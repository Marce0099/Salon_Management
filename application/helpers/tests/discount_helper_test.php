<?php
/**
 * Dependency-free scenario script for the golden-rule functions in application/helpers/discount_helper.php:
 * resolve_single_discount_source() (stage 1: category vs VIP vs birthday) and
 * resolve_coupon_vs_line_discounts() (stage 2: winning line discount vs coupon payment).
 * No PHPUnit/composer is installed in this repo, so this runs standalone via CLI:
 *
 *   php application/helpers/tests/discount_helper_test.php
 *
 * Exits 0 with all scenarios passing, 1 otherwise.
 */

define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/discount_helper.php';

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

// convenience for the stage-2 (coupon vs. line discount) scenarios below, which don't care how a line's
// discount was decided — plain max() is a quick stand-in for "some already-resolved rate" as input;
// production line discounts now come from resolve_single_discount_source() (stage 1, tested separately below)
function candidate_rate( $category, $vip, $birthday_eligible, $birthday_rate ){
	return max( $category, $vip, $birthday_eligible ? $birthday_rate : 0 );
}

// ---------------------------------------------------------------------------
// Baseline: category/VIP/birthday max() picking still flows through untouched
// when there's no coupon in play (resolver's job here is a no-op passthrough)
// ---------------------------------------------------------------------------

// 1. Category only
$candidate = array( 0 => candidate_rate(10, 0, false, 0) );
$undiscounted = array( 0 => 1000 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
check( 'category-only: rate kept', $r['discounts'], array(0 => 10) );
check( 'category-only: coupon_allowed false (no coupon attempted)', $r['coupon_allowed'], false );
check( 'category-only: not neutralized', $r['neutralized_by_coupon'], false );

// 2. Birthday (50) beats category (20) and VIP (15)
$candidate = array( 0 => candidate_rate(20, 15, true, 50) );
$undiscounted = array( 0 => 1000 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
check( 'birthday beats category+vip: rate is 50', $r['discounts'], array(0 => 50) );

// 3. VIP (15) beats category (5)
$candidate = array( 0 => candidate_rate(5, 15, false, 0) );
$undiscounted = array( 0 => 1000 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
check( 'vip beats category: rate is 15', $r['discounts'], array(0 => 15) );

// 4. Category (20) beats VIP (5) — category can win too, not just vip/birthday
$candidate = array( 0 => candidate_rate(20, 5, false, 0) );
$undiscounted = array( 0 => 1000 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
check( 'category beats vip: rate is 20', $r['discounts'], array(0 => 20) );

// ---------------------------------------------------------------------------
// No line discount at all: coupon must behave exactly as before this feature
// ---------------------------------------------------------------------------

$candidate = array( 0 => 0, 1 => 0 );
$undiscounted = array( 0 => 500, 1 => 700 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 300 );
check( 'no discount: coupon always allowed', $r['coupon_allowed'], true );
check( 'no discount: never neutralized', $r['neutralized_by_coupon'], false );
check( 'no discount: total_line_discount_cordobas is 0', $r['total_line_discount_cordobas'], 0.0 );
check( 'no discount: discounts untouched', $r['discounts'], array(0 => 0, 1 => 0) );

// ---------------------------------------------------------------------------
// Coupon amount blank/zero while a discount exists: discount always wins
// ---------------------------------------------------------------------------

$candidate = array( 0 => 15 );
$undiscounted = array( 0 => 1000 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
check( 'zero coupon tender: discount kept', $r['discounts'], array(0 => 15) );
check( 'zero coupon tender: coupon not allowed', $r['coupon_allowed'], false );
check( 'zero coupon tender: not neutralized', $r['neutralized_by_coupon'], false );

// ---------------------------------------------------------------------------
// Multi-line aggregate comparison: birthday ceiling excludes a later line,
// discount value is the SUM across lines, not compared per line
// ---------------------------------------------------------------------------
// line 0: undiscounted 600, birthday-eligible at 50% -> 300 discount value
// line 1: undiscounted 500, birthday ceiling exceeded (0%), category 10% -> 50 discount value
// total_line_discount_cordobas = 350

$candidate = array(
	0 => candidate_rate(0, 0, true, 50),
	1 => candidate_rate(10, 0, false, 50),
);
$undiscounted = array( 0 => 600, 1 => 500 );

// coupon well below total (340 < 350) -> discount wins, coupon blocked
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 340 );
check( 'multi-line, coupon below total: discounts kept', $r['discounts'], array(0 => 50, 1 => 10) );
check( 'multi-line, coupon below total: coupon blocked', $r['coupon_allowed'], false );
check( 'multi-line: total_line_discount_cordobas is 350', $r['total_line_discount_cordobas'], 350.0 );

// exact tie (350 == 350) -> discount must still win, coupon needs to strictly exceed
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 350 );
check( 'multi-line, exact tie: discounts kept', $r['discounts'], array(0 => 50, 1 => 10) );
check( 'multi-line, exact tie: coupon blocked', $r['coupon_allowed'], false );

// coupon clearly above total (400 > 350) -> coupon wins, ALL line discounts blacked out
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 400 );
check( 'multi-line, coupon above total: all discounts zeroed', $r['discounts'], array(0 => 0, 1 => 0) );
check( 'multi-line, coupon above total: coupon allowed', $r['coupon_allowed'], true );
check( 'multi-line, coupon above total: neutralized flag set', $r['neutralized_by_coupon'], true );

// ---------------------------------------------------------------------------
// Epsilon/tie-break boundary: coupon must exceed by MORE than 0.01 to win
// ---------------------------------------------------------------------------

$candidate = array( 0 => 10 );
$undiscounted = array( 0 => 1000 ); // discount value = 100.00

$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 100.01 ); // exactly at the tolerance boundary
check( 'boundary: coupon at +0.01 still blocked (must strictly exceed)', $r['coupon_allowed'], false );

$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 100.02 ); // just past the boundary
check( 'boundary: coupon at +0.02 wins', $r['coupon_allowed'], true );
check( 'boundary: coupon at +0.02 neutralizes discount', $r['discounts'], array(0 => 0) );

// ---------------------------------------------------------------------------
// resolve_single_discount_source(): golden rule stage 1 — category/VIP/birthday compete as three
// whole-sale strategies; only the single winner's candidate discount survives per line.
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Reported bug, reproduced and fixed: birthday-eligible line + a line the ceiling excluded, VIP
// also active with a lower rate. Before this fix, the excluded line fell back to VIP; now it must
// get 0, since birthday (50%) beats VIP (15%) for the whole sale.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => true, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50 );
check( 'bug repro: birthday wins the sale', $r['winning_source'], 'birthday' );
check( 'bug repro: eligible line gets birthday rate, excluded line gets 0 (not VIP)', $r['discounts'], array(0 => 50, 1 => 0) );
check( 'bug repro: winning_rate is 50', $r['winning_rate'], 50 );

// ---------------------------------------------------------------------------
// Category can win outright over both VIP and birthday; each line then keeps its OWN category
// rate (category has no single flat rate, unlike VIP/birthday), VIP/birthday fully suppressed.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 60, 1 => 20 );
$birthday_eligible = array( 0 => false, 1 => false ); // client not having a birthday today
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50 );
check( 'category wins: winning_source is category', $r['winning_source'], 'category' );
check( 'category wins: each line keeps its own rate', $r['discounts'], array(0 => 60, 1 => 20) );

// ---------------------------------------------------------------------------
// VIP wins over a lower category rate: VIP applies uniformly to every service line, even one
// whose own category rate is lower than VIP.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 10, 1 => 5 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 50 );
check( 'vip wins: winning_source is vip', $r['winning_source'], 'vip' );
check( 'vip wins: every line gets the flat VIP rate', $r['discounts'], array(0 => 20, 1 => 20) );

// ---------------------------------------------------------------------------
// Nothing applies: not VIP, no category rates, birthday not eligible anywhere -> all zero.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, false, 15, $birthday_eligible, 50 );
check( 'nothing applies: winning_source is none', $r['winning_source'], 'none' );
check( 'nothing applies: all discounts zero', $r['discounts'], array(0 => 0, 1 => 0) );

// ---------------------------------------------------------------------------
// Birthday is configured with a higher rate than VIP, but the ceiling excludes every line, so
// birthday never actually applies anywhere. It must NOT "win" and discount nothing while
// blocking VIP for no benefit — VIP should win instead.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => false, 1 => false ); // ceiling excluded every line
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50 );
check( 'birthday excluded everywhere: vip wins instead', $r['winning_source'], 'vip' );
check( 'birthday excluded everywhere: both lines get the VIP rate', $r['discounts'], array(0 => 15, 1 => 15) );

// ---------------------------------------------------------------------------
// Exact ties: precedence is birthday > VIP > category.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 20, 1 => 5 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 50 ); // category 20 == vip 20
check( 'tie category vs vip: vip wins', $r['winning_source'], 'vip' );
check( 'tie category vs vip: uniform vip rate applied', $r['discounts'], array(0 => 20, 1 => 20) );

$category_rates = array( 0 => 0 );
$birthday_eligible = array( 0 => true );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 20 ); // vip 20 == birthday 20
check( 'tie vip vs birthday: birthday wins', $r['winning_source'], 'birthday' );
check( 'tie vip vs birthday: birthday rate applied', $r['discounts'], array(0 => 20) );

// ---------------------------------------------------------------------------
// Integration: stage 1's winning discounts feed straight into stage 2 (coupon vs. line discount).
// Reuses the bug-repro scenario: birthday wins with line 0 discounted 50%, line 1 at 0% — the
// combined córdoba value must reflect ONLY that (300), not the old buggy per-line-max total that
// would have also given line 1 its VIP discount (300 + 75 = 375).
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => true, 1 => false );
$stage1 = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50 );

$undiscounted = array( 0 => 600, 1 => 500 );

$stage2 = resolve_coupon_vs_line_discounts( $stage1['discounts'], $undiscounted, 300 ); // exact tie
check( 'integration: combined value is 300, not the old buggy 375', $stage2['total_line_discount_cordobas'], 300.0 );
check( 'integration: exact-tie coupon still blocked', $stage2['coupon_allowed'], false );

$stage2 = resolve_coupon_vs_line_discounts( $stage1['discounts'], $undiscounted, 310 ); // clearly above 300
check( 'integration: coupon above the corrected total wins, neutralizes both lines', $stage2['discounts'], array(0 => 0, 1 => 0) );
check( 'integration: coupon allowed once it exceeds the corrected total', $stage2['coupon_allowed'], true );

// ---------------------------------------------------------------------------

echo "\n$passed passed, $failed failed\n";
exit( $failed > 0 ? 1 : 0 );
