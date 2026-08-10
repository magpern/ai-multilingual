#!/usr/bin/env php
<?php
/**
 * TQ.0 corpus validation CLI.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';

use AIMultilingual\Quality\CorpusValidator;

$version = $argv[1] ?? 'C1.0';

$validator = new CorpusValidator();
$result    = $validator->validate( $version );

foreach ( $result['warnings'] as $warning ) {
	fwrite( STDERR, 'WARN: ' . $warning . "\n" );
}

if ( ! $result['ok'] ) {
	foreach ( $result['errors'] as $error ) {
		fwrite( STDERR, 'ERROR: ' . $error . "\n" );
	}
	echo "FAIL\tcorpus validation\n";
	exit( 1 );
}

echo "PASS\tcorpus validation\tcases=" . array_sum( $result['category_counts'] ) . "\n";
exit( 0 );
