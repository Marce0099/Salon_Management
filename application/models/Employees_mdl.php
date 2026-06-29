<?php if( !defined('BASEPATH') ){ exit(); }

class Employees_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function save( $data ){

		$this->db->insert( 'employees', $data );

		return sprintf('%03d', $this->db->insert_id() );
	}

	public function getEmployee( $id_employee ){

		return $this->db->get_where( 'employees', array( 'id_employee' => $id_employee ) )->row();
	}

	public function edit( $id_employee, $data ){

		$this->db->update('employees',$data,array( 'id_employee' => $id_employee ));
	}

	public function delete($id_employee){

		$this->db->where('id_employee', $id_employee);
		$this->db->update('employees', ["active" => 0]);

		//$this->db->delete( 'employees', array( 'id_employee' => $id_employee ) );
	}

	public function getAll(){

		return $this->db->get('employees')->result();
	}

	public function getSales( $id_employee, $date_start, $date_end ){

		$date_start = date('Y-m-d',strtotime($date_start));
		$date_end = date('Y-m-d',strtotime($date_end));

		$sql = 'select S.* from sales S where id_employee = '.$id_employee.' and
		date(date_time) between "'.$date_start.'" and "'.$date_end.'"';

		$data['sales'] = $this->db->query($sql)->result();

		/*foreach( $data['sales'] as $s ){

			$sql = 'select SP.*,P.barcode,P.name,P.is_service from sale_products SP inner join products P on SP.id_product = P.id_product where SP.id_sale = '.$s->id_sale;

			$products = $this->db->query($sql)->result();

			$sale_total = 0;
			$service_total = 0;

			foreach( $products as $p ){

				if( !$p->is_service )
					$sale_total += $p->price * $p->quantity;
				else
					$service_total += $p->price * $p->quantity;
			}

			$s->sale_total = $sale_total;
			$s->service_total = $service_total;
		}*/

		return $data['sales'];
	}

	public function getSaleDetail( $id_sale ){

		$data = array(
			'totalSale' => 0,
			'commission' => 0
		);

		$sql = "SELECT * FROM `sale_products` WHERE id_sale='{$id_sale}';";
		$products = $this->db->query($sql)->result();

		foreach($products as $p){
			$sold = $p->price * $p->quantity;
			$discounted = $sold - ($sold * ($p->discount/100));

			if(!is_null($p->commission_rate)){
				$cr = floatval($p->commission_rate) / 100;

				if($cr >= 0.5){
					$fcr = $p->my_product ? $cr : 0.3;
					$data['commission'] += $discounted * $fcr;
				}else{
					$data['commission'] += $discounted * $cr;
				}
			}

			$data['totalSale'] += $discounted;

		}

		return $data;
	}
}