<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Shared engine for the "upload data from Excel" feature (providers, employees, clients).
 *
 * A column spec is: array( 'key' => 'name', 'label' => 'Nombre', 'required' => TRUE, 'text' => TRUE, 'width' => 28, 'note' => '...' )
 */
class Excel_import{

	const MAX_FILE_BYTES = 5242880; // 5 MB
	const MAX_ROWS = 2000;
	const MAX_EXPANDED_BYTES = 52428800; // 50 MB of uncompressed XML
	const MAX_SHEET_ROWS = 20000; // guards against sheets whose "used range" is huge

	private $previous_error_reporting;

	// PHPExcel-1.8 predates PHP 8: its harmless deprecation/notice noise would otherwise be printed
	// inline by CI2's error handler and corrupt the xlsx / JSON response (same trick as Sales_report::export())
	private function loadPhpExcel(){

		$this->previous_error_reporting = error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT & ~E_WARNING );
		require_once( FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php' );
	}

	private function restoreErrorReporting(){

		if( $this->previous_error_reporting !== NULL ){
			error_reporting( $this->previous_error_reporting );
			$this->previous_error_reporting = NULL;
		}
	}

	private function headerLabel( $col ){

		return $col['label'].( !empty($col['required']) ? '*' : '' );
	}

	// "Fecha de nacimiento* (dd/mm/aaaa)" => "fecha de nacimiento"
	private function normalizeHeader( $text ){

		$text = preg_replace('/\(.*?\)/u', '', (string) $text);
		$text = str_replace('*', '', $text);

		return mb_strtolower( trim( preg_replace('/\s+/u', ' ', $text) ), 'UTF-8' );
	}

	/**
	 * Streams the empty template (sheet "Datos" + sheet "Instrucciones") and exits.
	 */
	public function sendTemplate( $filename, array $columns, array $extra_notes = array() ){

		$this->loadPhpExcel();

		$excel = new PHPExcel();
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('Datos');

		foreach( $columns as $i => $col ){

			$letter = PHPExcel_Cell::stringFromColumnIndex($i);

			$sheet->setCellValueExplicitByColumnAndRow($i, 1, $this->headerLabel($col), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->getColumnDimension($letter)->setWidth( isset($col['width']) ? $col['width'] : 25 );

			// text format keeps leading zeros, "+" prefixes and dd/mm/yyyy dates exactly as typed
			if( !empty($col['text']) ){
				$sheet->getStyle($letter.'2:'.$letter.'1000')->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_TEXT );
			}
		}

		$last_letter = PHPExcel_Cell::stringFromColumnIndex( count($columns) - 1 );
		$sheet->getStyle('A1:'.$last_letter.'1')->getFont()->setBold(TRUE);
		$sheet->getStyle('A1:'.$last_letter.'1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
		$sheet->freezePane('A2');

		$help = $excel->createSheet();
		$help->setTitle('Instrucciones');
		$help->setCellValue('A1', 'Columna');
		$help->setCellValue('B1', 'Obligatorio');
		$help->setCellValue('C1', 'Formato');
		$help->getStyle('A1:C1')->getFont()->setBold(TRUE);

		$row = 2;
		foreach( $columns as $col ){
			$help->setCellValue('A'.$row, $col['label']);
			$help->setCellValue('B'.$row, !empty($col['required']) ? 'Sí' : 'No');
			$help->setCellValue('C'.$row, isset($col['note']) ? $col['note'] : '');
			$row++;
		}

		$row++;
		$notes = array_merge(array(
			'Llene los datos en la hoja "Datos", una fila por registro, sin modificar la fila de encabezados.',
			'Máximo '.self::MAX_ROWS.' filas por archivo.',
			'Las filas repetidas (dentro del archivo o ya registradas en el sistema) se omiten automáticamente.',
			'Si alguna fila tiene datos inválidos no se guarda nada: el sistema indica la fila y el motivo.',
		), $extra_notes);

		foreach( $notes as $note ){
			$help->setCellValue('A'.$row, $note);
			$row++;
		}

		$help->getColumnDimension('A')->setWidth(28);
		$help->getColumnDimension('B')->setWidth(14);
		$help->getColumnDimension('C')->setWidth(70);

		$excel->setActiveSheetIndex(0);

		while( ob_get_level() ){
			ob_end_clean();
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="'.$filename.'"');
		header('Cache-Control: max-age=0');

		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
		$writer->save('php://output');

		// no restoreErrorReporting() here: PHPExcel objects emit deprecation notices when destroyed at exit,
		// which would append an HTML error block after the zip data and make Excel report the file as corrupt
		exit;
	}

	private function cellToString( $value ){

		if( $value === NULL ){
			return '';
		}

		if( is_float($value) || is_int($value) ){
			// a phone typed as a number: avoid "8.8851430E+7" and trailing ".0"
			return $value == floor($value) ? sprintf('%.0f', $value) : rtrim( rtrim( sprintf('%.10F', $value), '0' ), '.' );
		}

		if( is_bool($value) ){
			return $value ? '1' : '0';
		}

		$value = (string) $value;

		if( !mb_check_encoding($value, 'UTF-8') ){
			$value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
		}

		// NBSP / tabs / line breaks / repeated spaces => single space, then trim
		return trim( preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', $value) );
	}

	/**
	 * Reads the uploaded xlsx and returns array of array( 'row' => excelRowNumber, 'data' => array( key => value ) ).
	 * Throws Exception (message safe to show to the user) on any file-level problem.
	 */
	public function readUpload( $field, array $columns ){

		if( empty($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE ){
			throw new Exception('Seleccione un archivo Excel (.xlsx)');
		}

		$file = $_FILES[$field];

		if( $file['error'] !== UPLOAD_ERR_OK ){
			throw new Exception('No se pudo recibir el archivo, intente de nuevo');
		}

		if( $file['size'] <= 0 ){
			throw new Exception('El archivo está vacío');
		}

		if( $file['size'] > self::MAX_FILE_BYTES ){
			throw new Exception('El archivo supera el tamaño máximo de 5 MB');
		}

		if( strtolower( pathinfo($file['name'], PATHINFO_EXTENSION) ) !== 'xlsx' ){
			throw new Exception('Formato inválido: use la plantilla en formato .xlsx');
		}

		// an xlsx is a zip container; reject anything else before handing it to the parser
		$handle = @fopen($file['tmp_name'], 'rb');
		$magic = $handle ? fread($handle, 4) : '';
		if( $handle ){ fclose($handle); }

		if( $magic !== "PK\x03\x04" ){
			throw new Exception('El archivo no es un Excel (.xlsx) válido');
		}

		// an .xlsx is a zip: refuse archives that would expand into an unreasonable amount of XML (zip bomb)
		$zip = new ZipArchive();
		if( $zip->open($file['tmp_name']) !== TRUE ){
			throw new Exception('El archivo no es un Excel (.xlsx) válido');
		}

		$expanded = 0;
		for( $i = 0; $i < $zip->numFiles; $i++ ){
			$stat = $zip->statIndex($i);
			$expanded += $stat ? $stat['size'] : 0;
		}
		$zip->close();

		if( $expanded > self::MAX_EXPANDED_BYTES ){
			throw new Exception('El archivo es demasiado grande o complejo para procesarse');
		}

		@ini_set('memory_limit', '384M');
		@set_time_limit(120);

		$this->loadPhpExcel();

		try{

			$reader = PHPExcel_IOFactory::createReader('Excel2007');
			$reader->setReadDataOnly(TRUE);
			$excel = $reader->load($file['tmp_name']);

			// the "Datos" sheet, or the first one if the customer renamed it
			$sheet = $excel->getSheetByName('Datos');
			if( !$sheet ){
				$sheet = $excel->getSheet(0);
			}

			if( $sheet->getHighestDataRow() > self::MAX_SHEET_ROWS ){
				throw new Exception('El archivo tiene demasiadas filas (máximo '.self::MAX_ROWS.' registros)');
			}

			$grid = $sheet->toArray(NULL, FALSE, FALSE, FALSE);

			$excel->disconnectWorksheets();
			unset($sheet, $excel, $reader);
		}
		catch( Throwable $e ){

			if( $e instanceof Exception && strpos($e->getMessage(), 'demasiadas') !== FALSE ){
				throw $e;
			}

			throw new Exception('No se pudo leer el archivo, verifique que sea un Excel (.xlsx) válido');
		}

		// error_reporting stays lowered for the rest of the request on purpose: PHPExcel objects emit
		// deprecation notices when they are garbage-collected, which would corrupt the JSON response

		// header row must match the template, in order
		$header = isset($grid[0]) ? $grid[0] : array();

		foreach( $columns as $i => $col ){

			$found = isset($header[$i]) ? $this->normalizeHeader( $this->cellToString($header[$i]) ) : '';

			if( $found !== $this->normalizeHeader($col['label']) ){
				throw new Exception('Los encabezados no coinciden con la plantilla. Descargue la plantilla y no modifique la primera fila');
			}
		}

		$rows = array();

		for( $r = 1; $r < count($grid); $r++ ){

			$data = array();
			$blank = TRUE;

			foreach( $columns as $i => $col ){

				$raw = isset($grid[$r][$i]) ? $grid[$r][$i] : NULL;

				if( !empty($col['date']) && ( is_int($raw) || is_float($raw) ) ){
					$data[$col['key']] = $raw; // keep the Excel serial, validator converts it
					$blank = FALSE;
					continue;
				}

				$data[$col['key']] = $this->cellToString($raw);

				if( $data[$col['key']] !== '' ){
					$blank = FALSE;
				}
			}

			if( !$blank ){
				$rows[] = array( 'row' => $r + 1, 'data' => $data );
			}
		}

		if( empty($rows) ){
			throw new Exception('El archivo no contiene datos');
		}

		if( count($rows) > self::MAX_ROWS ){
			throw new Exception('El archivo tiene '.count($rows).' filas; el máximo permitido es '.self::MAX_ROWS);
		}

		return $rows;
	}

	/**
	 * Converts a cell (Excel serial number or "dd/mm/yyyy" text) to Y-m-d, or NULL if not a real date.
	 */
	public static function parseDate( $value ){

		if( is_int($value) || is_float($value) ){

			if( $value < 1 || $value > 80000 ){
				return NULL;
			}

			return gmdate('Y-m-d', (int) round( ($value - 25569) * 86400 ));
		}

		$text = trim((string) $value);
		$text = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?(\.\d+)?$/', '', $text); // drop a trailing time

		if( preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ){
			return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
		}

		if( preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ){
			return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
		}

		return NULL;
	}

	/**
	 * Full pipeline. $cfg:
	 *   columns   column specs (see header)
	 *   validate  callable( array &$data ): ?string   -> error message or NULL; may normalize $data in place
	 *   key       callable( array $data ): string     -> duplicate key of a validated row
	 *   existing  callable(): array                   -> list of duplicate keys already in the DB
	 *   insert    callable( array $rows ): void       -> inserts validated rows (throws on failure)
	 *   db        CI database object (used for the transaction)
	 * Returns the JSON response array (without the token).
	 */
	public function run( array $cfg ){

		try{
			$rows = $this->readUpload('file', $cfg['columns']);
		}
		catch( Exception $e ){
			return array( 'type' => 'error', 'message' => $e->getMessage() );
		}

		// 1) validate everything first: any invalid row aborts the whole import before touching the DB
		$errors = array();
		$valid = array();

		foreach( $rows as $r ){

			$data = $r['data'];
			$error = NULL;

			// the app talks to MySQL in 3-byte utf8 and the server is non-strict: 4-byte characters would be stored as "????"
			foreach( $data as $value ){
				if( !is_string($value) ){
					continue;
				}

				if( preg_match('/[\x{10000}-\x{10FFFF}]/u', $value) ){
					$error = 'Contiene caracteres no soportados (por ejemplo emojis)';
					break;
				}

				// Excel content bypasses global_xss_filtering and several list views print values unescaped
				if( preg_match('/[<>]|[\x00-\x08\x0B\x0C\x0E-\x1F]/u', $value) ){
					$error = 'Contiene caracteres no permitidos (< > o de control)';
					break;
				}

				// a leading = or @ would be evaluated as a formula if the value is ever exported back to Excel
				if( preg_match('/^[=@]/', $value) ){
					$error = 'No puede comenzar con = o @';
					break;
				}
			}

			if( $error === NULL ){
				$error = call_user_func_array($cfg['validate'], array( &$data ));
			}

			if( $error !== NULL ){
				$errors[] = array( 'row' => $r['row'], 'reason' => $error );
			}
			else{
				$valid[] = array( 'row' => $r['row'], 'data' => $data );
			}
		}

		if( !empty($errors) ){
			return array(
				'type' => 'error',
				'message' => 'El archivo tiene '.count($errors).' fila(s) con datos inválidos. No se guardó ningún dato.',
				'errors' => array_slice($errors, 0, 50),
				'errors_total' => count($errors),
			);
		}

		$db = $cfg['db'];
		$previous_debug = $db->db_debug;
		$db->db_debug = FALSE; // CI2 would otherwise print an HTML error page and exit on a failed query

		$transaction_open = FALSE;

		try{

			// 2) drop duplicates (inside the file and already in the DB) and keep going
			$seen = array_flip( call_user_func($cfg['existing']) );
			$to_insert = array();
			$duplicates = array();

			foreach( $valid as $r ){

				$key = call_user_func($cfg['key'], $r['data']);

				if( isset($seen[$key]) ){
					$duplicates[] = array( 'row' => $r['row'], 'reason' => 'Registro duplicado, se omitió' );
					continue;
				}

				$seen[$key] = TRUE;
				$to_insert[] = $r['data'];
			}

			// 3) all-or-nothing insert
			if( !empty($to_insert) ){

				$db->trans_begin();
				$transaction_open = TRUE;

				call_user_func($cfg['insert'], $to_insert);

				if( $db->trans_status() === FALSE ){
					throw new Exception('Database error');
				}

				$db->trans_commit();
				$transaction_open = FALSE;
			}
		}
		catch( Throwable $e ){

			if( $transaction_open ){
				$db->trans_rollback();
			}

			// a throwing insert_batch() leaves its half-built statement in the query builder, which the
			// session driver's shutdown write would otherwise merge into its own UPDATE
			$db->reset_query();

			$db->db_debug = $previous_debug;

			log_message('error', 'Excel import failed: '.$e->getMessage());

			return array( 'type' => 'error', 'message' => 'La operación falló y no se guardó ningún dato. Intente de nuevo; si el problema continúa, revise el archivo.' );
		}

		$db->db_debug = $previous_debug;

		$inserted = count($to_insert);

		return array(
			'type' => $inserted > 0 ? 'success' : 'warning',
			'message' => $inserted > 0 ? "Se registraron $inserted registro(s)." : 'No se registró ningún dato nuevo: todas las filas ya existían.',
			'inserted' => $inserted,
			'skipped' => count($duplicates),
			'duplicates' => array_slice($duplicates, 0, 50),
		);
	}
}
