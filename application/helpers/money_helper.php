<?php if( !defined('BASEPATH') ){ exit(); }

/*
 * Cordoba-first money helpers.
 *
 * Business rule: prices are designed/shown in cordobas (C$); dollars are only
 * extra information. Legacy columns still hold dollars, so these helpers
 * convert with the live sell rate (dollar_exchange.sell_price) unless a
 * per-document snapshot rate is given.
 */

if( !function_exists('fx_rate') ){

	/** Live sell rate (C$ per US$). Cached per request. */
	function fx_rate(){

		static $rate = null;

		if( $rate === null ){
			$CI =& get_instance();
			$row = $CI->db->select('sell_price')->get('dollar_exchange')->row();
			$rate = ($row && (float) $row->sell_price > 0) ? (float) $row->sell_price : 1.0;
		}

		return $rate;
	}
}

if( !function_exists('usd_to_cor') ){

	function usd_to_cor($usd, $rate = null){

		$rate = $rate ? (float) $rate : fx_rate();

		return round((float) $usd * $rate, 2);
	}
}

if( !function_exists('cor_to_usd') ){

	function cor_to_usd($cor, $rate = null, $decimals = 4){

		$rate = $rate ? (float) $rate : fx_rate();

		return $rate > 0 ? round((float) $cor / $rate, $decimals) : 0.0;
	}
}

if( !function_exists('fmt_cor') ){

	/** "C$ 1,234.50" */
	function fmt_cor($cor, $decimals = 2){

		return 'C$ '.number_format((float) $cor, $decimals);
	}
}

if( !function_exists('fmt_usd') ){

	/** "$1,234.50" */
	function fmt_usd($usd, $decimals = 2){

		return '$'.number_format((float) $usd, $decimals);
	}
}

if( !function_exists('fmt_money_pair') ){

	/**
	 * C$ first, dollars as small secondary info.
	 * $usd = amount in dollars (legacy storage); $rate = snapshot rate or null for live.
	 */
	function fmt_money_pair($usd, $rate = null, $decimals = 2){

		$cor = usd_to_cor($usd, $rate);

		return fmt_cor($cor, $decimals).' <small class="text-muted">('.fmt_usd($usd).')</small>';
	}
}
