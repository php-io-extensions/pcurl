
extern zend_class_entry *pcurl_multi_ce;

ZEPHIR_INIT_CLASS(Pcurl_Multi);

PHP_METHOD(Pcurl_Multi, curlMultiSetopt);
PHP_METHOD(Pcurl_Multi, curlMultiSocketAction);
PHP_METHOD(Pcurl_Multi, curlMultiFdset);
PHP_METHOD(Pcurl_Multi, curlMultiTimeout);
PHP_METHOD(Pcurl_Multi, curlMultiPoll);
PHP_METHOD(Pcurl_Multi, curlMultiWait);
PHP_METHOD(Pcurl_Multi, curlMultiWakeup);
PHP_METHOD(Pcurl_Multi, curlMultiAssign);
PHP_METHOD(Pcurl_Multi, curlMultiStrerror);

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultisetopt, 0, 3, IS_LONG, 0)
	ZEND_ARG_INFO(0, multi)
	ZEND_ARG_TYPE_INFO(0, option, IS_LONG, 0)
	ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultisocketaction, 0, 3, IS_ARRAY, 0)
	ZEND_ARG_INFO(0, multi)
	ZEND_ARG_TYPE_INFO(0, s, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, evBitmask, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultifdset, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_INFO(0, multi)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultitimeout, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_INFO(0, multi)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultipoll, 0, 3, IS_ARRAY, 0)
	ZEND_ARG_INFO(0, multi)
	ZEND_ARG_ARRAY_INFO(0, extraFds, 0)
	ZEND_ARG_TYPE_INFO(0, timeoutMs, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultiwait, 0, 3, IS_ARRAY, 0)
	ZEND_ARG_INFO(0, multi)
	ZEND_ARG_ARRAY_INFO(0, extraFds, 0)
	ZEND_ARG_TYPE_INFO(0, timeoutMs, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultiwakeup, 0, 1, IS_LONG, 0)
	ZEND_ARG_INFO(0, multi)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultiassign, 0, 3, IS_LONG, 0)
	ZEND_ARG_INFO(0, multi)
	ZEND_ARG_TYPE_INFO(0, s, IS_LONG, 0)
	ZEND_ARG_INFO(0, sockp)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_pcurl_multi_curlmultistrerror, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, code, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEPHIR_INIT_FUNCS(pcurl_multi_method_entry) {
	PHP_ME(Pcurl_Multi, curlMultiSetopt, arginfo_pcurl_multi_curlmultisetopt, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiSocketAction, arginfo_pcurl_multi_curlmultisocketaction, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiFdset, arginfo_pcurl_multi_curlmultifdset, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiTimeout, arginfo_pcurl_multi_curlmultitimeout, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiPoll, arginfo_pcurl_multi_curlmultipoll, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiWait, arginfo_pcurl_multi_curlmultiwait, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiWakeup, arginfo_pcurl_multi_curlmultiwakeup, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiAssign, arginfo_pcurl_multi_curlmultiassign, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_ME(Pcurl_Multi, curlMultiStrerror, arginfo_pcurl_multi_curlmultistrerror, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	PHP_FE_END
};
