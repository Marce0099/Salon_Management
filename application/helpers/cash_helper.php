<?php if( !defined('BASEPATH') ){ exit(); }

	/* Applies córdoba tender against the debt first, then dollar tender (converted at
	   $sell_rate) against whatever remains; any leftover on either side is change in
	   that same currency. */
	function splitCashChange( $debt_cordobas, $cash_cordoba, $cash_dolar, $sell_rate ){

		$debt_remaining = max( $debt_cordobas, 0 );

		$applied_cordoba = min( $cash_cordoba, $debt_remaining );
		$change_cordoba  = $cash_cordoba - $applied_cordoba;
		$debt_remaining -= $applied_cordoba;

		$dollar_equiv         = $cash_dolar * $sell_rate;
		$applied_dollar_equiv = min( $dollar_equiv, $debt_remaining );
		$applied_dollar       = $sell_rate > 0 ? $applied_dollar_equiv / $sell_rate : 0;
		$change_dollar        = $cash_dolar - $applied_dollar;

		return array(
			'applied_cordoba' => $applied_cordoba,
			'applied_dollar'  => $applied_dollar,
			'change_cordoba'  => $change_cordoba,
			'change_dollar'   => $change_dollar,
		);
	}

?>
