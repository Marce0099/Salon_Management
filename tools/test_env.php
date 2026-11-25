<?php
/**
 * Checks the .env contract (application/config/env.php) against tools/env_cases.json, which the Rust backend can run too.
 *
 *   C:\xampp\php\php.exe tools\test_env.php
 */

if( PHP_SAPI !== 'cli' ){ exit("CLI only\n"); }

define('BASEPATH', 'cli');
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'env_test_'.getmypid().DIRECTORY_SEPARATOR;
mkdir($tmp);
define('FCPATH', $tmp);
require __DIR__.'/../application/config/env.php';

$failed = 0;
function check($name, $ok){

	global $failed;
	echo $ok ? 'ok    ' : 'FAIL  ', $name, "\n";
	if( !$ok ){ $failed++; }
}

foreach( json_decode(file_get_contents(__DIR__.'/env_cases.json'), TRUE) as $case ){
	check($case['name'], env_parse($case['text']) === $case['expect']);
}

// resolution: real environment (non-blank) > .env > default
file_put_contents($tmp.'.env', "ENVT_FILE=from-file\nENVT_BOTH=from-file\nENVT_BLANK=from-file\n");
putenv('ENVT_BOTH=from-env');   // allowed here: single-threaded command line
putenv('ENVT_BLANK=   ');
check('file value', app_env('ENVT_FILE') === 'from-file');
check('environment wins over file', app_env('ENVT_BOTH') === 'from-env');
check('blank environment value is ignored', app_env('ENVT_BLANK') === 'from-file');
check('default when missing', app_env('ENVT_NOPE', 'dflt') === 'dflt');
check('process environment untouched by loading', getenv('ENVT_FILE') === FALSE);

unlink($tmp.'.env');
rmdir($tmp);

echo $failed ? "$failed FAILED\n" : "All passed\n";
exit($failed ? 1 : 0);
