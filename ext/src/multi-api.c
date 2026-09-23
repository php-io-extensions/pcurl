#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ext.h"
#include "multi-api.h"
#include "zend_exceptions.h"
#include "ext/curl/php_curl.h"
#include <curl/curl.h>
#include <pthread.h>
#include <stdint.h>
#include <string.h>
#include <sys/select.h>

/*
 * ext-curl keeps its structs private. Both begin with the libcurl handle, and
 * zend_object std is last, so handlers->offset walks back to the struct head:
 *   php_curlm { CURLM *multi; zend_llist easyh; ...; zend_object std; }
 *   php_curl  { CURL *cp; ...; zend_object std; }
 * easyh holds a zval per CurlHandle added with curl_multi_add_handle().
 */
typedef struct {
    CURLM      *multi;
    zend_llist  easyh;
} pcurl_curlm_head;

typedef struct {
    zend_object *multi;        /* always live: the entry is dropped from the multi's free_obj */
    zval         socket_cb;
    zval         socket_data;
    zval         timer_cb;
    zval         timer_data;
    HashTable    sockp;        /* curl_socket_t => zval* handed to curl_multi_assign() */
} pcurl_multi_state;

ZEND_TLS HashTable *pcurl_registry = NULL;   /* (uintptr_t) CURLM* => pcurl_multi_state* */

/* Tracked CurlMultiHandles get a copy of ext-curl's handlers whose free_obj drops their state. */
static zend_object_handlers   pcurl_multi_handlers;
static zend_object_free_obj_t pcurl_multi_free_obj_orig = NULL;
static pthread_mutex_t        pcurl_multi_handlers_lock = PTHREAD_MUTEX_INITIALIZER;

static void pcurl_sockp_dtor(zval *zv)
{
    zval *p = Z_PTR_P(zv);

    zval_ptr_dtor(p);
    efree(p);
}

static void pcurl_state_dtor(zval *zv)
{
    pcurl_multi_state *st = Z_PTR_P(zv);

    zval_ptr_dtor(&st->socket_cb);
    zval_ptr_dtor(&st->socket_data);
    zval_ptr_dtor(&st->timer_cb);
    zval_ptr_dtor(&st->timer_data);
    zend_hash_destroy(&st->sockp);
    efree(st);
}

static CURLM *pcurl_obj_multi(zend_object *obj)
{
    return ((pcurl_curlm_head *) ((char *) obj - obj->handlers->offset))->multi;
}

/* libcurl's SOCKETDATA/TIMERDATA point at the state; unhook before the state can go. */
static void pcurl_multi_unhook(CURLM *m)
{
    curl_multi_setopt(m, CURLMOPT_SOCKETFUNCTION, NULL);
    curl_multi_setopt(m, CURLMOPT_TIMERFUNCTION, NULL);
}

static void pcurl_multi_free_obj(zend_object *obj)
{
    CURLM *m = pcurl_obj_multi(obj);
    zend_ulong key = (zend_ulong) (uintptr_t) m;
    zend_bool tracked = m && pcurl_registry && zend_hash_index_exists(pcurl_registry, key);

    if (tracked) {
        pcurl_multi_unhook(m);
    }
    pcurl_multi_free_obj_orig(obj);
    if (tracked && pcurl_registry) {
        zend_hash_index_del(pcurl_registry, key);
    }
}

static void pcurl_multi_track(zend_object *obj)
{
    pthread_mutex_lock(&pcurl_multi_handlers_lock);
    if (!pcurl_multi_free_obj_orig) {
        memcpy(&pcurl_multi_handlers, obj->handlers, sizeof(pcurl_multi_handlers));
        pcurl_multi_free_obj_orig = obj->handlers->free_obj;
        pcurl_multi_handlers.free_obj = pcurl_multi_free_obj;
    }
    pthread_mutex_unlock(&pcurl_multi_handlers_lock);

    if (obj->handlers->free_obj == pcurl_multi_free_obj_orig) {
        obj->handlers = &pcurl_multi_handlers;
    }
}

static pcurl_curlm_head *pcurl_multi_head(zval *mh)
{
    zend_object *obj;

    if (!mh || Z_TYPE_P(mh) != IS_OBJECT || Z_OBJCE_P(mh) != curl_multi_ce) {
        zend_throw_exception(zend_ce_exception, "expected a CurlMultiHandle", 0);
        return NULL;
    }

    obj = Z_OBJ_P(mh);

    return (pcurl_curlm_head *) ((char *) obj - obj->handlers->offset);
}

static CURLM *pcurl_multi_ptr(zval *mh)
{
    pcurl_curlm_head *head = pcurl_multi_head(mh);

    return head ? head->multi : NULL;
}

static pcurl_multi_state *pcurl_state_for(zval *mh, CURLM *m)
{
    pcurl_multi_state *st;

    if (!pcurl_registry) {
        ALLOC_HASHTABLE(pcurl_registry);
        zend_hash_init(pcurl_registry, 8, NULL, pcurl_state_dtor, 0);
    }

    st = zend_hash_index_find_ptr(pcurl_registry, (zend_ulong) (uintptr_t) m);
    if (!st) {
        st = ecalloc(1, sizeof(*st));
        st->multi = Z_OBJ_P(mh);
        ZVAL_UNDEF(&st->socket_cb);
        ZVAL_NULL(&st->socket_data);
        ZVAL_UNDEF(&st->timer_cb);
        ZVAL_NULL(&st->timer_data);
        zend_hash_init(&st->sockp, 8, NULL, pcurl_sockp_dtor, 0);
        zend_hash_index_add_new_ptr(pcurl_registry, (zend_ulong) (uintptr_t) m, st);
        pcurl_multi_track(st->multi);
    }

    return st;
}

static void pcurl_easy_zval(zval *out, pcurl_multi_state *st, CURL *e)
{
    pcurl_curlm_head *head = (pcurl_curlm_head *) ((char *) st->multi - st->multi->handlers->offset);
    zend_llist_position pos;
    zval *z;

    for (z = zend_llist_get_first_ex(&head->easyh, &pos); z; z = zend_llist_get_next_ex(&head->easyh, &pos)) {
        zend_object *obj;

        if (Z_TYPE_P(z) != IS_OBJECT || Z_OBJCE_P(z) != curl_ce) {
            continue;
        }
        obj = Z_OBJ_P(z);
        if (*(CURL **) ((char *) obj - obj->handlers->offset) == e) {
            ZVAL_OBJ(out, obj);
            return;
        }
    }
    ZVAL_NULL(out);
}

static int pcurl_callback_result(zval *rv, int called)
{
    int res = 0;

    if (called != SUCCESS || EG(exception)) {
        res = -1;
    } else if (Z_TYPE_P(rv) != IS_UNDEF && Z_TYPE_P(rv) != IS_NULL) {
        res = (int) zval_get_long(rv);
    }
    zval_ptr_dtor(rv);

    return res;
}

/* curl_socket_callback: (CURL *easy, curl_socket_t s, int what, void *clientp, void *socketp) */
static int pcurl_socket_trampoline(CURL *e, curl_socket_t s, int what, void *clientp, void *socketp)
{
    pcurl_multi_state *st = clientp;
    zval args[5], rv;

    if (Z_ISUNDEF(st->socket_cb)) {
        return 0;
    }

    pcurl_easy_zval(&args[0], st, e);
    ZVAL_LONG(&args[1], (zend_long) s);
    ZVAL_LONG(&args[2], (zend_long) what);
    ZVAL_COPY_VALUE(&args[3], &st->socket_data);
    if (socketp) {
        ZVAL_COPY_VALUE(&args[4], (zval *) socketp);
    } else {
        ZVAL_NULL(&args[4]);
    }
    ZVAL_UNDEF(&rv);

    return pcurl_callback_result(&rv, call_user_function(NULL, NULL, &st->socket_cb, &rv, 5, args));
}

/* curl_multi_timer_callback: (CURLM *multi, long timeout_ms, void *clientp) */
static int pcurl_timer_trampoline(CURLM *m, long timeout_ms, void *clientp)
{
    pcurl_multi_state *st = clientp;
    zval args[3], rv;

    (void) m;
    if (Z_ISUNDEF(st->timer_cb)) {
        return 0;
    }

    ZVAL_OBJ(&args[0], st->multi);
    ZVAL_LONG(&args[1], (zend_long) timeout_ms);
    ZVAL_COPY_VALUE(&args[2], &st->timer_data);
    ZVAL_UNDEF(&rv);

    return pcurl_callback_result(&rv, call_user_function(NULL, NULL, &st->timer_cb, &rv, 3, args));
}

static CURLMcode pcurl_store_callback(CURLM *m, zval *slot, zval *value, CURLMoption fn_opt, CURLMoption data_opt, void *trampoline, pcurl_multi_state *st)
{
    if (Z_TYPE_P(value) != IS_NULL && !zend_is_callable(value, 0, NULL)) {
        return CURLM_BAD_FUNCTION_ARGUMENT;
    }

    zval_ptr_dtor(slot);
    ZVAL_UNDEF(slot);

    if (Z_TYPE_P(value) == IS_NULL) {
        return curl_multi_setopt(m, fn_opt, NULL);
    }

    ZVAL_COPY(slot, value);
    curl_multi_setopt(m, data_opt, st);

    return curl_multi_setopt(m, fn_opt, trampoline);
}

static void pcurl_store_data(zval *slot, zval *value)
{
    zval_ptr_dtor(slot);
    ZVAL_COPY(slot, value);
}

zend_long pcurl_multi_setopt(zval *mh, zval *option, zval *value)
{
    CURLM *m = pcurl_multi_ptr(mh);
    pcurl_multi_state *st;
    zend_long opt = zval_get_long(option);

    if (!m) {
        return CURLM_BAD_HANDLE;
    }

    switch (opt) {
        case CURLMOPT_SOCKETFUNCTION:
            st = pcurl_state_for(mh, m);
            return pcurl_store_callback(m, &st->socket_cb, value, CURLMOPT_SOCKETFUNCTION, CURLMOPT_SOCKETDATA, (void *) pcurl_socket_trampoline, st);
        case CURLMOPT_TIMERFUNCTION:
            st = pcurl_state_for(mh, m);
            return pcurl_store_callback(m, &st->timer_cb, value, CURLMOPT_TIMERFUNCTION, CURLMOPT_TIMERDATA, (void *) pcurl_timer_trampoline, st);
        case CURLMOPT_SOCKETDATA:
            pcurl_store_data(&pcurl_state_for(mh, m)->socket_data, value);
            return CURLM_OK;
        case CURLMOPT_TIMERDATA:
            pcurl_store_data(&pcurl_state_for(mh, m)->timer_data, value);
            return CURLM_OK;
    }

    if (opt < CURLOPTTYPE_OBJECTPOINT) {
        return curl_multi_setopt(m, (CURLMoption) opt, (long) zval_get_long(value));
    }

    return CURLM_UNKNOWN_OPTION;
}

void pcurl_multi_socket_action(zval *return_value, zval *mh, zval *s, zval *ev_bitmask)
{
    CURLM *m = pcurl_multi_ptr(mh);
    int running = 0;
    CURLMcode res;

    if (!m) {
        return;
    }

    res = curl_multi_socket_action(m, (curl_socket_t) zval_get_long(s), (int) zval_get_long(ev_bitmask), &running);

    array_init(return_value);
    add_assoc_long(return_value, "res", (zend_long) res);
    add_assoc_long(return_value, "running_handles", (zend_long) running);
}

void pcurl_multi_fdset(zval *return_value, zval *mh)
{
    CURLM *m = pcurl_multi_ptr(mh);
    fd_set r, w, e;
    int max_fd = -1, i;
    zval zr, zw, ze;
    CURLMcode res;

    if (!m) {
        return;
    }

    FD_ZERO(&r);
    FD_ZERO(&w);
    FD_ZERO(&e);
    res = curl_multi_fdset(m, &r, &w, &e, &max_fd);

    array_init(&zr);
    array_init(&zw);
    array_init(&ze);
    for (i = 0; i <= max_fd && i < FD_SETSIZE; i++) {
        if (FD_ISSET(i, &r)) add_next_index_long(&zr, i);
        if (FD_ISSET(i, &w)) add_next_index_long(&zw, i);
        if (FD_ISSET(i, &e)) add_next_index_long(&ze, i);
    }

    array_init(return_value);
    add_assoc_long(return_value, "res", (zend_long) res);
    add_assoc_zval(return_value, "read_fd_set", &zr);
    add_assoc_zval(return_value, "write_fd_set", &zw);
    add_assoc_zval(return_value, "exc_fd_set", &ze);
    add_assoc_long(return_value, "max_fd", (zend_long) max_fd);
}

void pcurl_multi_timeout(zval *return_value, zval *mh)
{
    CURLM *m = pcurl_multi_ptr(mh);
    long timeout = -1;
    CURLMcode res;

    if (!m) {
        return;
    }

    res = curl_multi_timeout(m, &timeout);

    array_init(return_value);
    add_assoc_long(return_value, "res", (zend_long) res);
    add_assoc_long(return_value, "timeout", (zend_long) timeout);
}

/* extra_fds: list of ['fd' => int, 'events' => int] mirroring struct curl_waitfd; 'revents' is filled on return. */
static void pcurl_multi_waitfds(zval *return_value, zval *mh, zval *extra_fds, zval *timeout_ms, int use_poll)
{
    CURLM *m = pcurl_multi_ptr(mh);
    HashTable *ht;
    struct curl_waitfd *fds = NULL;
    unsigned int n, i = 0;
    int numfds = 0;
    zval *v, *f, zfds;
    CURLMcode res;

    if (!m) {
        return;
    }
    if (Z_TYPE_P(extra_fds) != IS_ARRAY) {
        zend_throw_exception(zend_ce_exception, "extra_fds must be an array", 0);
        return;
    }

    ht = Z_ARRVAL_P(extra_fds);
    n = zend_hash_num_elements(ht);
    if (n) {
        fds = ecalloc(n, sizeof(*fds));
    }

    ZEND_HASH_FOREACH_VAL(ht, v) {
        ZVAL_DEREF(v);
        if (Z_TYPE_P(v) != IS_ARRAY || !(f = zend_hash_str_find(Z_ARRVAL_P(v), "fd", sizeof("fd") - 1))) {
            if (fds) efree(fds);
            zend_throw_exception(zend_ce_exception, "each extra_fds entry needs an 'fd' key", 0);
            return;
        }
        fds[i].fd = (curl_socket_t) zval_get_long(f);
        f = zend_hash_str_find(Z_ARRVAL_P(v), "events", sizeof("events") - 1);
        fds[i].events = f ? (short) zval_get_long(f) : 0;
        i++;
    } ZEND_HASH_FOREACH_END();

    res = use_poll
        ? curl_multi_poll(m, fds, n, (int) zval_get_long(timeout_ms), &numfds)
        : curl_multi_wait(m, fds, n, (int) zval_get_long(timeout_ms), &numfds);

    array_init_size(&zfds, n);
    for (i = 0; i < n; i++) {
        zval entry;

        array_init_size(&entry, 3);
        add_assoc_long(&entry, "fd", (zend_long) fds[i].fd);
        add_assoc_long(&entry, "events", (zend_long) fds[i].events);
        add_assoc_long(&entry, "revents", (zend_long) fds[i].revents);
        add_next_index_zval(&zfds, &entry);
    }
    if (fds) {
        efree(fds);
    }

    array_init(return_value);
    add_assoc_long(return_value, "res", (zend_long) res);
    add_assoc_long(return_value, "numfds", (zend_long) numfds);
    add_assoc_zval(return_value, "extra_fds", &zfds);
}

void pcurl_multi_poll(zval *return_value, zval *mh, zval *extra_fds, zval *timeout_ms)
{
    pcurl_multi_waitfds(return_value, mh, extra_fds, timeout_ms, 1);
}

void pcurl_multi_wait(zval *return_value, zval *mh, zval *extra_fds, zval *timeout_ms)
{
    pcurl_multi_waitfds(return_value, mh, extra_fds, timeout_ms, 0);
}

zend_long pcurl_multi_wakeup(zval *mh)
{
    CURLM *m = pcurl_multi_ptr(mh);

    if (!m) {
        return CURLM_BAD_HANDLE;
    }

    return (zend_long) curl_multi_wakeup(m);
}

zend_long pcurl_multi_assign(zval *mh, zval *s, zval *sockp)
{
    CURLM *m = pcurl_multi_ptr(mh);
    pcurl_multi_state *st;
    zend_ulong key;
    zval *p = NULL;
    CURLMcode res;

    if (!m) {
        return CURLM_BAD_HANDLE;
    }

    st = pcurl_state_for(mh, m);
    key = (zend_ulong) zval_get_long(s);

    if (Z_TYPE_P(sockp) != IS_NULL) {
        p = emalloc(sizeof(zval));
        ZVAL_COPY(p, sockp);
    }

    res = curl_multi_assign(m, (curl_socket_t) key, p);
    if (res != CURLM_OK) {
        if (p) {
            zval_ptr_dtor(p);
            efree(p);
        }
        return (zend_long) res;
    }

    if (p) {
        zend_hash_index_update_ptr(&st->sockp, key, p);
    } else {
        zend_hash_index_del(&st->sockp, key);
    }

    return (zend_long) res;
}

void pcurl_multi_strerror(zval *return_value, zval *code)
{
    RETVAL_STRING(curl_multi_strerror((CURLMcode) zval_get_long(code)));
}

void pcurl_multi_api_rshutdown(void)
{
    HashTable *registry = pcurl_registry;
    pcurl_multi_state *st;

    if (!registry) {
        return;
    }

    /* Multis still alive are freed after RSHUTDOWN; their free_obj then sees no registry. */
    pcurl_registry = NULL;
    ZEND_HASH_FOREACH_PTR(registry, st) {
        pcurl_multi_unhook(pcurl_obj_multi(st->multi));
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(registry);
    FREE_HASHTABLE(registry);
}
