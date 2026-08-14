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

	/* Payment-sufficiency tolerance (córdobas): how much a sale's tendered total may fall short
	   of (or exceed) the invoice total and still be accepted as "paid in full". A córdoba amount
	   typed directly is exact to 0.01; a dollar amount is only exact to 0.01 USD, which after
	   *sell_rate is coarser than a córdoba cent. The "Pendiente"/"remaining owed" figure shown to
	   the cashier in dollars is itself a rounded quotient (see form_sales.php's updateDueAmount()),
	   so re-typing that rounded figure back and reconverting to córdobas can miss the exact
	   córdoba total by up to half a dollar-cent's worth of córdobas, per independently-typed
	   dollar-denominated payment row. Scales with $num_dollar_rows so multiple dollar rows (e.g.
	   a split cash tender plus a dollar POS row) are each allowed their own rounding slack, but is
	   capped well under 1 córdoba so it can never be used to forgive a real, whole-unit shortfall
	   (mirrors the change_rounding_cordoba policy in Sales::register(), which rejects any
	   abs(...) >= 1). Must be mirrored exactly (same constants) in form_sales.php's
	   paymentSufficiencyTolerance(), since JS and PHP can't share this function. */
	function paymentSufficiencyToleranceCordobas( $num_dollar_rows, $sell_rate ){

		$tolerance = max( 0, $num_dollar_rows ) * 0.005 * $sell_rate;

		return max( 0.01, min( $tolerance, 0.99 ) );
	}

	/* Ratio of a sale's real (bank/coupon-absorption-netted) córdoba revenue to its gross,
	   post-manual-discount invoice total. Mirrors the pos/cupon netting math in
	   Cash_mdl::getCashMovements() exactly, so the two can't diverge in intent. Cash and
	   transfer payments never reduce revenue relative to the invoice, so only 'pos' and
	   'cupon' rows contribute a loss. */
	function saleRealProfitRatio( $sale_payments, $sell_rate, $gross_cordobas ){

		if( $gross_cordobas <= 0 )
			return 1;

		$loss = 0;

		foreach( $sale_payments as $spa ){

			if( $spa->payment_method === 'pos' ){

				$cor = floatval($spa->cordoba ?? 0);
				$dol = floatval($spa->dolar ?? 0) * $sell_rate;
				$tendered = $cor + $dol;
				$tax_rate = floatval($spa->tax_rate ?? 0);

				$loss += $tendered * ( $tax_rate / 100 );

			}else if( $spa->payment_method === 'cupon' ){

				$cor = floatval($spa->cordoba ?? 0);
				$discount = floatval($spa->cupon_discount_rate ?? 0);

				$loss += $cor * ( $discount / 100 );
			}
			// cash / transfer: full amount recognized, no netting — intentionally ignored
		}

		$ratio = ( $gross_cordobas - $loss ) / $gross_cordobas;

		if( $ratio < 0 ) $ratio = 0;
		if( $ratio > 1 ) $ratio = 1;

		return $ratio;
	}

?>
