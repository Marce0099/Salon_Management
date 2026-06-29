<?php if(!defined('BASEPATH')){ exit(); }

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MY_PHPMailer{

	private $phpmailer;

	public function __construct(){

		require_once('PHPMailer/PHPMailer.php');
		require_once('PHPMailer/SMTP.php');
		require_once('PHPMailer/Exception.php');

		$this->phpmailer = new PHPMailer(true);

		$CI =& get_instance();
		$CI->config->load('email');

		try {

			$this->phpmailer->isSMTP();
			$this->phpmailer->Host       = $CI->config->item('smtp_host');
			$this->phpmailer->SMTPAuth   = true;
			$this->phpmailer->Username   = $CI->config->item('smtp_user');
			$this->phpmailer->Password   = $CI->config->item('smtp_pass');
			$this->phpmailer->SMTPSecure = 'tls';
			$this->phpmailer->Port       = $CI->config->item('smtp_port');
			$this->phpmailer->isHTML(true);
			$this->phpmailer->CharSet    = 'UTF-8';
			$this->phpmailer->setFrom(
				$CI->config->item('from_email'),
				$CI->config->item('from_name')
			);

		} catch (Exception $e) {
            log_message('error', 'PHPMailer init error: ' . $e->getMessage());
        }
	}

	public function toOwner(){
		$this->phpmailer->addAddress(getenv('OWNER_EMAIL'));
	}

	public function sendCloseCash( $email_html ){

		$this->toOwner();
		$this->phpmailer->Subject = 'Reporte de cierre de caja';
		$this->phpmailer->Body = $email_html;
		$this->phpmailer->send();
	}

	public function sendOpenCash( $email_html ){

		$this->toOwner();
		$this->phpmailer->Subject = 'Se ha abierto caja';
		$this->phpmailer->Body = $email_html;
		$this->phpmailer->send();
	}

	public function AddAttachment( $path ){

		$this->phpmailer->AddAttachment($path);
	}

	public function sendMail( $to, $subject, $message ){

		$this->phpmailer->addAddress($to);
		$this->phpmailer->Subject = $subject;
		$this->phpmailer->Body = $message;

		$sent = $this->phpmailer->send();

		if( !$sent ){
			log_message('error', 'PHPMailer send failed to ' . $to . ': ' . $this->phpmailer->ErrorInfo);
		} else {
			log_message('info', 'PHPMailer sent to ' . $to);
		}

		$this->phpmailer->ClearAllRecipients();

	}

	public function close(){

		$this->phpmailer->SmtpClose();
	}
}
