<?php if( !defined('BASEPATH') ){ exit(); }

class Settings extends Admin_Controller{

	private $logo_groups = array(
		'logo_header'         => array('label' => 'Logo del panel administrativo',    'default' => 'logo.png'),
		'logo_login'          => array('label' => 'Logo de inicio de sesión',          'default' => 'logo_white.png'),
		'logo_receipt'        => array('label' => 'Logo de recibo (impresión térmica)', 'default' => 'logo_invoice.png'),
		'logo_purchase_order' => array('label' => 'Logo de orden de compra (PDF)',     'default' => 'logo_invoice.jpg'),
	);

	private $allowed_logo_extensions = array('png', 'jpg', 'jpeg');
	private $max_logo_size = 5242880; // 5MB
	private $max_logo_dimension = 1000; // px

	public function __construct(){

		parent::__construct();
		$this->load->model('settings_mdl');
	}

	public function index(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->content_data['vip_discount_rate'] = $this->settings_mdl->get('vip_discount_rate', 15);
		$this->content_data['birthday_discount_rate'] = $this->settings_mdl->get('birthday_discount_rate', 50);
		$this->content_data['birthday_discount_ceiling_min'] = $this->settings_mdl->get('birthday_discount_ceiling_min', 0);
		$this->content_data['birthday_discount_ceiling_max'] = $this->settings_mdl->get('birthday_discount_ceiling_max', 999999999);
		$this->content_data['company_name'] = $this->settings_mdl->get('company_name', 'Conceptos');
		$this->content_data['receipt_ruc'] = $this->settings_mdl->get('receipt_ruc', '2811201760006N');
		$this->content_data['receipt_address'] = $this->settings_mdl->get('receipt_address', "Costado sur funeraria monte\nlos olivos 2 1/2c al este,\nManagua-Nicaragua.");
		$this->content_data['receipt_phone'] = $this->settings_mdl->get('receipt_phone', '8885-1430');
		$this->content_data['receipt_tagline'] = $this->settings_mdl->get('receipt_tagline', 'Siente la Diferencia');
		$this->content_data['receipt_footer_notice'] = $this->settings_mdl->get('receipt_footer_notice', 'No se aceptan devoluciones');
		$this->content_data['receipt_footer_thanks'] = $this->settings_mdl->get('receipt_footer_thanks', "Gracias por preferirnos!!\nLe esperamos pronto.");

		$logo_groups = $this->logo_groups;
		foreach( $logo_groups as $key => &$group ){
			$group['url'] = resolveLogoUrl($key, $group['default']);
		}
		unset($group);
		$this->content_data['logo_groups'] = $logo_groups;

		$this->content_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $this->content_data['tk']);

		$this->renderPanel('form_settings');
	}

	public function save(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( !formPosted() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		$this->form_validation->set_rules('vip_discount_rate','','required|numeric');
		$this->form_validation->set_rules('birthday_discount_rate','','required|numeric');
		$this->form_validation->set_rules('birthday_discount_ceiling_min','','required|numeric');
		$this->form_validation->set_rules('birthday_discount_ceiling_max','','required|numeric');
		$this->form_validation->set_rules('company_name','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_ruc','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_address','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_phone','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_tagline','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_footer_notice','','required|trim|max_length[255]');
		$this->form_validation->set_rules('receipt_footer_thanks','','required|trim|max_length[255]');

		if( !$this->form_validation->run() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		$rate = $this->input->post('vip_discount_rate');
		$birthday_rate = $this->input->post('birthday_discount_rate');

		if( $rate < 0 || $rate > 100 || $birthday_rate < 0 || $birthday_rate > 100 ){

			$response = array( 'type' => 'error', 'message' => 'El descuento debe estar entre 0 y 100' );
			echo json_encode($response);
			return;
		}

		$ceiling_min = $this->input->post('birthday_discount_ceiling_min');
		$ceiling_max = $this->input->post('birthday_discount_ceiling_max');

		if( $ceiling_min < 0 || $ceiling_max < 0 || $ceiling_min > $ceiling_max ){

			$response = array( 'type' => 'error', 'message' => 'El monto mínimo no puede ser mayor al máximo y ambos deben ser positivos' );
			echo json_encode($response);
			return;
		}

		try {
			$this->settings_mdl->save('vip_discount_rate', $rate);
			$this->settings_mdl->save('birthday_discount_rate', $birthday_rate);
			$this->settings_mdl->save('birthday_discount_ceiling_min', $ceiling_min);
			$this->settings_mdl->save('birthday_discount_ceiling_max', $ceiling_max);
			$this->settings_mdl->save('company_name', $this->input->post('company_name'));
			$this->settings_mdl->save('receipt_ruc', $this->input->post('receipt_ruc'));
			$this->settings_mdl->save('receipt_address', $this->input->post('receipt_address'));
			$this->settings_mdl->save('receipt_phone', $this->input->post('receipt_phone'));
			$this->settings_mdl->save('receipt_tagline', $this->input->post('receipt_tagline'));
			$this->settings_mdl->save('receipt_footer_notice', $this->input->post('receipt_footer_notice'));
			$this->settings_mdl->save('receipt_footer_thanks', $this->input->post('receipt_footer_thanks'));
		} catch (\Throwable $th) {
			$response = array( 'type' => 'error', 'message' => 'Ha ocurrido un error actualizando la configuración' );
			echo json_encode($response);
			return;
		}

		$response = array( 'type' => 'success', 'message' => 'Configuración actualizada' );
		echo json_encode($response);
	}

	public function uploadLogo(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( !formSubmited() ){
			echo json_encode( array( 'type' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo' ) );
			return;
		}

		$logo_key = $this->input->post('logo_key');

		if( !isset($this->logo_groups[$logo_key]) ){
			echo json_encode( array( 'type' => 'error', 'message' => 'Grupo de logo inválido' ) );
			return;
		}

		if( empty($_FILES['logo_file']) || !is_uploaded_file($_FILES['logo_file']['tmp_name']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK ){
			echo json_encode( array( 'type' => 'error', 'message' => 'No se recibió ninguna imagen válida' ) );
			return;
		}

		$file = $_FILES['logo_file'];

		if( $file['size'] > $this->max_logo_size ){
			echo json_encode( array( 'type' => 'error', 'message' => 'La imagen supera el tamaño máximo permitido (5MB)' ) );
			return;
		}

		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		if( !in_array($ext, $this->allowed_logo_extensions, true) ){
			echo json_encode( array( 'type' => 'error', 'message' => 'Formato de imagen no permitido. Use PNG o JPG' ) );
			return;
		}

		if( getimagesize($file['tmp_name']) === false ){
			echo json_encode( array( 'type' => 'error', 'message' => 'El archivo no es una imagen válida' ) );
			return;
		}

		$logos_dir = FCPATH . 'uploads/logos';

		if( !file_exists($logos_dir) && !mkdir($logos_dir, 0755, true) ){
			echo json_encode( array( 'type' => 'error', 'message' => 'No se pudo crear el directorio de logos' ) );
			return;
		}

		$new_filename = generateToken() . '.' . $ext;
		$dest_path = str_replace("\\",'/', $logos_dir . '/' . $new_filename);

		$stored = $this->compressLogoImage($file['tmp_name'], $dest_path, $ext);

		if( !$stored && !move_uploaded_file($file['tmp_name'], $dest_path) ){
			echo json_encode( array( 'type' => 'error', 'message' => 'Ocurrió un error guardando la imagen en el servidor' ) );
			return;
		}

		$old_filename = $this->settings_mdl->get($logo_key);

		try {
			$this->settings_mdl->save($logo_key, $new_filename);
		} catch (\Throwable $th) {
			@unlink($dest_path);
			echo json_encode( array( 'type' => 'error', 'message' => 'Ha ocurrido un error actualizando la configuración' ) );
			return;
		}

		if( !empty($old_filename) ){
			@unlink(str_replace("\\",'/', $logos_dir . '/' . $old_filename));
		}

		$new_tk = generateToken();
		$this->session->set_userdata('tk', $new_tk);

		$response = array(
			'type' => 'success',
			'message' => 'Logo actualizado',
			'tk' => $new_tk,
			'url' => resolveLogoUrl($logo_key, $this->logo_groups[$logo_key]['default']),
		);
		echo json_encode($response);
	}

	private function compressLogoImage( $tmp_path, $dest_path, $ext ){

		$info = @getimagesize($tmp_path);

		if( !$info ){
			return false;
		}

		list($width, $height) = $info;

		if( $ext === 'png' ){
			$img = @imagecreatefrompng($tmp_path);
		}
		else{
			$img = @imagecreatefromjpeg($tmp_path);
		}

		if( !$img ){
			return false;
		}

		if( $width > $this->max_logo_dimension || $height > $this->max_logo_dimension ){

			$ratio = min($this->max_logo_dimension / $width, $this->max_logo_dimension / $height);
			$new_width = (int) round($width * $ratio);
			$new_height = (int) round($height * $ratio);

			$resized = imagecreatetruecolor($new_width, $new_height);

			if( $ext === 'png' ){
				imagealphablending($resized, false);
				imagesavealpha($resized, true);
			}

			imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			imagedestroy($img);
			$img = $resized;
		}

		if( $ext === 'png' ){
			$saved = imagepng($img, $dest_path, 9);
		}
		else{
			$saved = imagejpeg($img, $dest_path, 85);
		}

		imagedestroy($img);

		return $saved;
	}
}
