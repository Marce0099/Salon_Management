<?php if( !defined('BASEPATH') ){ exit(); }

class Loyalty_points_mdl extends CI_Model{

	const ACCRUAL_RATE = 0.02;

	public function __construct(){

		parent::__construct();
	}

	public function getSettings(){

		return $this->db->get('loyalty_settings')->row();
	}

	public function saveSettings( $data ){

		$this->db->update('loyalty_settings', $data);
	}

	public function getClientBalance( $id_client ){

		$row = $this->db->select('balance_after')
			->where('id_client', $id_client)
			->order_by('id_loyalty_movement', 'desc')
			->limit(1)
			->get('loyalty_point_movements')
			->row();

		return $row ? floatval($row->balance_after) : 0.0;
	}

	// Writes the redemption (if any) and accrual movements for a just-registered sale, inside the
	// caller's existing DB transaction. $accrual_basis_cordobas must already be the post-loyalty-discount
	// services amount (computed by the caller) so points are earned on what the client actually paid for.
	public function recordSaleMovements( $id_sale, $id_client, $id_admin, $claim_tier, $tier_threshold, $tier_rate, $balance_before, $accrual_basis_cordobas ){

		$settings = $this->getSettings();

		if( !$settings || !$settings->enabled ){
			return;
		}

		$balance = $balance_before;

		if( $claim_tier >= 1 && $tier_threshold > 0 ){

			$balance -= $tier_threshold;

			$this->db->insert('loyalty_point_movements', array(
				'id_client' => $id_client,
				'id_sale' => $id_sale,
				'movement_type' => 'redemption',
				'points' => -$tier_threshold,
				'tier_applied' => $claim_tier,
				'discount_rate_applied' => $tier_rate,
				'services_total_cordobas' => NULL,
				'balance_after' => $balance,
				'id_admin' => $id_admin,
			));
		}

		$earned = round( $accrual_basis_cordobas * self::ACCRUAL_RATE, 2 );
		$accrual = max( 0, min( $earned, $settings->points_ceiling - $balance ) );

		if( $accrual > 0 ){

			$balance += $accrual;

			$this->db->insert('loyalty_point_movements', array(
				'id_client' => $id_client,
				'id_sale' => $id_sale,
				'movement_type' => 'accrual',
				'points' => $accrual,
				'tier_applied' => NULL,
				'discount_rate_applied' => NULL,
				'services_total_cordobas' => $accrual_basis_cordobas,
				'balance_after' => $balance,
				'id_admin' => $id_admin,
			));
		}
	}
}
