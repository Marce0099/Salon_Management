<?php if( !defined('BASEPATH') ){ exit(); }

class Appointments extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('appointments_mdl');
		$this->load->model('clients_mdl');
		$this->load->model('employees_mdl');
		$this->load->model('cash_mdl');
		$this->load->model('bank_account_mdl');
	}

	public function index(){

		$this->footer_data = load_data($this->session);

		$this->renderPanel('appointments');
	}

	public function counts(){

		$start = $this->input->get('start');
		$end = $this->input->get('end');

		$rows = $this->appointments_mdl->getCountsForRange( $start, $end );

		$counts = array();

		foreach( $rows as $r ){

			$counts[ $r->appt_date ] = (int) $r->total;
		}

		echo json_encode( $counts );
	}

	public function schedule( $date = NULL ){

		if( empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date) ){

			$date = date('Y-m-d');
		}

		$view_data['date'] = $date;
		$view_data['prev_date'] = date( 'Y-m-d', strtotime($date.' -1 day') );
		$view_data['next_date'] = date( 'Y-m-d', strtotime($date.' +1 day') );
		$services = $this->appointments_mdl->getActiveServices();
		$view_data['service_categories'] = $this->groupServicesByCategory( $services );
		$view_data['has_services'] = !empty( $services );
		$view_data['is_admin'] = $this->is_admin();

		$exchange = $this->db->get('dollar_exchange')->row();
		$view_data['sell_rate'] = $exchange->sell_price;
		$view_data['accounts'] = $this->bank_account_mdl->getAccounts();

		$employees = $this->employees_mdl->getActiveEmployees();
		$appointments = $this->appointments_mdl->getForDay( $date );

		$grid_origin = strtotime( $date . ' 01:00:00' );

		$view_data['hours'] = $this->buildScheduleHours( $grid_origin );
		$view_data['columns'] = $this->buildScheduleColumns( $employees, $appointments, $grid_origin );

		$view_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $view_data['tk']);

		$this->content_data = $view_data;
		$this->footer_data = load_data($this->session);

		$this->renderPanel('appointments_schedule');
	}

	private function groupServicesByCategory( $services ){

		$groups = array();

		foreach( $services as $s ){

			$key = $s->id_service_category ? (int) $s->id_service_category : 0;

			if( !isset($groups[$key]) ){
				$groups[$key] = array(
					'id_service_category' => $key,
					'name' => $s->id_service_category ? $s->name_category : 'Sin categoría',
					'services' => array(),
				);
			}

			$groups[$key]['services'][] = $s;
		}

		// uncategorized ("Sin categoría", key 0) last
		uksort( $groups, function( $a, $b ){
			if( $a === 0 ){ return 1; }
			if( $b === 0 ){ return -1; }
			return 0;
		});

		return array_values( $groups );
	}

	private function buildScheduleHours( $grid_origin ){

		$hours = array();

		for( $i = 0; $i < 24; $i++ ){

			$hours[] = array(
				'label' => date( 'H:i', $grid_origin + ($i * 3600) ),
				'start' => date( 'Y-m-d H:i:s', $grid_origin + ($i * 3600) ),
				'end'   => date( 'Y-m-d H:i:s', $grid_origin + (($i + 1) * 3600) ),
			);
		}

		return $hours;
	}

	private function computeBucket( $appointment, $grid_origin ){

		$start_ts = strtotime( $appointment->start_datetime );
		$end_ts = strtotime( $appointment->end_datetime );

		$offset_start = ( $start_ts - $grid_origin ) / 3600;
		$offset_end = ( $end_ts - $grid_origin ) / 3600;

		$first_hour = (int) floor( $offset_start );
		$last_hour = (int) ceil( $offset_end ) - 1;

		$first_hour = max( 0, min( 23, $first_hour ) );
		$last_hour = max( 0, min( 23, $last_hour ) );

		$rowspan = $last_hour - $first_hour + 1;

		return array( $first_hour, $rowspan );
	}

	private function buildScheduleColumns( $employees, $appointments, $grid_origin ){

		$by_employee = array();

		foreach( $appointments as $a ){

			list( $first_hour, $rowspan ) = $this->computeBucket( $a, $grid_origin );

			$key = $a->id_employee ? (int) $a->id_employee : 'unassigned';

			if( !isset($by_employee[$key]) ){
				$by_employee[$key] = array();
			}

			$by_employee[$key][$first_hour] = array( 'appt' => $a, 'rowspan' => $rowspan );
		}

		$columns = array();

		if( !empty($by_employee['unassigned']) ){

			$columns[] = $this->buildScheduleColumn( NULL, 'Sin asignar', $by_employee['unassigned'] );
		}

		foreach( $employees as $e ){

			$hour_map = isset($by_employee[(int) $e->id_employee]) ? $by_employee[(int) $e->id_employee] : array();

			$columns[] = $this->buildScheduleColumn( $e->id_employee, $e->name, $hour_map );
		}

		return $columns;
	}

	private function buildScheduleColumn( $id_employee, $label, $hour_map ){

		// hours covered by an earlier row's rowspan — must not emit a <td> for these
		$skip = array_fill( 0, 24, FALSE );

		foreach( $hour_map as $first_hour => $info ){

			for( $k = $first_hour + 1; $k < $first_hour + $info['rowspan']; $k++ ){
				$skip[$k] = TRUE;
			}
		}

		return array( 'id_employee' => $id_employee, 'label' => $label, 'hour_map' => $hour_map, 'skip' => $skip );
	}

	private function validateAppointmentInput( $id_client, $id_products, $start_datetime, $end_datetime ){

		if( empty($id_products) || !is_array($id_products) ){

			throw new Exception('Debe seleccionar al menos un servicio');
		}

		if( strtotime($end_datetime) <= strtotime($start_datetime) ){

			throw new Exception('La hora final debe ser mayor a la hora inicial');
		}

		$client = $this->clients_mdl->getClient( $id_client );

		if( !$client ){

			throw new Exception('Cliente inválido');
		}

		$valid_services = $this->appointments_mdl->countActiveServices( $id_products );

		if( $valid_services != count($id_products) ){

			throw new Exception('Uno o más servicios seleccionados no son válidos');
		}
	}

	private function resolveDeposit( array $id_products ){

		$exchange = $this->db->get('dollar_exchange')->row();

		$services_total = $this->appointments_mdl->sumServicePrices( $id_products );
		$required_cordobas = round( $services_total * $exchange->sell_price * 0.10, 2 );

		$token_account = $this->input->post('account');
		$cordoba = floatval( str_replace(',', '', $this->input->post('deposit_cordoba')) );
		$dolar = floatval( str_replace(',', '', $this->input->post('deposit_dolar')) );

		$account = $this->bank_account_mdl->getAccount( $token_account );

		if( !$account ){

			throw new Exception('Cuenta bancaria no existe');
		}

		if( (int) $account->active !== 1 ){

			throw new Exception('Cuenta bancaria ya no está disponible');
		}

		$entered_cordobas_equivalent = $cordoba + ( $dolar * $exchange->sell_price );

		if( abs( $entered_cordobas_equivalent - $required_cordobas ) > 0.01 ){

			throw new Exception('El depósito debe ser exactamente el 10% del total de servicios (C$'.number_format($required_cordobas,2).')');
		}

		if( $account->type === 'dolares' && $cordoba > 0 && (float) $account->sell_rate <= 0 ){

			throw new Exception('La cuenta no tiene una tasa de venta válida configurada');
		}

		if( $account->type === 'cordobas' && $dolar > 0 && (float) $account->buy_rate <= 0 ){

			throw new Exception('La cuenta no tiene una tasa de compra válida configurada');
		}

		if( $account->type === 'dolares' ){

			$converted = $cordoba > 0 ? ($cordoba / $account->sell_rate) : 0;
			$final_dolar = $dolar + $converted;
			$final_cordoba = 0;
		}
		else{

			$converted = $dolar > 0 ? ($dolar * $account->buy_rate) : 0;
			$final_cordoba = $cordoba + $converted;
			$final_dolar = 0;
		}

		return array( 'account' => $account->id_account, 'cordoba' => $final_cordoba, 'dolar' => $final_dolar );
	}

	public function register(){

		if( formSubmited() ){

			if( !$this->cash_mdl->getOpenedCash() ){

				echo json_encode( array( 'type' => 'warning', 'message' => 'Para registrar reservas con depósito debe primero abrir la caja del día' ) );
				exit();
			}

			$this->form_validation->set_rules('id_client','','required|integer');
			$this->form_validation->set_rules('id_employee','','required|integer');
			$this->form_validation->set_rules('start_datetime','','required');
			$this->form_validation->set_rules('end_datetime','','required');

			try{

				if( $this->form_validation->run() ){

					$id_client = (int) $this->input->post('id_client');
					$id_employee = (int) $this->input->post('id_employee');
					$start_datetime = $this->input->post('start_datetime');
					$end_datetime = $this->input->post('end_datetime');
					$notes = trim( $this->input->post('notes') );
					$id_products = $this->input->post('id_products');

					$this->validateAppointmentInput( $id_client, $id_products, $start_datetime, $end_datetime );

					if( $this->appointments_mdl->hasOverlap( $id_employee, $start_datetime, $end_datetime ) ){

						throw new Exception('Este empleado ya tiene una reserva en ese horario');
					}

					$deposit = $this->resolveDeposit( $id_products );

					$id_appointment = $this->appointments_mdl->save( $id_client, $this->get_userID(), $id_employee, $start_datetime, $end_datetime, $notes !== '' ? $notes : NULL, $id_products, $deposit['account'], $deposit['cordoba'], $deposit['dolar'] );

					if( !$id_appointment ){

						throw new Exception('Ha ocurrido un error guardando la reserva');
					}

					$new_tk = generateToken();
					$this->session->set_userdata('tk', $new_tk);

					$response = array( 'type' => 'success', 'message' => 'Reserva registrada', 'tk' => $new_tk );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');
				}
			}
			catch( Exception $e ){

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode( $response );
			}
		}
		else{

			redirect('appointments');
		}
	}

	public function update( $id_appointment = 0 ){

		if( !formSubmited() ){

			redirect('appointments');
			return;
		}

		$this->form_validation->set_rules('id_client','','required|integer');
		$this->form_validation->set_rules('id_employee','','required|integer');
		$this->form_validation->set_rules('start_datetime','','required');
		$this->form_validation->set_rules('end_datetime','','required');

		try{

			if( !$this->form_validation->run() ){

				throw new Exception('Hay valores inválidos en el formulario');
			}

			$appointment = $this->appointments_mdl->getAppointment( $id_appointment );

			if( !$appointment ){

				throw new Exception('Reserva inválida');
			}

			if( $this->appointments_mdl->isInvoiced( $id_appointment ) ){

				throw new Exception('Esta reserva ya fue facturada y no puede modificarse');
			}

			$id_client = (int) $this->input->post('id_client');
			$id_employee = (int) $this->input->post('id_employee');
			$start_datetime = $this->input->post('start_datetime');
			$end_datetime = $this->input->post('end_datetime');
			$notes = trim( $this->input->post('notes') );
			$id_products = $this->input->post('id_products');
			$schedule_date = $this->input->post('schedule_date');
			$validate_only = (bool) $this->input->post('validate_only');

			$this->validateAppointmentInput( $id_client, $id_products, $start_datetime, $end_datetime );

			if( empty($schedule_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date) || !strtotime($schedule_date) ){

				throw new Exception('Fecha de agenda inválida');
			}

			$shifts = array();

			if( $this->appointments_mdl->hasOverlap( $id_employee, $start_datetime, $end_datetime, $id_appointment ) ){

				$window_start = date( 'Y-m-d H:i:s', strtotime($schedule_date.' 01:00:00') );
				$window_end = date( 'Y-m-d H:i:s', strtotime($schedule_date.' +1 day 01:00:00') );
				$cascade_cap_ts = strtotime( $schedule_date.' +1 day 00:00:00' );

				$others = $this->appointments_mdl->getEmployeeDayAppointments( $id_employee, $window_start, $window_end, $id_appointment );

				$new_start_ts = strtotime( $start_datetime );
				$cursor_end_ts = strtotime( $end_datetime );

				foreach( $others as $other ){

					$other_start_ts = strtotime( $other->start_datetime );
					$other_end_ts = strtotime( $other->end_datetime );

					if( $other_end_ts <= $new_start_ts ){

						// entirely before the edited slot — unrelated, leave it alone
						continue;
					}

					if( $other_start_ts < $new_start_ts ){

						throw new Exception('No se puede reprogramar: se solapa con una reserva anterior');
					}

					if( $other_start_ts >= $cursor_end_ts ){

						break;
					}

					$duration = $other_end_ts - $other_start_ts;
					$shifted_start_ts = $cursor_end_ts;
					$shifted_end_ts = $shifted_start_ts + $duration;

					if( $shifted_end_ts > $cascade_cap_ts ){

						throw new Exception('No hay espacio disponible para reprogramar las reservas siguientes');
					}

					$shifts[] = array(
						'id_appointment' => $other->id_appointment,
						'start_datetime' => date('Y-m-d H:i:s', $shifted_start_ts),
						'end_datetime'   => date('Y-m-d H:i:s', $shifted_end_ts),
					);

					$cursor_end_ts = $shifted_end_ts;
				}
			}

			if( $validate_only ){

				$message = count($shifts) > 0 ? count($shifts).' reserva(s) serán reprogramada(s)' : 'OK';

				echo json_encode( array( 'type' => 'success', 'message' => $message ) );
				return;
			}

			$edit_reason = trim( $this->input->post('edit_reason') );

			if( $edit_reason === '' ){

				throw new Exception('Debe indicar el motivo del cambio');
			}

			$ok = $this->appointments_mdl->updateWithShifts( $id_appointment, $id_client, $id_employee, $start_datetime, $end_datetime, $notes !== '' ? $notes : NULL, $id_products, $shifts, $this->get_userID(), $edit_reason );

			if( !$ok ){

				throw new Exception('Ha ocurrido un error actualizando la reserva');
			}

			$new_tk = generateToken();
			$this->session->set_userdata('tk', $new_tk);

			$message = 'Reserva actualizada';

			if( count($shifts) > 0 ){

				$message .= ' y '.count($shifts).' reserva(s) reprogramada(s)';
			}

			echo json_encode( array( 'type' => 'success', 'message' => $message, 'tk' => $new_tk ) );
		}
		catch( Exception $e ){

			echo json_encode( array( 'type' => 'error', 'message' => $e->getMessage() ) );
		}
	}

	public function delete( $id_appointment = 0 ){

		if( !$this->is_admin() ){

			echo json_encode( array( 'type' => 'error', 'message' => 'No tiene permisos para eliminar reservas' ) );
			exit;
		}

		$appointment = $this->appointments_mdl->getAppointment( $id_appointment );

		if( !$appointment ){

			echo json_encode( array( 'type' => 'error', 'message' => 'Reserva inválida' ) );
			exit;
		}

		if( $this->appointments_mdl->isInvoiced( $id_appointment ) ){

			echo json_encode( array( 'type' => 'error', 'message' => 'Esta reserva ya fue facturada y no puede eliminarse' ) );
			exit;
		}

		try{

			$this->appointments_mdl->delete( $id_appointment );
		}
		catch( \Throwable $e ){

			echo json_encode( array( 'type' => 'error', 'message' => 'Ha ocurrido un error cancelando la reserva' ) );
			exit;
		}

		echo json_encode( array( 'type' => 'success', 'message' => 'Reserva cancelada' ) );
	}
}
