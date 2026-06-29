<?php if( !defined('BASEPATH') ){ exit(); }

class Fix extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function fix(){

		$sales = array(
					271,
					270,
					269,
					268,
					267,
					266,
					265,
					264,
					263,
					262,
					261,
					260,
					259,
					258,
					257,
					256,
					255,
					254,
					253,
					252,
					250,
					249,
					248,
					247,
					246,
					245,
					243,
					242,
					241,
					234,
					232);

		echo '<pre>';

		foreach( $sales as $s ){

			$sale = $this->db->get_where('sales',array('id_sale'=>$s))->row();
			$details = $this->db->get_where('sale_products',array('id_sale'=>$s))->result();

			$p = $this->db->get_where('products',array('id_product'=>$details[0]->id_product))->row();
			$provider = $this->db->get_where('providers',array('id_provider'=>$p->id_provider))->row();

			foreach( $details as $d ){

				$product = $this->db->get_where('products',array('id_product'=>$d->id_product))->row();

				if( $provider->id_provider != $product->id_provider ){
					echo 'PROVEEDOR DIFERENTE';
					exit();
				}
			}

			echo 'Proveedor venta '.$sale->id_sale.': '.$provider->provider.'<br>';
		}
	}

	public function returns(){

		$sales = array(
					271,
					270,
					269,
					268,
					267,
					266,
					265,
					264,
					263,
					262,
					261,
					260,
					259,
					258,
					257,
					256,
					255,
					254,
					253,
					252,
					250,
					249,
					248,
					247,
					246,
					245,
					243,
					242,
					241,
					234,
					232);

		echo '<pre>';

		$returns = array();

		foreach( $sales as $s ){

			$sale = $this->db->get_where('sales',array('id_sale'=>$s))->row();
			$details = $this->db->get_where('sale_products',array('id_sale'=>$s))->result();

			$p = $this->db->get_where('products',array('id_product'=>$details[0]->id_product))->row();
			$provider = $this->db->get_where('providers',array('id_provider'=>$p->id_provider))->row();

			$r['id_provider'] = $provider->id_provider;
			$r['date_time'] = $sale->date_time;
			$r['products'] = array();

			foreach( $details as $d ){

				$product = $this->db->get_where('products',array('id_product'=>$d->id_product))->row();
				$pr['id_product'] = $product->id_product;
				$pr['price_cost'] = $product->price_cost;
				$pr['price_sell'] = $product->price_sell;
				$pr['quantity'] = $d->quantity;
				array_push($r['products'], $pr);
			}

			array_push($returns, $r);
		}

		foreach( $returns as $r ){

			$rprovider['id_provider'] = $r['id_provider'];
			$rprovider['date_time'] = $r['date_time'];

			$this->db->insert('returns',$rprovider);
			$id_return = $this->db->insert_id();

			foreach( $r['products'] as &$p )
				$p['id_return'] = $id_return;

			$this->db->insert_batch('returns_products',$r['products']);

			print_r($rprovider);
			print_r($r['products']);
			echo '-----------------------------------<br><br>';
		}
	}

	public function delete(){

		$sales = array(
					271,
					270,
					269,
					268,
					267,
					266,
					265,
					264,
					263,
					262,
					261,
					260,
					259,
					258,
					257,
					256,
					255,
					254,
					253,
					252,
					250,
					249,
					248,
					247,
					246,
					245,
					243,
					242,
					241,
					234,
					232);

		foreach ($sales as $s) {
			$this->db->delete('sales',array('id_sale' => $s));
		}
	}
}