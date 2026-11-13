<?php
/**
 * Lists the translatable strings of a package, grouped by text domain.
 * Usage: php tools/dev/i18n-extract.php <dir> <text-domain>
 * Output: JSON array of msgids in WordPress .l10n.php key format (plural
 * strings are keyed by their singular, "context\4msgid" for strings with context).
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

function amc_i18n_extract( $dir, $domain ) {
	$strings = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( 'php' !== $file->getExtension() || false !== strpos( $file->getPathname(), '/languages/' ) ) {
			continue;
		}
		$tokens = token_get_all( file_get_contents( $file->getPathname() ) );
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] ) {
				continue;
			}
			$fn = $tokens[ $i ][1];
			if ( ! in_array( $fn, array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_n', '_x' ), true ) ) {
				continue;
			}
			// Collect the string-literal arguments up to the closing parenthesis.
			$args  = array();
			$depth = 0;
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$t = $tokens[ $j ];
				if ( '(' === $t ) {
					++$depth;
				} elseif ( ')' === $t ) {
					if ( 0 === --$depth ) {
						break;
					}
				} elseif ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] && 1 === $depth ) {
					$args[] = stripcslashes( substr( $t[1], 1, -1 ) );
				}
			}
			if ( end( $args ) !== $domain ) {
				continue;
			}
			if ( '_n' === $fn ) {
				// WordPress looks plurals up by their singular; the translation holds "one\0many".
				$strings[ $args[0] ] = true;
			} elseif ( '_x' === $fn ) {
				$strings[ $args[1] . "\4" . $args[0] ] = true;
			} else {
				$strings[ $args[0] ] = true;
			}
		}
	}
	return array_keys( $strings );
}

if ( isset( $argv[2] ) && realpath( $argv[0] ) === __FILE__ ) {
	echo json_encode( amc_i18n_extract( $argv[1], $argv[2] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), "\n";
}
