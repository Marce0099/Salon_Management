<?php if( !defined('BASEPATH') ) exit();
	
	class Invoices_mdl extends CI_Model{

		public function __construct(){

			parent::__construct();
		}

		public function getInvoices(){

			$this->db->order_by('id_invoice','desc');
			return $this->db->get('invoices')->result();
		}

		public function saveInvoice( $data ){

			// a concurrent duplicate invoice_no+id_provider is rejected by the
			// uniq_invoice_no_provider constraint; on this PHP/PDO version (default
			// PDO::ERRMODE_EXCEPTION since PHP 8.1) that surfaces as a normal catchable
			// PDOException, which Invoices_mdl::receive() catches (inside its transaction) and handles
			$this->db->insert( 'invoices', $data );

			return $this->db->insert_id();
		}

		/**
		 * Saves a whole receipt atomically: header, lines, stock and price updates.
		 * $lines come already validated from Invoices::validatedLines(). Returns array(ok, message, id_invoice).
		 * - a named lock per invoice number serializes concurrent saves, so the duplicate check (also for receipts
		 *   without provider, which the unique index cannot guard) cannot be raced by a double click or a retry;
		 * - product rows are locked in ascending id (same order as Products_mdl::takeStock) and must still exist;
		 * - deadlocks / lock timeouts are retried up to 3 times, any other failure is logged and rolled back.
		 */
		public function receive( $invoice, $lines ){

			// one lock per number whatever the provider: a receipt without provider is checked against ALL providers
			$lock_name = 'invoice_no_'.md5($invoice['invoice_no']);

			$ids = array();
			foreach( $lines as $l ){ $ids[(int)$l['id_product']] = TRUE; }
			$ids = array_keys($ids);
			sort($ids);

			for( $try = 0; $try < 3; $try++ ){

				$locked = (int)$this->db->query('SELECT GET_LOCK(?, 10) AS l', array($lock_name))->row('l');

				if( $locked !== 1 ){

					return array(FALSE, 'Hay mucha actividad en este momento, intente de nuevo', 0);
				}

				try{

					$this->db->trans_begin();

					if( $this->invoiceNumberExists($invoice['invoice_no'], NULL, $invoice['id_provider']) ){

						$this->db->trans_rollback();
						$this->releaseLock($lock_name);
						return array(FALSE, 'El numero de factura ingresado ya existe', 0);
					}

					$rows = $this->db->query(
						'SELECT id_product, name, stock, price_cost, price_sell FROM products WHERE active = 1 AND id_product IN ('.implode(',', array_map('intval', $ids)).') ORDER BY id_product FOR UPDATE'
					)->result();

					if( count($rows) !== count($ids) ){

						$this->db->trans_rollback();
						$this->releaseLock($lock_name);
						return array(FALSE, 'Uno de los productos ya no existe, recargue la página', 0);
					}

					$old = array();
					foreach( $rows as $r ){ $old[(int)$r->id_product] = $r; }

					$this->load->model('cost_history_mdl');

					$id_invoice = $this->saveInvoice($invoice);
					$audit_lines = array();

					if( !$id_invoice ){

						throw new Exception('Invoice header was not inserted');
					}

					foreach( $lines as $line ){

						$this->saveInvoiceDetails(array(
							'id_invoice' => $id_invoice,
							'id_product' => $line['id_product'],
							'quantity' => $line['quantity'],
							'price_cost' => $line['price_cost'],
							'price_sell' => $line['price_sell'],
							'id_pack' => isset($line['id_pack']) ? $line['id_pack'] : NULL,
							'pack_name' => isset($line['pack_name']) ? $line['pack_name'] : NULL,
							'pack_qty' => isset($line['pack_qty']) ? $line['pack_qty'] : NULL,
							'pack_units' => isset($line['pack_units']) ? $line['pack_units'] : NULL
						));

						if( $this->db->affected_rows() < 1 ){

							throw new Exception('Invoice line was not inserted for product '.$line['id_product']);
						}

						$id_invoice_product = $this->db->insert_id();
						$prev = $old[(int)$line['id_product']];

						$audit_lines[] = array(
							'id_product' => (int) $line['id_product'], 'name' => $prev->name, 'units' => (int) $line['quantity'],
							'pack' => isset($line['pack_name']) ? $line['pack_name'] : NULL, 'pack_qty' => isset($line['pack_qty']) ? $line['pack_qty'] : NULL,
							'cost_old' => $prev->price_cost, 'cost_new' => $line['price_cost'], 'sell_old' => $prev->price_sell, 'sell_new' => $line['price_sell'],
							'stock_before' => $line['is_service'] ? NULL : (int) $prev->stock, 'stock_after' => $line['is_service'] ? NULL : (int) $prev->stock + (int) $line['quantity']
						);
						if( !$line['is_service'] ){ $prev->stock = (int) $prev->stock + (int) $line['quantity']; }

						$this->cost_history_mdl->log( $line['id_product'], 'purchase', $prev->price_cost, $line['price_cost'], array(
							'id_invoice' => $id_invoice,
							'id_invoice_product' => $id_invoice_product,
							'id_provider' => isset($invoice['id_provider']) ? $invoice['id_provider'] : NULL,
							'quantity' => $line['quantity'],
							'old_sell' => $prev->price_sell,
							'new_sell' => $line['price_sell'],
							'created_at' => isset($invoice['date_time']) ? $invoice['date_time'] : NULL
						));

						// a later line of the same product in this receipt sees this line as its previous one
						if( $line['price_cost'] !== '' && $line['price_cost'] !== NULL ){ $prev->price_cost = $line['price_cost']; }
						$prev->price_sell = $line['price_sell'];

						$changed = $this->updateStockPrice( $line['id_product'], $line['quantity'], $line['price_sell'], $line['price_cost'], $line['is_service'] );

						// a stocked product always changes (stock + qty); services may legitimately keep the same prices
						if( !$line['is_service'] && $changed < 1 ){

							throw new Exception('Stock was not updated for product '.$line['id_product']);
						}
					}

					if( $this->db->trans_status() === FALSE ){

						throw new Exception('Transaction status is FALSE');
					}

					$this->audit->log('invoice.receive', 'invoice', $id_invoice, array(
						'summary' => 'Compra registrada: factura '.$invoice['invoice_no'].' ('.count($audit_lines).' linea(s))',
						'after' => array('invoice_no' => $invoice['invoice_no'], 'id_provider' => isset($invoice['id_provider']) ? $invoice['id_provider'] : NULL, 'date_time' => isset($invoice['date_time']) ? $invoice['date_time'] : NULL, 'lines' => $audit_lines)
					));

					$this->db->trans_commit();
					$this->releaseLock($lock_name);

					return array(TRUE, '', (int)$id_invoice);
				}
				catch( Exception $e ){

					log_message('error', 'Invoice receive failed (try '.($try + 1).'): '.$e->getMessage());

					try{ $this->db->trans_rollback(); }catch( Exception $e2 ){ /* server already rolled it back */ }

					$this->releaseLock($lock_name);

					usleep(mt_rand(20000, 90000));
				}
			}

			return array(FALSE, 'No se ha podido registrar la factura, intente de nuevo', 0);
		}

		private function releaseLock( $name ){

			try{ $this->db->query('SELECT RELEASE_LOCK(?)', array($name)); }catch( Exception $e ){ log_message('error', 'Could not release lock '.$name.': '.$e->getMessage()); }
		}

		public function invoiceIDExists( $id_invoice ){

			$sql = 'select I.*,P.provider from invoices I left outer join providers P
			on I.id_provider = P.id_provider where I.id_invoice = ?';

			return $this->db->query($sql, array((int)$id_invoice))->row();
		}

		public function invoiceNumberExists( $invoice_no, $id_invoice = NULL, $id_provider = NULL ){

			$sql = 'select * from invoices where invoice_no = ?';
			$binds = array((string)$invoice_no);

			if( $id_invoice ){

				$sql .= ' and id_invoice != ?';
				$binds[] = (int)$id_invoice;
			}
			if( $id_provider ){

				$sql .= ' and id_provider = ?';
				$binds[] = (int)$id_provider;
			}
			return $this->db->query( $sql, $binds )->row();
		}

		public function saveInvoiceDetails( $data ){

			return $this->db->insert( 'invoice_products', $data );
		}

		// Arguments are validated by Invoices::save(); still bound, never concatenated.
		// Services carry no stock, so only their prices are updated.
		public function updateStockPrice( $id_product, $quantity, $price_sell, $price_cost, $is_service = 0 ){

			$set = array();
			$binds = array();

			if( !$is_service ){

				$set[] = 'stock = stock + ?';
				$binds[] = (int)$quantity;
			}
			if( $price_cost !== '' && $price_cost !== NULL ){

				$set[] = 'price_cost = ?';
				$binds[] = $price_cost;
			}
			$set[] = 'price_sell = ?';
			$binds[] = $price_sell;
			$binds[] = (int)$id_product;

			$this->db->query('update products set '.implode(', ',$set).' where id_product = ?', $binds);

			return $this->db->affected_rows();
		}

		public function getProductsInvoice( $id_invoice ){

			$sql = 'select P.name,P.barcode,IP.* from products P inner join invoice_products IP
			on P.id_product = IP.id_product where IP.id_invoice = ?';

			return $this->db->query($sql, array((int)$id_invoice))->result();
		}

		/*public function updateEditStockPrice( $id_invoice, $id_product, $quantity, $price ){

			$ip = $this->db->get_where( 'invoice_products',
				array( 'id_invoice' => $id_invoice, 'id_product' => $id_product )
				)->row();

			if( $ip ){
				if( $quantity > $ip->quantity ){

					$stock_sum = $quantity - $ip->quantity;
					$this->db->query('update products set stock = stock+'.$stock_sum.' where id_product = '.$id_product);
				}
				else if( $quantity < $ip->quantity ){

					$stock_rest = $ip->quantity - $quantity;
					$this->db->query('update products set stock = stock-'.$stock_rest.' where id_product = '.$id_product);
				}
			}
			else{

				$sql = 'update products set stock=stock+'.$quantity.' where
				id_product = '.$id_product;

				$this->db->query($sql);
			}

			$this->db->update('products',array('price'=>$price),array('id_product'=>$id_product));
		}*/
	}

?>