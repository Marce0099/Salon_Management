<?php if( !defined('BASEPATH') ){ exit(); }

$config['mailtype']    = 'html';
$config['protocol']    = 'smtp';
$config['smtp_host']   = app_env('EMAIL_HOST');
$config['smtp_port']   = app_env('EMAIL_PORT');
$config['smtp_user']   = app_env('EMAIL_USERNAME');
$config['smtp_pass']   = app_env('EMAIL_PASSWORD');
$config['smtp_secure'] = 'tls';
$config['from_email']  = app_env('EMAIL_FROM');
$config['from_name']   = app_env('EMAIL_FROM_NAME');
