<?php if( !defined('BASEPATH') ) exit();

/**
 * JSON API for the per-invoice comments chat shown in /sales/details/:id_sale.
 *   GET  /sale_comments/list_by_sale/:id_sale
 *   POST /sale_comments/create/:id_sale   (field: comment)
 */
class Sale_comments extends Admin_Controller{

	const MAX_LENGTH = 1000;

	public function __construct(){

		parent::__construct();
		// db_debug would render CI's HTML error page and exit; this API must always answer JSON
		$this->db->db_debug = FALSE;
		// app connection is 3-byte utf8; comments may hold emoji, so talk utf8mb4 for this API
		$this->db->simple_query( "SET NAMES 'utf8mb4'" );
		$this->load->model('sale_comments_mdl');
	}

	private function respond( $status, $payload ){

		$this->output
			->set_status_header( $status )
			->set_content_type( 'application/json', 'utf-8' )
			->set_output( json_encode( $payload ) );
	}

	private function format( $row ){

		return array(
			'id'         => (int) $row->id_sale_comment,
			'id_admin'   => $row->id_admin === NULL ? NULL : (int) $row->id_admin,
			'author'     => $row->author !== NULL ? $row->author : 'Usuario eliminado',
			'comment'    => $row->comment,
			'created_at' => date( 'd-m-Y H:i', strtotime( $row->created_at ) ),
		);
	}

	public function list_by_sale( $id_sale = 0 ){

		if( $this->input->server('REQUEST_METHOD') !== 'GET' ){
			return $this->respond( 405, array( 'type' => 'error', 'message' => 'Método no permitido' ) );
		}

		$id_sale = (int) $id_sale;

		try{
			if( $id_sale <= 0 || !$this->sale_comments_mdl->saleExists( $id_sale ) ){
				return $this->respond( 404, array( 'type' => 'error', 'message' => 'La factura no existe' ) );
			}

			$rows = $this->sale_comments_mdl->getBySale( $id_sale );
		}
		catch( Throwable $e ){
			log_message( 'error', 'Sale_comments::list_by_sale ' . $e->getMessage() );
			return $this->respond( 500, array( 'type' => 'error', 'message' => 'No se pudieron cargar los comentarios' ) );
		}

		$this->respond( 200, array(
			'type'     => 'success',
			'comments' => array_map( array( $this, 'format' ), $rows ),
		));
	}

	public function create( $id_sale = 0 ){

		if( $this->input->server('REQUEST_METHOD') !== 'POST' ){
			return $this->respond( 405, array( 'type' => 'error', 'message' => 'Método no permitido' ) );
		}

		$id_sale = (int) $id_sale;
		// raw on purpose: global_xss_filtering would mangle the text; the UI renders it via .text() (escaped)
		$comment = trim( (string) $this->input->post('comment', FALSE) );
		// normalize Windows line endings so length/storage are consistent
		$comment = str_replace( "\r\n", "\n", $comment );

		if( $comment === '' ){
			return $this->respond( 422, array( 'type' => 'warning', 'message' => 'El comentario no puede estar vacío' ) );
		}

		if( mb_strlen( $comment, 'UTF-8' ) > self::MAX_LENGTH ){
			return $this->respond( 422, array( 'type' => 'warning', 'message' => 'El comentario no puede superar ' . self::MAX_LENGTH . ' caracteres' ) );
		}

		try{
			if( $id_sale <= 0 || !$this->sale_comments_mdl->saleExists( $id_sale ) ){
				return $this->respond( 404, array( 'type' => 'error', 'message' => 'La factura no existe' ) );
			}

			$id = $this->sale_comments_mdl->add( $id_sale, $this->id_admin, $comment );

			if( !$id ){
				throw new Exception( 'insert failed: ' . json_encode( $this->db->error() ) );
			}

			$row = $this->sale_comments_mdl->getOne( $id );
		}
		catch( Throwable $e ){
			log_message( 'error', 'Sale_comments::create ' . $e->getMessage() );
			return $this->respond( 500, array( 'type' => 'error', 'message' => 'No se pudo guardar el comentario. Intente de nuevo.' ) );
		}

		$this->respond( 200, array(
			'type'    => 'success',
			'message' => 'Comentario guardado',
			'comment' => $this->format( $row ),
		));
	}
}
