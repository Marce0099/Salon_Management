<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Golden rule: only one discount effect survives per sale. Two stages, both pure
 * functions with no framework/DB dependency, so both can be exercised by a plain
 * PHP script without bootstrapping CodeIgniter (see application/helpers/tests/):
 *
 *  1. resolve_single_discount_source() — category, VIP, and birthday discounts
 *     compete as three whole-sale strategies; whichever is worth the most wins
 *     and is applied everywhere it's eligible, every other line gets a flat 0.
 *  2. resolve_coupon_vs_line_discounts() — the winning line-discount total from
 *     stage 1 then competes against a coupon payment, comparing córdoba value
 *     and neutralizing whichever is smaller.
 */

/**
 * Stage 1 of the golden rule: picks a single winning discount source for the whole
 * sale among category/VIP/birthday, instead of letting each service line pick its
 * own max() independently (which let two different clients-wide sources — e.g.
 * birthday on one line, VIP on another — both show up on the same invoice).
 *
 * Category has no single flat rate (it varies per service), so its "representative"
 * rate for the three-way comparison is the highest category rate present among the
 * sale's service lines; once category wins, each line still uses its own rate. VIP
 * and birthday are flat settings-level rates. A source only enters the comparison
 * if it actually applies to at least one line (birthday doesn't "win" by having the
 * higher configured rate if the ceiling excludes every line — that would discount
 * nothing while blocking VIP for no benefit). On an exact tie between representative
 * rates, precedence is birthday > VIP > category.
 *
 * @param array $category_rates    service-line index => category discount percentage (0-100; 0 if none)
 * @param bool  $is_vip            whether the sale's client is VIP
 * @param float $vip_rate          configured VIP discount percentage
 * @param array $birthday_eligible service-line index => bool, ceiling-eligibility (same keys as $category_rates)
 * @param float $birthday_rate     configured birthday discount percentage
 * @return array{ discounts: array, winning_source: string, winning_rate: float }
 */
function resolve_single_discount_source( $category_rates, $is_vip, $vip_rate, $birthday_eligible, $birthday_rate ) {

	$category_representative = 0;
	foreach( $category_rates as $rate ){
		if( $rate > $category_representative ) $category_representative = $rate;
	}

	$vip_representative = $is_vip ? $vip_rate : 0;

	$birthday_applies = false;
	foreach( $birthday_eligible as $eligible ){
		if( $eligible ){ $birthday_applies = true; break; }
	}
	$birthday_representative = $birthday_applies ? $birthday_rate : 0;

	// checked in ascending precedence (category, then VIP, then birthday) with >=, so a later
	// source overtakes an equal representative — gives birthday > VIP > category on exact ties
	$winning_source = 'none';
	$winning_rate = 0;

	if( $category_representative > $winning_rate ){
		$winning_source = 'category';
		$winning_rate = $category_representative;
	}
	if( $vip_representative > 0 && $vip_representative >= $winning_rate ){
		$winning_source = 'vip';
		$winning_rate = $vip_representative;
	}
	if( $birthday_representative > 0 && $birthday_representative >= $winning_rate ){
		$winning_source = 'birthday';
		$winning_rate = $birthday_representative;
	}

	$discounts = array();

	foreach( $category_rates as $i => $rate ){

		switch( $winning_source ){
			case 'category':
				$discounts[$i] = $rate;
				break;
			case 'vip':
				$discounts[$i] = $vip_representative;
				break;
			case 'birthday':
				$discounts[$i] = !empty($birthday_eligible[$i]) ? $birthday_representative : 0;
				break;
			default:
				$discounts[$i] = 0;
		}
	}

	return array(
		'discounts' => $discounts,
		'winning_source' => $winning_source,
		'winning_rate' => $winning_rate,
	);
}

/**
 * Stage 2 of the golden rule: the winning line discount total from
 * resolve_single_discount_source() competes against a coupon payment, comparing
 * córdoba value and neutralizing whichever is smaller.
 *
 * @param array $candidate_discounts   service-line index => discount percentage (0-100)
 * @param array $undiscounted_cordobas service-line index => undiscounted córdoba subtotal
 * @param float $coupon_tender_total   córdoba amount tendered via the sale's coupon payment (0 if none)
 * @return array{
 *   discounts: array,
 *   coupon_allowed: bool,
 *   neutralized_by_coupon: bool,
 *   total_line_discount_cordobas: float
 * }
 */
function resolve_coupon_vs_line_discounts( $candidate_discounts, $undiscounted_cordobas, $coupon_tender_total ) {

	$total_line_discount_cordobas = 0;

	foreach( $candidate_discounts as $i => $rate ){
		$total_line_discount_cordobas += $rate / 100 * $undiscounted_cordobas[$i];
	}

	if( $total_line_discount_cordobas <= 0 ){

		return array(
			'discounts' => $candidate_discounts,
			'coupon_allowed' => true,
			'neutralized_by_coupon' => false,
			'total_line_discount_cordobas' => 0.0,
		);
	}

	if( $coupon_tender_total > $total_line_discount_cordobas + 0.01 ){

		$zeroed = array_fill_keys( array_keys($candidate_discounts), 0 );

		return array(
			'discounts' => $zeroed,
			'coupon_allowed' => true,
			'neutralized_by_coupon' => true,
			'total_line_discount_cordobas' => $total_line_discount_cordobas,
		);
	}

	return array(
		'discounts' => $candidate_discounts,
		'coupon_allowed' => false,
		'neutralized_by_coupon' => false,
		'total_line_discount_cordobas' => $total_line_discount_cordobas,
	);
}
