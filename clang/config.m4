PHP_ARG_ENABLE(pcurl, whether to enable pcurl, [ --enable-pcurl   Enable Pcurl])

if test "$PHP_PCURL" = "yes"; then

	
	AC_PATH_PROG(PKG_CONFIG, pkg-config, no)
	if test "x$PKG_CONFIG" = "xno"; then
		AC_MSG_RESULT([pkg-config not found])
		AC_MSG_ERROR([Please reinstall the pkg-config distribution])
	fi


	AC_MSG_CHECKING([for libcurl])
	if $PKG_CONFIG --exists libcurl; then
		PHP_LIBCURL_VERSION=`$PKG_CONFIG libcurl --modversion`
		PHP_LIBCURL_PREFIX=`$PKG_CONFIG libcurl --variable=prefix`

		if $PKG_CONFIG --atleast-version=7.68.0 libcurl; then
			AC_MSG_RESULT([found version $PHP_LIBCURL_VERSION, under $PHP_LIBCURL_PREFIX])
			PHP_LIBCURL_LIBS=`$PKG_CONFIG libcurl --libs`
			PHP_LIBCURL_INCS=`$PKG_CONFIG libcurl --cflags-only-I`

			PHP_EVAL_LIBLINE($PHP_LIBCURL_LIBS, PCURL_SHARED_LIBADD)
			PHP_EVAL_INCLINE($PHP_LIBCURL_INCS)
		else
			AC_MSG_ERROR(Requested 'libcurl >= 7.68.0' but version of libcurl is $PHP_LIBCURL_VERSION)
		fi
	else
		AC_MSG_ERROR(Unable to find libcurl installation)
	fi



	if ! test "x" = "x"; then
		PHP_EVAL_LIBLINE(, PCURL_SHARED_LIBADD)
	fi

	AC_DEFINE(HAVE_PCURL, 1, [Whether you have Pcurl])
	CFLAGS="$CFLAGS -Wno-error=incompatible-pointer-types -Wno-incompatible-pointer-types -Wno-pointer-compare -Wno-error=int-conversion"
	pcurl_sources="pcurl.c kernel/main.c kernel/memory.c kernel/exception.c kernel/debug.c kernel/backtrace.c kernel/object.c kernel/array.c kernel/string.c kernel/fcall.c kernel/require.c kernel/file.c kernel/operators.c kernel/math.c kernel/concat.c kernel/variables.c kernel/filter.c kernel/iterator.c kernel/time.c kernel/exit.c pcurl/multi.zep.c src/multi-api.c"
	PHP_NEW_EXTENSION(pcurl, $pcurl_sources, $ext_shared,,  $PHP_LIBCURL_INCS )
	PHP_ADD_EXTENSION_DEP([pcurl], [curl])
	PHP_ADD_BUILD_DIR([$ext_builddir/kernel/])
	for dir in "pcurl" "src"; do
		PHP_ADD_BUILD_DIR([$ext_builddir/$dir])
	done
	PHP_SUBST(PCURL_SHARED_LIBADD)

	old_CPPFLAGS=$CPPFLAGS
	CPPFLAGS="$CPPFLAGS $INCLUDES"

	AC_CHECK_DECL(
		[HAVE_BUNDLED_PCRE],
		[
			AC_CHECK_HEADERS(
				[ext/pcre/php_pcre.h],
				[
					PHP_ADD_EXTENSION_DEP([pcurl], [pcre])
					AC_DEFINE([ZEPHIR_USE_PHP_PCRE], [1], [Whether PHP pcre extension is present at compile time])
				],
				,
				[[#include "main/php.h"]]
			)
		],
		,
		[[#include "php_config.h"]]
	)

	AC_CHECK_DECL(
		[HAVE_JSON],
		[
			AC_CHECK_HEADERS(
				[ext/json/php_json.h],
				[
					PHP_ADD_EXTENSION_DEP([pcurl], [json])
					AC_DEFINE([ZEPHIR_USE_PHP_JSON], [1], [Whether PHP json extension is present at compile time])
				],
				,
				[[#include "main/php.h"]]
			)
		],
		,
		[[#include "php_config.h"]]
	)

	CPPFLAGS=$old_CPPFLAGS

	PHP_INSTALL_HEADERS([ext/pcurl], [php_PCURL.h])

fi
