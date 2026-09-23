
#ifdef HAVE_CONFIG_H
#include "../ext_config.h"
#endif

#include <php.h>
#include "../php_ext.h"
#include "../ext.h"

#include <Zend/zend_operators.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_interfaces.h>

#include "kernel/main.h"
#include "src/multi-api.h"
#include "kernel/operators.h"
#include "kernel/memory.h"
#include "kernel/object.h"
#include "kernel/array.h"


ZEPHIR_INIT_CLASS(Pcurl_Multi)
{
	ZEPHIR_REGISTER_CLASS(Pcurl, Multi, pcurl, multi, pcurl_multi_method_entry, 0);

	return SUCCESS;
}

PHP_METHOD(Pcurl_Multi, curlMultiSetopt)
{
	zend_long option;
	zval *multi = NULL, multi_sub, *option_param = NULL, *value = NULL, value_sub, _0;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&value_sub);
	ZVAL_UNDEF(&_0);
	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(multi)
		Z_PARAM_LONG(option)
		Z_PARAM_ZVAL(value)
	ZEND_PARSE_PARAMETERS_END();
	zephir_fetch_params_without_memory_grow(3, 0, &multi, &option_param, &value);
	ZVAL_LONG(&_0, option);
	RETURN_LONG(pcurl_multi_setopt(multi, &_0, value));
}

PHP_METHOD(Pcurl_Multi, curlMultiSocketAction)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zend_long s, evBitmask;
	zval *multi = NULL, multi_sub, *s_param = NULL, *evBitmask_param = NULL, result, _0, _1;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&result);
	ZVAL_UNDEF(&_0);
	ZVAL_UNDEF(&_1);
	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(multi)
		Z_PARAM_LONG(s)
		Z_PARAM_LONG(evBitmask)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 3, 0, &multi, &s_param, &evBitmask_param);
	ZVAL_LONG(&_0, s);
	ZVAL_LONG(&_1, evBitmask);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_socket_action(&result, multi, &_0, &_1);
	RETURN_CCTOR(&result);
}

PHP_METHOD(Pcurl_Multi, curlMultiFdset)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zval *multi = NULL, multi_sub, result;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&result);
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(multi)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 1, 0, &multi);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_fdset(&result, multi);
	RETURN_CCTOR(&result);
}

PHP_METHOD(Pcurl_Multi, curlMultiTimeout)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zval *multi = NULL, multi_sub, result;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&result);
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(multi)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 1, 0, &multi);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_timeout(&result, multi);
	RETURN_CCTOR(&result);
}

PHP_METHOD(Pcurl_Multi, curlMultiPoll)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zend_long timeoutMs;
	zval extraFds;
	zval *multi = NULL, multi_sub, *extraFds_param = NULL, *timeoutMs_param = NULL, result, _0;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&result);
	ZVAL_UNDEF(&_0);
	ZVAL_UNDEF(&extraFds);
	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(multi)
		Z_PARAM_ARRAY(extraFds)
		Z_PARAM_LONG(timeoutMs)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 3, 0, &multi, &extraFds_param, &timeoutMs_param);
	zephir_get_arrval(&extraFds, extraFds_param);
	ZVAL_LONG(&_0, timeoutMs);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_poll(&result, multi, &extraFds, &_0);
	RETURN_CCTOR(&result);
}

PHP_METHOD(Pcurl_Multi, curlMultiWait)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zend_long timeoutMs;
	zval extraFds;
	zval *multi = NULL, multi_sub, *extraFds_param = NULL, *timeoutMs_param = NULL, result, _0;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&result);
	ZVAL_UNDEF(&_0);
	ZVAL_UNDEF(&extraFds);
	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(multi)
		Z_PARAM_ARRAY(extraFds)
		Z_PARAM_LONG(timeoutMs)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 3, 0, &multi, &extraFds_param, &timeoutMs_param);
	zephir_get_arrval(&extraFds, extraFds_param);
	ZVAL_LONG(&_0, timeoutMs);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_wait(&result, multi, &extraFds, &_0);
	RETURN_CCTOR(&result);
}

PHP_METHOD(Pcurl_Multi, curlMultiWakeup)
{
	zval *multi = NULL, multi_sub;

	ZVAL_UNDEF(&multi_sub);
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_ZVAL(multi)
	ZEND_PARSE_PARAMETERS_END();
	zephir_fetch_params_without_memory_grow(1, 0, &multi);
	RETURN_LONG(pcurl_multi_wakeup(multi));
}

PHP_METHOD(Pcurl_Multi, curlMultiAssign)
{
	zend_long s;
	zval *multi = NULL, multi_sub, *s_param = NULL, *sockp = NULL, sockp_sub, _0;

	ZVAL_UNDEF(&multi_sub);
	ZVAL_UNDEF(&sockp_sub);
	ZVAL_UNDEF(&_0);
	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_ZVAL(multi)
		Z_PARAM_LONG(s)
		Z_PARAM_ZVAL(sockp)
	ZEND_PARSE_PARAMETERS_END();
	zephir_fetch_params_without_memory_grow(3, 0, &multi, &s_param, &sockp);
	ZVAL_LONG(&_0, s);
	RETURN_LONG(pcurl_multi_assign(multi, &_0, sockp));
}

PHP_METHOD(Pcurl_Multi, curlMultiStrerror)
{
	zephir_method_globals *ZEPHIR_METHOD_GLOBALS_PTR = NULL;
	zval *code_param = NULL, result, _0;
	zend_long code;

	ZVAL_UNDEF(&result);
	ZVAL_UNDEF(&_0);
	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_LONG(code)
	ZEND_PARSE_PARAMETERS_END();
	ZEPHIR_METHOD_GLOBALS_PTR = pecalloc(1, sizeof(zephir_method_globals), 0);
	zephir_memory_grow_stack(ZEPHIR_METHOD_GLOBALS_PTR, __func__);
	zephir_fetch_params(1, 1, 0, &code_param);
	ZVAL_LONG(&_0, code);
	ZEPHIR_INIT_VAR(&result);
	pcurl_multi_strerror(&result, &_0);
	RETURN_CCTOR(&result);
}

