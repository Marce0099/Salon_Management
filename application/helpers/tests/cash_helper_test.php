<?php
/**
 * Dependency-free scenario script for paymentSufficiencyToleranceCordobas() in
 * application/helpers/cash_helper.php. No PHPUnit/composer is installed in this repo, so this
 * runs standalone via CLI:
 *
 *   php application/helpers/tests/cash_helper_test.php
 *
 * Exits 0 with all scenarios passing, 1 otherwise.
 */

define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/cash_helper.php';

$passed = 0;
$failed = 0;

function check( $label, $actual, $expected ){
	global $passed, $failed;

	if( $actual === $expected ){
		$passed++;
	}else{
		$failed++;
		echo "FAIL: $label\n";
		echo "  expected: " . var_export($expected, true) . "\n";
		echo "  actual:   " . var_export($actual, true) . "\n";
	}
}

// ---------------------------------------------------------------------------
// No dollar-denominated rows: falls back to the pre-existing 0.01 córdoba-only floor.
// ---------------------------------------------------------------------------

check( 'zero dollar rows: floor of 0.01', paymentSufficiencyToleranceCordobas( 0, 30 ), 0.01 );
check( 'zero dollar rows, high sell rate: still floor of 0.01', paymentSufficiencyToleranceCordobas( 0, 60 ), 0.01 );

// ---------------------------------------------------------------------------
// One dollar-denominated row: half a US-cent's worth of córdobas at the given sell rate.
// ---------------------------------------------------------------------------

check( 'one dollar row at sell_rate=30: 0.15', paymentSufficiencyToleranceCordobas( 1, 30 ), 0.15 );
check( 'one dollar row at sell_rate=36.5: 0.1825', paymentSufficiencyToleranceCordobas( 1, 36.5 ), 0.1825 );

// ---------------------------------------------------------------------------
// Multiple dollar-denominated rows: scales linearly, still comfortably under the cap.
// ---------------------------------------------------------------------------

check( 'three dollar rows at sell_rate=36.5: 0.5475', paymentSufficiencyToleranceCordobas( 3, 36.5 ), 0.5475 );

// ---------------------------------------------------------------------------
// Anti-loophole: no matter how many dollar rows a sale has, the tolerance is capped well under
// a whole córdoba — stacking payment rows can never be used to forgive a real shortfall.
// ---------------------------------------------------------------------------

check( 'large row count clamps at the 0.99 cap', paymentSufficiencyToleranceCordobas( 50, 30 ), 0.99 );
check( 'cap is strictly less than a whole córdoba', paymentSufficiencyToleranceCordobas( 50, 30 ) < 1, true );
check( 'even an extreme row count never reaches 1', paymentSufficiencyToleranceCordobas( 10000, 100 ), 0.99 );

// ---------------------------------------------------------------------------
// Negative/defensive input: a negative row count must not produce a negative tolerance.
// ---------------------------------------------------------------------------

check( 'negative row count: still the 0.01 floor', paymentSufficiencyToleranceCordobas( -5, 30 ), 0.01 );

echo "\n$passed passed, $failed failed\n";
exit( $failed > 0 ? 1 : 0 );
