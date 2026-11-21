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

	/** C$ -> US$ (2 decimals) at the live rate, or at a document's snapshot rate. Dollars are never stored, only derived. */
	function cor_to_usd($cor, $rate = null, $decimals = 2){

		$rate = $rate ? (float) $rate : fx_rate();

		return $rate > 0 ? round((float) $cor / $rate, $decimals) : 0.0;
	}
}

if( !function_exists('show_usd') ){

	/** Dollars are only visible under /cash/* and /sales/register; everywhere else amounts are C$ only. */
	function show_usd(){

		$CI =& get_instance();
		$first = strtolower((string) $CI->uri->segment(1));
		$second = strtolower((string) $CI->uri->segment(2));

		return $first === 'cash' || ($first === 'sales' && $second === 'register');
	}
}

if( !function_exists('fmt_cor') ){

	/** "C$ 1,234.50" */
	function fmt_cor($cor, $decimals = 2){

		return 'C$&nbsp;'.number_format((float) $cor, $decimals);
	}
}

if( !function_exists('fmt_usd') ){

	/** "$1,234.50" */
	function fmt_usd($usd, $decimals = 2){

		return '$'.number_format((float) $usd, $decimals);
	}
}

if( !function_exists('fmt_cor_pair') ){

	/**
	 * Stored C$ amount; the dollar equivalent (live rate, or $rate for a document snapshot) is appended
	 * only where dollars are allowed (see show_usd). NULL -> dash.
	 */
	function fmt_cor_pair($cor, $decimals = 2, $rate = null){

		if( $cor === NULL || $cor === '' ){ return '—'; }

		$out = fmt_cor($cor, $decimals);

		if( show_usd() ){ $out .= ' <small class="money-usd">('.fmt_usd(cor_to_usd($cor, $rate), 2).')</small>'; }

		return $out;
	}
}

if( !function_exists('fmt_cor_unit') ){

	/** Per-unit cost: C$ to the cent, or up to 4 decimals when the cost really has them (the only money allowed more than 2 decimals). */
	function fmt_cor_unit($cor){

		if( $cor === NULL || $cor === '' ){ return '—'; }

		return fmt_cor_pair($cor, round((float) $cor, 2) == round((float) $cor, 4) ? 2 : 4);
	}
}

if( !function_exists('fmt_money_pair') ){

	/** Same as fmt_cor_pair with the legacy argument order: ($cor, $rate, $decimals). */
	function fmt_money_pair($cor, $rate = null, $decimals = 2){

		return fmt_cor_pair($cor, $decimals, $rate);
	}
}

if( !function_exists('fmt_cor_usd') ){

	/** Legacy two-argument form (the dollar value is no longer stored, so it is ignored). */
	function fmt_cor_usd($cor, $ignored = NULL, $decimals = 2){

		return fmt_cor_pair($cor, $decimals);
	}
}

if( !function_exists('fmt_plain') ){

	/** Plain number for an <input value>: at least $min decimals, up to $max, no thousands separator ("185.5000" -> "185.50"). */
	function fmt_plain($n, $min = 2, $max = 2){

		$s = number_format((float) $n, $max, '.', '');
		$s = rtrim($s, '0');
		$dot = strpos($s, '.');
		$have = ($dot === false) ? 0 : strlen($s) - $dot - 1;
		if( $have < $min ){ $s = ($dot === false ? $s.'.' : $s).str_repeat('0', $min - $have); }

		return $s;
	}
}
