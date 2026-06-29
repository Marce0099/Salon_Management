<?php if(!defined('BASEPATH')){ exit(); }

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MY_PHPMailer{

	private $phpmailer;

	public function __construct(){

		//require_once('PHPMailer/PHPMailerAutoload.php');
		require_once('PHPMailer/PHPMailer.php');
		require_once('PHPMailer/SMTP.php');
		require_once('PHPMailer/Exception.php');

		//$this->phpmailer = new PHPMailer;
		$this->phpmailer = new PHPMailer(true);

		try {

			$this->phpmailer->isSMTP();                                    
			$this->phpmailer->Host = 'smtp.gmail.com';  
			$this->phpmailer->SMTPAuth = true;                              
			$this->phpmailer->Username = 'conceptosvp@gmail.com';               
			$this->phpmailer->Password = 'REDACTED-LEAKED-CREDENTIAL';                       
			$this->phpmailer->SMTPSecure = 'tls';
			$this->phpmailer->Port = 587;
			$this->phpmailer->isHTML(true);
			$this->phpmailer->CharSet = 'UTF-8';
			$this->phpmailer->setFrom('noreply@conceptos.com.ni', 'Conceptos\' Mailer');

		} catch (Exception $e) {
            log_message('error', 'PHPMailer init error: ' . $e->getMessage());
        }
		/*$this->phpmailer->isSMTP();                                    
		$this->phpmailer->Host = 'localhost';  
		$this->phpmailer->SMTPAuth = true;                              
		$this->phpmailer->Username = 'noreply@empleaconacee.org';               
		$this->phpmailer->Password = 'REDACTED-LEAKED-CREDENTIAL';                       
		$this->phpmailer->SMTPSecure = 'tls';
		$this->phpmailer->Port = 587;
		$this->phpmailer->isHTML(true);
		$this->phpmailer->CharSet = 'UTF-8';

		$this->phpmailer->setFrom('noreply@empleaconacee.org', 'Jornadas CEDDD');*/
	}

	public function toOwner(  ){
		$this->phpmailer->addAddress('REDACTED_EMAIL');
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

		$fp = fopen('/var/log/app/mail.log', 'a');

		$sent = $this->phpmailer->send();

		if( !$sent ) {

			$log = PHP_EOL.'--------Mensaje no enviado('.$to.' / '.date('Y-m-d H:i').')----------'.PHP_EOL;
			$log .= $this->phpmailer->ErrorInfo;
			fwrite($fp, $log);
		}
		else{

		    $log = PHP_EOL.'--------Mensaje enviado('.$to.' / '.date('Y-m-d H:i').')----------'.PHP_EOL;
		    $log .= strip_tags($message);
			fwrite($fp, $log);
		}

		fclose($fp);

		$this->phpmailer->ClearAllRecipients();
		
	}

	public function close(){

		$this->phpmailer->SmtpClose();
	}
}