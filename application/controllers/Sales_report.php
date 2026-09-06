<?php if( !defined('BASEPATH') ){ exit(); }

class Sales_report extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('sales_report_mdl');
	}

	public function index(){

		$this->renderPanel('sales_report');
	}

	// API for the report's single dataset — used to build the dashboard and detail table client-side
	public function data(){

		if( $_SERVER["REQUEST_METHOD"] !== "POST" ) exit;

		$date_start = $this->input->post('date_start');
		$date_end = $this->input->post('date_end');

		if( empty($date_start) || empty($date_end) ){
			echo json_encode(array());
			exit;
		}

		echo json_encode( $this->sales_report_mdl->getDetailedSales($date_start, $date_end) );
		exit;
	}

	public function export(){

		$date_start = $this->input->get('date_start');
		$date_end = $this->input->get('date_end');

		if( empty($date_start) || empty($date_end) ){
			show_error('Rango de fechas requerido', 400);
		}

		$rows = $this->sales_report_mdl->getDetailedSales($date_start, $date_end);

		// PHPExcel-1.8 predates PHP 8's stricter typing rules; its harmless (non-fatal)
		// E_DEPRECATED/E_NOTICE/E_WARNING noise — e.g. array-offset-on-int checks inside
		// its own type-detection helpers, which still resolve correctly — would otherwise
		// get printed inline by CI2's error handler (display_errors is on) and corrupt
		// this binary xlsx response
		$previous_error_reporting = error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT & ~E_WARNING );

		require_once(FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php');

		$excel = new PHPExcel();
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('Reporte de ventas');

		$headers = array(
			'No. Factura', 'Fecha', 'Cliente', 'Producto/Servicio', 'Tipo', 'Cantidad',
			'Precio unitario', 'Descuento %', 'Atendido por', 'Venta (C$)',
			'Utilidad bruta (C$)', 'Comisión (C$)', 'Ganancia real (C$)'
		);

		$col = 0;
		foreach( $headers as $h ){
			$sheet->setCellValueByColumnAndRow($col, 1, $h);
			$col++;
		}

		$row = 2;
		foreach( $rows as $r ){
			$sheet->setCellValueByColumnAndRow(0, $row, $r['id_sale']);
			$sheet->setCellValueByColumnAndRow(1, $row, date('d/m/Y H:i', strtotime($r['date_time'])));
			$sheet->setCellValueByColumnAndRow(2, $row, $r['client']);
			$sheet->setCellValueByColumnAndRow(3, $row, $r['product']);
			$sheet->setCellValueByColumnAndRow(4, $row, $r['is_service'] ? 'Servicio' : 'Producto');
			$sheet->setCellValueByColumnAndRow(5, $row, $r['quantity']);
			$sheet->setCellValueByColumnAndRow(6, $row, $r['price']);
			$sheet->setCellValueByColumnAndRow(7, $row, $r['discount']);
			$sheet->setCellValueByColumnAndRow(8, $row, $r['employee']);
			$sheet->setCellValueByColumnAndRow(9, $row, $r['venta']);
			$sheet->setCellValueByColumnAndRow(10, $row, $r['utilidad_bruta']);
			$sheet->setCellValueByColumnAndRow(11, $row, $r['comision']);
			$sheet->setCellValueByColumnAndRow(12, $row, $r['ganancia_real']);
			$row++;
		}

		foreach( range('A','M') as $col_letter ){
			$sheet->getColumnDimension($col_letter)->setAutoSize(true);
		}

		$filename = 'reporte_ventas_' . date('Y-m-d', strtotime($date_start)) . '_a_' . date('Y-m-d', strtotime($date_end)) . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');

		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
		$writer->save('php://output');

		error_reporting($previous_error_reporting);
		exit;
	}
}
