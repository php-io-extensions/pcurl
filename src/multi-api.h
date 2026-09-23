#ifndef PHP_PCURL_MULTI_API_H
#define PHP_PCURL_MULTI_API_H

#include "php.h"

zend_long pcurl_multi_setopt(zval *mh, zval *option, zval *value);
void      pcurl_multi_socket_action(zval *return_value, zval *mh, zval *s, zval *ev_bitmask);
void      pcurl_multi_fdset(zval *return_value, zval *mh);
void      pcurl_multi_timeout(zval *return_value, zval *mh);
void      pcurl_multi_poll(zval *return_value, zval *mh, zval *extra_fds, zval *timeout_ms);
void      pcurl_multi_wait(zval *return_value, zval *mh, zval *extra_fds, zval *timeout_ms);
zend_long pcurl_multi_wakeup(zval *mh);
zend_long pcurl_multi_assign(zval *mh, zval *s, zval *sockp);
void      pcurl_multi_strerror(zval *return_value, zval *code);
void      pcurl_multi_api_rshutdown(void);

#endif /* PHP_PCURL_MULTI_API_H */
