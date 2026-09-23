
/* This file was generated automatically by Zephir do not modify it! */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <php.h>

#include "php_ext.h"
#include "pcurl.h"

#include <ext/standard/info.h>

#include <Zend/zend_operators.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_interfaces.h>

#include "kernel/globals.h"
#include "kernel/main.h"
#include "kernel/fcall.h"
#include "kernel/memory.h"


#include "src/multi-api.h"

zend_class_entry *pcurl_multi_ce;

ZEND_DECLARE_MODULE_GLOBALS(pcurl)

PHP_INI_BEGIN()
	
PHP_INI_END()

static PHP_MINIT_FUNCTION(pcurl)
{
	REGISTER_INI_ENTRIES();
	zephir_module_init();
	ZEPHIR_INIT(Pcurl_Multi);
	
	return SUCCESS;
}

#ifndef ZEPHIR_RELEASE
static PHP_MSHUTDOWN_FUNCTION(pcurl)
{
	
	zephir_deinitialize_memory();
	UNREGISTER_INI_ENTRIES();
	return SUCCESS;
}
#endif

/**
 * Initialize globals on each request or each thread started
 */
static void php_zephir_init_globals(zend_pcurl_globals *pcurl_globals)
{
	pcurl_globals->initialized = 0;

	/* Cache Enabled */
	pcurl_globals->cache_enabled = 1;

	/* Recursive Lock */
	pcurl_globals->recursive_lock = 0;

	/* Static cache */
	memset(pcurl_globals->scache, '\0', sizeof(zephir_fcall_cache_entry*) * ZEPHIR_MAX_CACHE_SLOTS);

	
	
}

/**
 * Initialize globals only on each thread started
 */
static void php_zephir_init_module_globals(zend_pcurl_globals *pcurl_globals)
{
	
}

static PHP_RINIT_FUNCTION(pcurl)
{
	zend_pcurl_globals *pcurl_globals_ptr;
	pcurl_globals_ptr = ZEPHIR_VGLOBAL;

	php_zephir_init_globals(pcurl_globals_ptr);
	zephir_initialize_memory(pcurl_globals_ptr);

	
	return SUCCESS;
}

static PHP_RSHUTDOWN_FUNCTION(pcurl)
{
	pcurl_multi_api_rshutdown();
	zephir_deinitialize_memory();
	return SUCCESS;
}



static PHP_MINFO_FUNCTION(pcurl)
{
	php_info_print_box_start(0);
	php_printf("%s", PHP_PCURL_DESCRIPTION);
	php_info_print_box_end();

	php_info_print_table_start();
	php_info_print_table_header(2, PHP_PCURL_NAME, "enabled");
	php_info_print_table_row(2, "Author", PHP_PCURL_AUTHOR);
	php_info_print_table_row(2, "Version", PHP_PCURL_VERSION);
	php_info_print_table_row(2, "Build Date", __DATE__ " " __TIME__ );
	php_info_print_table_row(2, "Powered by Zephir", "Version " PHP_PCURL_ZEPVERSION);
	php_info_print_table_end();
	
	DISPLAY_INI_ENTRIES();
}

static PHP_GINIT_FUNCTION(pcurl)
{
#if defined(COMPILE_DL_PCURL) && defined(ZTS)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif

	php_zephir_init_globals(pcurl_globals);
	php_zephir_init_module_globals(pcurl_globals);
}

static PHP_GSHUTDOWN_FUNCTION(pcurl)
{
	
}


zend_function_entry php_pcurl_functions[] = {
	ZEND_FE_END

};

static const zend_module_dep php_pcurl_deps[] = {
	ZEND_MOD_REQUIRED("curl")
	ZEND_MOD_END
};

zend_module_entry pcurl_module_entry = {
	STANDARD_MODULE_HEADER_EX,
	NULL,
	php_pcurl_deps,
	PHP_PCURL_EXTNAME,
	php_pcurl_functions,
	PHP_MINIT(pcurl),
#ifndef ZEPHIR_RELEASE
	PHP_MSHUTDOWN(pcurl),
#else
	NULL,
#endif
	PHP_RINIT(pcurl),
	PHP_RSHUTDOWN(pcurl),
	PHP_MINFO(pcurl),
	PHP_PCURL_VERSION,
	ZEND_MODULE_GLOBALS(pcurl),
	PHP_GINIT(pcurl),
	PHP_GSHUTDOWN(pcurl),
#ifdef ZEPHIR_POST_REQUEST
	PHP_PRSHUTDOWN(pcurl),
#else
	NULL,
#endif
	STANDARD_MODULE_PROPERTIES_EX
};

/* implement standard "stub" routine to introduce ourselves to Zend */
#ifdef COMPILE_DL_PCURL
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(pcurl)
#endif
