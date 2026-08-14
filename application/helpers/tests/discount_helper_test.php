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
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, true );
check( 'bug repro: birthday wins the sale', $r['winning_source'], 'birthday' );
check( 'bug repro: eligible line gets birthday rate, excluded line gets 0 (not VIP)', $r['discounts'], array(0 => 50, 1 => 0) );
check( 'bug repro: winning_rate is 50', $r['winning_rate'], 50 );

// ---------------------------------------------------------------------------
// Category can win outright over both VIP and birthday; each line then keeps its OWN category
// rate (category has no single flat rate, unlike VIP/birthday), VIP/birthday fully suppressed.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 60, 1 => 20 );
$birthday_eligible = array( 0 => false, 1 => false ); // client not having a birthday today
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, false );
check( 'category wins: winning_source is category', $r['winning_source'], 'category' );
check( 'category wins: each line keeps its own rate', $r['discounts'], array(0 => 60, 1 => 20) );

// ---------------------------------------------------------------------------
// VIP wins over a lower category rate: VIP applies uniformly to every service line, even one
// whose own category rate is lower than VIP.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 10, 1 => 5 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 50, false );
check( 'vip wins: winning_source is vip', $r['winning_source'], 'vip' );
check( 'vip wins: every line gets the flat VIP rate', $r['discounts'], array(0 => 20, 1 => 20) );

// ---------------------------------------------------------------------------
// Nothing applies: not VIP, no category rates, birthday not eligible anywhere -> all zero.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, false, 15, $birthday_eligible, 50, false );
check( 'nothing applies: winning_source is none', $r['winning_source'], 'none' );
check( 'nothing applies: all discounts zero', $r['discounts'], array(0 => 0, 1 => 0) );

// ---------------------------------------------------------------------------
// Birthday genuinely doesn't qualify (client not having a birthday today, or the sale's total
// never reached the ceiling minimum) — VIP should win instead. Different from the scenario below:
// here $birthday_qualifies is false, not just "the per-line ceiling walk happened to exclude
// every line while the sale still qualifies."
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, false );
check( 'birthday does not qualify: vip wins instead', $r['winning_source'], 'vip' );
check( 'birthday does not qualify: both lines get the VIP rate', $r['discounts'], array(0 => 15, 1 => 15) );

// ---------------------------------------------------------------------------
// Regression for the reported bug: a single service line's own undiscounted amount already
// exceeds the ceiling max, so the per-line ceiling walk marks it (and everything after)
// ineligible — $birthday_eligible is all false, same shape as the "doesn't qualify" scenario
// above. But the sale DOES qualify ($birthday_qualifies = true: client's birthday, total >=
// ceiling min), so birthday must still win the sale here (its rate beats VIP's), so that
// resolve_birthday_ceiling_writeoff() can produce its flat write-off afterwards instead of the
// bug's silent "discount nothing." VIP must NOT win by default just because no line survived
// the per-line walk.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0 );
$birthday_eligible = array( 0 => false ); // this one line's own amount alone exceeds the ceiling max
$r = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, true );
check( 'bug repro, single oversized line: birthday still wins the sale', $r['winning_source'], 'birthday' );
check( 'bug repro, single oversized line: winning_rate is 50 (birthday, not vip)', $r['winning_rate'], 50 );
check( 'bug repro, single oversized line: line discount is 0 (excluded by the per-line walk)', $r['discounts'], array(0 => 0) );

// ---------------------------------------------------------------------------
// Exact ties: precedence is birthday > VIP > category.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 20, 1 => 5 );
$birthday_eligible = array( 0 => false, 1 => false );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 50, false ); // category 20 == vip 20
check( 'tie category vs vip: vip wins', $r['winning_source'], 'vip' );
check( 'tie category vs vip: uniform vip rate applied', $r['discounts'], array(0 => 20, 1 => 20) );

$category_rates = array( 0 => 0 );
$birthday_eligible = array( 0 => true );
$r = resolve_single_discount_source( $category_rates, true, 20, $birthday_eligible, 20, true ); // vip 20 == birthday 20
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
$stage1 = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, true );

$undiscounted = array( 0 => 600, 1 => 500 );

$stage2 = resolve_coupon_vs_line_discounts( $stage1['discounts'], $undiscounted, 300 ); // exact tie
check( 'integration: combined value is 300, not the old buggy 375', $stage2['total_line_discount_cordobas'], 300.0 );
check( 'integration: exact-tie coupon still blocked', $stage2['coupon_allowed'], false );

$stage2 = resolve_coupon_vs_line_discounts( $stage1['discounts'], $undiscounted, 310 ); // clearly above 300
check( 'integration: coupon above the corrected total wins, neutralizes both lines', $stage2['discounts'], array(0 => 0, 1 => 0) );
check( 'integration: coupon allowed once it exceeds the corrected total', $stage2['coupon_allowed'], true );

// ---------------------------------------------------------------------------
// resolve_birthday_ceiling_writeoff(): once birthday wins the sale AND the undiscounted services total
// goes beyond the configured ceiling maximum, the discount stops being granted per line — it's capped
// at birthday_rate% of the MAXIMUM and applied as a single flat córdoba write-off, with every line's
// candidate discount zeroed. No-op passthrough in every other case.
// ---------------------------------------------------------------------------

// A different source won the sale (category/VIP untouched): must be a complete no-op even though the
// total is well beyond the max — proves this feature doesn't affect other discount logistics.
$discounts = array( 0 => 60, 1 => 20 );
$r = resolve_birthday_ceiling_writeoff( 'category', 50, 5000, 1000, $discounts );
check( 'ceiling writeoff: category won, no-op discounts', $r['discounts'], $discounts );
check( 'ceiling writeoff: category won, no writeoff', $r['writeoff_cordobas'], 0.0 );

$discounts = array( 0 => 20, 1 => 20 );
$r = resolve_birthday_ceiling_writeoff( 'vip', 50, 5000, 1000, $discounts );
check( 'ceiling writeoff: vip won, no-op discounts', $r['discounts'], $discounts );
check( 'ceiling writeoff: vip won, no writeoff', $r['writeoff_cordobas'], 0.0 );

// Birthday won, but the total stays within the ceiling — today's per-line behavior is unchanged,
// including the exact boundary (total == max, inclusive, not "beyond" it).
$discounts = array( 0 => 50, 1 => 0 );
$r = resolve_birthday_ceiling_writeoff( 'birthday', 50, 800, 1000, $discounts );
check( 'ceiling writeoff: birthday won, under max, no-op discounts', $r['discounts'], $discounts );
check( 'ceiling writeoff: birthday won, under max, no writeoff', $r['writeoff_cordobas'], 0.0 );

$discounts = array( 0 => 50, 1 => 50 );
$r = resolve_birthday_ceiling_writeoff( 'birthday', 50, 1000, 1000, $discounts );
check( 'ceiling writeoff: birthday won, exactly at max (inclusive), no-op discounts', $r['discounts'], $discounts );
check( 'ceiling writeoff: birthday won, exactly at max (inclusive), no writeoff', $r['writeoff_cordobas'], 0.0 );

// Birthday won and the total goes beyond the max: every line zeroed (including ones the old per-line
// walk would still have marked eligible), writeoff is rate% of the MAX, not the actual total.
$discounts = array( 0 => 50, 1 => 0, 2 => 0 ); // e.g. line 0 was under the old walk's cutoff, 1/2 excluded
$r = resolve_birthday_ceiling_writeoff( 'birthday', 50, 1500, 1000, $discounts );
check( 'ceiling writeoff: birthday won, over max, all lines zeroed', $r['discounts'], array(0 => 0, 1 => 0, 2 => 0) );
check( 'ceiling writeoff: birthday won, over max, writeoff is rate% of MAX', $r['writeoff_cordobas'], 500.0 );

// ---------------------------------------------------------------------------
// resolve_coupon_vs_line_discounts(): 4th arg ($extra_discount_cordobas), the birthday-ceiling write-off
// ---------------------------------------------------------------------------

// Omitted 4th arg must reproduce prior behavior exactly (backward-compatibility regression check).
$candidate = array( 0 => 15 );
$undiscounted = array( 0 => 1000 );
$r_old = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0 );
$r_new = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0, 0 );
check( 'extra arg: omitted vs explicit 0 give identical discounts', $r_old['discounts'], $r_new['discounts'] );
check( 'extra arg: omitted vs explicit 0 give identical totals', $r_old['total_line_discount_cordobas'], $r_new['total_line_discount_cordobas'] );
check( 'extra arg: omitted defaults extra_discount_cordobas to 0.0', $r_new['extra_discount_cordobas'], 0.0 );

// Write-off alone (no per-line discounts, as production always sends once the ceiling zeroes every
// line), coupon below it: write-off wins, coupon blocked, extra_discount_cordobas kept.
$candidate = array( 0 => 0, 1 => 0 );
$undiscounted = array( 0 => 600, 1 => 900 );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 400, 500 );
check( 'extra arg alone: coupon below writeoff, coupon blocked', $r['coupon_allowed'], false );
check( 'extra arg alone: coupon below writeoff, not neutralized', $r['neutralized_by_coupon'], false );
check( 'extra arg alone: total includes the writeoff', $r['total_line_discount_cordobas'], 500.0 );
check( 'extra arg alone: writeoff kept in output', $r['extra_discount_cordobas'], 500.0 );
check( 'extra arg alone: line discounts stay zero', $r['discounts'], array(0 => 0, 1 => 0) );

// Coupon clearly above the writeoff (> +0.01): neutralized, writeoff zeroed out in the result.
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 500.02, 500 );
check( 'extra arg alone: coupon above writeoff, coupon allowed', $r['coupon_allowed'], true );
check( 'extra arg alone: coupon above writeoff, neutralized', $r['neutralized_by_coupon'], true );
check( 'extra arg alone: coupon above writeoff, extra_discount_cordobas zeroed', $r['extra_discount_cordobas'], 0.0 );

// Exact-tie boundary on the writeoff, mirroring the existing per-line epsilon test: coupon must exceed
// by MORE than 0.01 to neutralize.
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 500.01, 500 );
check( 'extra arg alone: coupon at +0.01 still blocked', $r['coupon_allowed'], false );
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 500.02, 500 );
check( 'extra arg alone: coupon at +0.02 wins', $r['coupon_allowed'], true );

// Defensive completeness: writeoff combined with nonzero per-line discounts (production never actually
// combines them, since the ceiling resolver always zeroes lines first) — totals must still add correctly.
$candidate = array( 0 => 10 );
$undiscounted = array( 0 => 1000 ); // line discount value = 100
$r = resolve_coupon_vs_line_discounts( $candidate, $undiscounted, 0, 50 ); // + 50 extra = 150 total
check( 'extra arg combined: total_line_discount_cordobas adds line + extra', $r['total_line_discount_cordobas'], 150.0 );
check( 'extra arg combined: extra kept when coupon absent', $r['extra_discount_cordobas'], 50.0 );

// ---------------------------------------------------------------------------
// End-to-end integration: resolve_single_discount_source() -> resolve_birthday_ceiling_writeoff() ->
// resolve_coupon_vs_line_discounts(), replicating a realistic over-ceiling Sales.php sale.
// Two service lines, undiscounted 700 + 900 = 1600 total, ceiling max 1000, birthday rate 50%,
// VIP also active at 15% (birthday must still win the sale on rate, same as before this feature).
// Expected writeoff = 50% x 1000 = 500, regardless of the actual 1600 total or how lines split.
// ---------------------------------------------------------------------------

$category_rates = array( 0 => 0, 1 => 0 );
$birthday_eligible = array( 0 => true, 1 => false ); // old per-line walk: line 0 fits under 1000, line 1 doesn't
$stage1 = resolve_single_discount_source( $category_rates, true, 15, $birthday_eligible, 50, true );
check( 'e2e ceiling: birthday still wins the sale', $stage1['winning_source'], 'birthday' );

$undiscounted = array( 0 => 700, 1 => 900 );
$services_total_undiscounted = 700 + 900;

$ceiling = resolve_birthday_ceiling_writeoff( $stage1['winning_source'], 50, $services_total_undiscounted, 1000, $stage1['discounts'] );
check( 'e2e ceiling: both lines zeroed', $ceiling['discounts'], array(0 => 0, 1 => 0) );
check( 'e2e ceiling: writeoff is 500 (50% of the 1000 max)', $ceiling['writeoff_cordobas'], 500.0 );

// (a) no coupon: writeoff applies untouched
$stage2 = resolve_coupon_vs_line_discounts( $ceiling['discounts'], $undiscounted, 0, $ceiling['writeoff_cordobas'] );
check( 'e2e ceiling, no coupon: writeoff applied', $stage2['extra_discount_cordobas'], 500.0 );
check( 'e2e ceiling, no coupon: coupon not allowed (no discounted service anyway, none attempted)', $stage2['coupon_allowed'], false );

// (b) small coupon (below the 500 writeoff): writeoff still wins, coupon blocked
$stage2 = resolve_coupon_vs_line_discounts( $ceiling['discounts'], $undiscounted, 300, $ceiling['writeoff_cordobas'] );
check( 'e2e ceiling, small coupon: writeoff still wins', $stage2['extra_discount_cordobas'], 500.0 );
check( 'e2e ceiling, small coupon: coupon blocked', $stage2['coupon_allowed'], false );

// (c) large coupon (clearly above the 500 writeoff): writeoff neutralized, coupon allowed
$stage2 = resolve_coupon_vs_line_discounts( $ceiling['discounts'], $undiscounted, 600, $ceiling['writeoff_cordobas'] );
check( 'e2e ceiling, large coupon: writeoff neutralized', $stage2['extra_discount_cordobas'], 0.0 );
check( 'e2e ceiling, large coupon: coupon allowed', $stage2['coupon_allowed'], true );
check( 'e2e ceiling, large coupon: line discounts still zero', $stage2['discounts'], array(0 => 0, 1 => 0) );

// ---------------------------------------------------------------------------

echo "\n$passed passed, $failed failed\n";
exit( $failed > 0 ? 1 : 0 );
