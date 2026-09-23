
/* This file was generated automatically by Zephir do not modify it! */

#ifndef PHP_PCURL_H
#define PHP_PCURL_H 1

#ifdef PHP_WIN32
#define ZEPHIR_RELEASE 1
#endif

#include "kernel/globals.h"

#define PHP_PCURL_NAME        "pcurl"
#define PHP_PCURL_VERSION     "0.9.0"
#define PHP_PCURL_EXTNAME     "pcurl"
#define PHP_PCURL_AUTHOR      "Project Saturn Studios, LLC"
#define PHP_PCURL_ZEPVERSION  "0.19.0-$Id$"
#define PHP_PCURL_DESCRIPTION "libcurl's multi socket API bound 1:1 into PHP on top of ext-curl handles"



ZEND_BEGIN_MODULE_GLOBALS(pcurl)

	int initialized;

	/** Function cache */
	HashTable *fcache;

	zephir_fcall_cache_entry *scache[ZEPHIR_MAX_CACHE_SLOTS];

	/* Cache enabled */
	unsigned int cache_enabled;

	/* Max recursion control */
	unsigned int recursive_lock;

	
ZEND_END_MODULE_GLOBALS(pcurl)

#ifdef ZTS
#include "TSRM.h"
#endif

ZEND_EXTERN_MODULE_GLOBALS(pcurl)

#ifdef ZTS
	#define ZEPHIR_GLOBAL(v) ZEND_MODULE_GLOBALS_ACCESSOR(pcurl, v)
#else
	#define ZEPHIR_GLOBAL(v) (pcurl_globals.v)
#endif

#ifdef ZTS
	ZEND_TSRMLS_CACHE_EXTERN()
	#define ZEPHIR_VGLOBAL ((zend_pcurl_globals *) (*((void ***) tsrm_get_ls_cache()))[TSRM_UNSHUFFLE_RSRC_ID(pcurl_globals_id)])
#else
	#define ZEPHIR_VGLOBAL &(pcurl_globals)
#endif

#define ZEPHIR_API ZEND_API

#define zephir_globals_def pcurl_globals
#define zend_zephir_globals_def zend_pcurl_globals

extern zend_module_entry pcurl_module_entry;
#define phpext_pcurl_ptr &pcurl_module_entry

#endif
