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

	/* Cash tendered (córdoba + dollar-at-$sell_rate), converted to a single córdoba-equivalent
	   figure, minus whatever debt is still owed after non-cash payments are applied. This is the
	   total change owed to the customer, which the cashier may then hand back split across either
	   currency (see Sales::register()) rather than strictly the currency it was tendered in. */
	function cashOverpaymentCordobas( $debt_after_other_payments, $cash_cordoba, $cash_dolar, $sell_rate ){

		$tendered_cordoba_equiv = $cash_cordoba + ( $cash_dolar * $sell_rate );
		$overpayment = $tendered_cordoba_equiv - max( $debt_after_other_payments, 0 );

		return max( $overpayment, 0 );
	}

	/* How much of a sale's cash tender was actually kept in the drawer (tendered minus whatever
	   was handed back as change). Prefers the cashier's actually-recorded change split
	   ($change_cordoba_given/$change_dollar_given, persisted by Sales::register() since the
	   change_*_given columns were added to sale_payments); falls back to splitCashChange()'s
	   tendered-currency-first estimate for older sales recorded before those columns existed. */
	function resolveCashApplied( $debt_cordobas, $cash_cordoba, $cash_dolar, $sell_rate, $change_cordoba_given = NULL, $change_dollar_given = NULL ){

		if( $change_cordoba_given !== NULL || $change_dollar_given !== NULL ){

			return array(
				'applied_cordoba' => $cash_cordoba - floatval($change_cordoba_given),
				'applied_dollar'  => $cash_dolar - floatval($change_dollar_given),
			);
		}

		$split = splitCashChange( $debt_cordobas, $cash_cordoba, $cash_dolar, $sell_rate );

		return array(
			'applied_cordoba' => $split['applied_cordoba'],
			'applied_dollar'  => $split['applied_dollar'],
		);
	}

?>
