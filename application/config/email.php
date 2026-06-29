<?php if( !defined('BASEPATH') ){ exit(); }

$config['mailtype']    = 'html';
$config['protocol']    = 'smtp';
$config['smtp_host']   = getenv('EMAIL_HOST');
$config['smtp_port']   = getenv('EMAIL_PORT');
$config['smtp_user']   = getenv('EMAIL_USERNAME');
$config['smtp_pass']   = getenv('EMAIL_PASSWORD');
$config['smtp_secure'] = 'tls';
$config['from_email']  = getenv('EMAIL_FROM');
$config['from_name']   = getenv('EMAIL_FROM_NAME');
