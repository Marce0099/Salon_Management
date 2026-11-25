<?php
/**
 * Checks what the web server hands out (rules in the root .htaccess). Run with Apache started:
 *
 *   C:\xampp\php\php.exe tools\test_web_access.php [http://localhost]
 */

if( PHP_SAPI !== 'cli' ){ exit("CLI only\n"); }

$base = rtrim(isset($argv[1]) ? $argv[1] : 'http://localhost', '/');
$root = dirname(__DIR__);

function status($url){

	$ctx = stream_context_create(array('http' => array('ignore_errors' => TRUE, 'follow_location' => 0, 'timeout' => 20),
		'ssl' => array('verify_peer' => FALSE, 'verify_peer_name' => FALSE)));
	$http_response_header = array();
	@file_get_contents($url, FALSE, $ctx);

	return isset($http_response_header[0]) && preg_match('/ (\d{3})/', $http_response_header[0], $m) ? (int) $m[1] : 0;
}

$private = array(
	'.env', '.ENV', '.env.example', 'ENV~1', '.env/x', '.git/config', '.git/HEAD', 'GIT~1/config', '.gitignore', '.htaccess',
	'.claude/docs/audit_events.md', 'CLAUDE.md', 'claude.md', 'README.md', 'LICENSE', 'coco_cosmetics2.sql', 'php.rar',
	'applications.html', 'bitnami.css', 'migrations/', 'migrations/restaurant_030_print_queue.sql', 'tools/', 'tools/env_cases.json',
	'tools/test_env.php', 'docs/audit_event.schema.json', 'agent/PosPrintAgent.ps1', 'agent/agent.json', 'application/config/env.php',
	'APPLICATION/config/database.php', 'system/core/CodeIgniter.php', 'print/autoload.php', 'includes/', 'dashboard/phpinfo.php',
	'dashboard/', 'xampp/', 'img/', 'assets/', 'uploads/', 'uploads/receipts/', 'orders_pdf/', 'assets/../.env', 'assets/%2e%2e/.env',
	'assets/gentelella/vendors/bootstrap-wysiwyg/examples/php/upload.php',
);

// public: one existing file of each kind the app serves, plus application routes
$public = array('' => array(200, 301, 302, 303, 307), 'login' => array(200), 'index.php/login' => array(200),
	'floor' => array(200, 302, 303, 307), 'assets/css/mobile.css' => array(200), 'assets/js/money.js' => array(200),
	'assets/img/logo_invoice.png' => array(200));
foreach( array('uploads/receipts/*.pdf', 'uploads/logos/*.*', 'assets/img/*.jpg') as $glob ){
	$found = glob($root.'/'.$glob);
	if( $found ){ $public[str_replace('\\', '/', substr($found[0], strlen($root) + 1))] = array(200); }
}

$failed = 0;

foreach( $private as $p ){
	$s = status($base.'/'.$p);
	// 400/403/404 all mean "not handed out"
	$ok = in_array($s, array(400, 403, 404), TRUE);
	if( !$ok ){ $failed++; }
	echo $ok ? 'ok    ' : 'FAIL  ', 'private ', $s, '  ', $p, "\n";
}

foreach( $public as $p => $allowed ){
	$s = status($base.'/'.str_replace(' ', '%20', $p));
	$ok = in_array($s, $allowed, TRUE);
	if( !$ok ){ $failed++; }
	echo $ok ? 'ok    ' : 'FAIL  ', 'public  ', $s, '  /', $p, "\n";
}

echo $failed ? "$failed FAILED\n" : "All passed\n";
exit($failed ? 1 : 0);
