<?php 

	if( !defined('BASEPATH') ){ exit(); }

	class Imports extends Admin_Controller{

		public function __construct(){

			parent::__construct();
		}

		public function index(){

			$this->form();
		}

		public function form(){

			$this->load->view('form_imports');
		}

		//jr
		public function import1(){

			$this->load->library('imagetool');
			$this->imagetool->setUploadPath('imports');
			$upl_data = $this->imagetool->uploadImg('file');

			require_once(FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php');

			$excelReader = PHPExcel_IOFactory::createReaderForFile($upl_data['full_path']);
			$excelObj = $excelReader->load($upl_data['full_path']);
			$worksheet = $excelObj->getSheet(0);
			$lastRow = $worksheet->getHighestRow();

			$products = 0;

			//echo '<pre>';
			$errors = array();

			$con = new PDO("mysql:host=localhost;dbname=coco_cosmetics", 'root', '',array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
			$con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
   			$con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

			for( $row = 6; $row <= $lastRow; $row++ ){

				if( $worksheet->getCell('A'.$row)->getValue() == '' ){

					continue;
				}

				$product['barcode'] = $worksheet->getCell('C'.$row)->getValue();
				$product['name'] = $worksheet->getCell('D'.$row)->getValue();
				$product['stock'] = $worksheet->getCell('E'.$row)->getValue();
				$product['price'] = $worksheet->getCell('F'.$row)->getValue();

				$sql = 'insert into products(barcode,name,stock,price_cost,price_sell)
				values(:barcode,:name,:stock,:price_cost,:price_sell)';

				$qry = $con->prepare($sql);
				$qry->bindParam(':barcode', $product['barcode'], PDO::PARAM_STR);
				$qry->bindParam(':name', $product['name'], PDO::PARAM_STR);
				$qry->bindParam(':stock', $product['stock'], PDO::PARAM_STR);
				$qry->bindParam(':price_cost', $product['price'], PDO::PARAM_STR);
				$qry->bindParam(':price_sell', $product['price'], PDO::PARAM_STR);

				try{

					$qry->execute();
				}
				catch( Exception $e ){

					array_push($errors, $e->getMessage());
				}

				$products++;			
			}

			echo '<pre>';
			echo '<br>'.$products.' productos importados<br>';
			if( !empty($errors) ){

				echo 'Ocurrieron los siguientes errores ('.count($errors).'):';
				print_r($errors);
			}
			
		}

		//pedido coco cosmetic cefa
		public function import2(){

			$this->load->library('imagetool');
			$this->imagetool->setUploadPath('imports');
			$upl_data = $this->imagetool->uploadImg('file');

			require_once(FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php');

			$excelReader = PHPExcel_IOFactory::createReaderForFile($upl_data['full_path']);
			$excelObj = $excelReader->load($upl_data['full_path']);
			$worksheet = $excelObj->getSheet(0);
			$lastRow = $worksheet->getHighestRow();

			$products = 0;

			//echo '<pre>';
			$errors = array();

			$con = new PDO("mysql:host=localhost;dbname=coco_cosmetics", 'root', '',array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
			$con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
   			$con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

			for( $row = 3; $row <= $lastRow; $row++ ){

				$cval = $worksheet->getCell('C'.$row)->getValue();

				if( $cval == '' || !is_numeric($cval) ){

					continue;
				}

				$product['barcode'] = NULL;
				$product['name'] = $worksheet->getCell('D'.$row)->getCalculatedValue();
				$product['stock'] = empty($worksheet->getCell('A'.$row)->getValue()) ? 0 : $worksheet->getCell('A'.$row)->getValue();
				$product['price'] = $worksheet->getCell('E'.$row)->getValue();

				$sql = 'insert into products(barcode,name,stock,price_cost,price_sell)
				values(:barcode,:name,:stock,:price_cost,:price_sell)';

				$qry = $con->prepare($sql);
				$qry->bindParam(':barcode', $product['barcode'], PDO::PARAM_STR);
				$qry->bindParam(':name', $product['name'], PDO::PARAM_STR);
				$qry->bindParam(':stock', $product['stock'], PDO::PARAM_STR);
				$qry->bindParam(':price_cost', $product['price'], PDO::PARAM_STR);
				$qry->bindParam(':price_sell', $product['price'], PDO::PARAM_STR);

				try{

					$qry->execute();
				}
				catch( Exception $e ){

					array_push($errors, $e->getMessage());
				}

				$products++;			
			}

			echo '<pre>';
			echo '<br>'.$products.' productos importados<br>';
			if( !empty($errors) ){

				echo 'Ocurrieron los siguientes errores ('.count($errors).'):';
				print_r($errors);
			}
		}

		//pedido CEFA
		public function import3(){

			$this->load->library('imagetool');
			$this->imagetool->setUploadPath('imports');
			$upl_data = $this->imagetool->uploadImg('file');

			require_once(FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php');

			$excelReader = PHPExcel_IOFactory::createReaderForFile($upl_data['full_path']);
			$excelObj = $excelReader->load($upl_data['full_path']);
			$worksheet = $excelObj->getSheet(0);
			$lastRow = $worksheet->getHighestRow();

			$products = 0;

			$errors = array();

			$con = new PDO("mysql:host=localhost;dbname=coco_cosmetics", 'root', '',array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
			$con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
   			$con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

			for( $row = 7; $row <= $lastRow; $row++ ){

				$name = $worksheet->getCell('C'.$row)->getValue();

				if( $name == '' ){

					break;
				}

				$match = array();
				preg_match('#\((.*?)\)#', $name, $match);

				$fps = strripos($name,'(');

				$product['barcode'] = $match[1];
				$product['name'] = substr($name, 0, $fps);
				$product['stock'] = $worksheet->getCell('B'.$row)->getValue();
				$product['price'] = substr($worksheet->getCell('E'.$row)->getValue(),1);

				$sql = 'insert into products(barcode,name,stock,price_cost,price_sell)
				values(:barcode,:name,:stock,:price_cost,:price_sell)';

				$qry = $con->prepare($sql);
				$qry->bindParam(':barcode', $product['barcode'], PDO::PARAM_STR);
				$qry->bindParam(':name', $product['name'], PDO::PARAM_STR);
				$qry->bindParam(':stock', $product['stock'], PDO::PARAM_STR);
				$qry->bindParam(':price_cost', $product['price'], PDO::PARAM_STR);
				$qry->bindParam(':price_sell', $product['price'], PDO::PARAM_STR);

				try{

					$qry->execute();
				}
				catch( Exception $e ){

					array_push($errors, $e->getMessage());
				}

				$products++;			
			}

			echo '<pre>';
			echo '<br>'.$products.' productos importados<br>';
			if( !empty($errors) ){

				echo 'Ocurrieron los siguientes errores ('.count($errors).'):';
				print_r($errors);
			}
		}

		//inventario de traslado
		public function import4(){

			$this->load->library('imagetool');
			$this->imagetool->setUploadPath('imports');
			$upl_data = $this->imagetool->uploadImg('file');

			require_once(FCPATH.'includes/PHPExcel-1.8/Classes/PHPExcel.php');

			$excelReader = PHPExcel_IOFactory::createReaderForFile($upl_data['full_path']);
			$excelObj = $excelReader->load($upl_data['full_path']);
			$worksheet = $excelObj->getSheet(0);
			$lastRow = $worksheet->getHighestRow();

			$products = 0;

			$errors = array();

			$con = new PDO("mysql:host=localhost;dbname=coco_cosmetics", 'root', '',array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
			$con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
   			$con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

			for( $row = 9; $row <= $lastRow; $row++ ){

				$name = $worksheet->getCell('D'.$row)->getValue();

				if( $name == '' ){

					break;
				}

				$product['barcode'] = NULL;
				$product['name'] = $name;
				$product['stock'] = $worksheet->getCell('C'.$row)->getValue();
				$product['price'] = substr(trim($worksheet->getCell('I'.$row)->getValue()),1);

				$sql = 'insert into products(barcode,name,stock,price_cost,price_sell)
				values(:barcode,:name,:stock,:price_cost,:price_sell)';

				$qry = $con->prepare($sql);
				$qry->bindParam(':barcode', $product['barcode'], PDO::PARAM_STR);
				$qry->bindParam(':name', $product['name'], PDO::PARAM_STR);
				$qry->bindParam(':stock', $product['stock'], PDO::PARAM_STR);
				$qry->bindParam(':price_cost', $product['price'], PDO::PARAM_STR);
				$qry->bindParam(':price_sell', $product['price'], PDO::PARAM_STR);

				try{

					$qry->execute();
				}
				catch( Exception $e ){

					array_push($errors, $e->getMessage());
				}

				$products++;			
			}

			echo '<pre>';
			echo '<br>'.$products.' productos importados<br>';
			if( !empty($errors) ){

				echo 'Ocurrieron los siguientes errores ('.count($errors).'):';
				print_r($errors);
			}
		}

		public function test(){

			$match = array();
			$str = 'LÓREAL SOMBRA COLOR RICHE LA PALETTE NUDE ROSE #002 (3600522832669)';
			preg_match('#\((.*?)\)#', $str, $match);
			print $match[1];

			$fps = strripos($str,'(');
			echo substr($str, 0, $fps);
		}
	}

?>