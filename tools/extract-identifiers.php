<?php
/**
 * Wave 0.4 helper: emit every prefix-matching identifier appearing inside
 * any real PHP string literal (T_CONSTANT_ENCAPSED_STRING) under a directory
 * tree, one occurrence per line.
 *
 * Used by tools/capture-baselines.sh. Token-based so comments and docblocks
 * cannot pollute the baseline. The pattern is matched anywhere inside the
 * literal value (not anchored), so substrings like the `freeman_settings_export`
 * inside `'admin_post_freeman_settings_export'` are still captured — those
 * are real public surfaces (admin-post actions, capability slugs, etc.).
 *
 * Usage:
 *   php tools/extract-identifiers.php <dir> <pattern>
 */

declare(strict_types=1);

if ( $argc !== 3 ) {
	fwrite( STDERR, "Usage: php extract-identifiers.php <dir> <pattern>\n" );
	exit( 64 );
}

$dir     = $argv[1];
$pattern = '/' . $argv[2] . '/';

if ( ! is_dir( $dir ) ) {
	exit( 0 );
}

$it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
);

foreach ( $it as $file ) {
	if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
		continue;
	}
	$src = @file_get_contents( $file->getPathname() );
	if ( $src === false ) {
		continue;
	}
	foreach ( token_get_all( $src ) as $token ) {
		if ( ! is_array( $token ) || $token[0] !== T_CONSTANT_ENCAPSED_STRING ) {
			continue;
		}
		// $token[1] is the literal as it appears in source, including quotes.
		$raw = $token[1];
		if ( strlen( $raw ) < 2 ) {
			continue;
		}
		$quote = $raw[0];
		if ( $quote !== "'" && $quote !== '"' ) {
			continue;
		}
		$value = substr( $raw, 1, -1 );
		// Unescape the two quote-relevant escapes so the captured string
		// matches what get_option() etc. would actually receive.
		if ( $quote === "'" ) {
			$value = strtr( $value, array( "\\'" => "'", '\\\\' => '\\' ) );
		} else {
			$value = stripcslashes( $value );
		}
		if ( preg_match_all( $pattern, $value, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				echo $match . "\n";
			}
		}
	}
}
