<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Configuration values (database, mail, encryption key). Contract in .claude/docs/configuration.md, shared with the
 * future Rust/Tauri backend; its cases live in tools/env_cases.json (php tools/test_env.php).
 *
 * NEVER use putenv() here or anywhere else: on Windows Apache runs requests as threads of ONE process, the process
 * environment is shared by all of them and PHP undoes a request's putenv() when it ends, so a finishing request erased
 * the variables another one was about to read ("Access denied for user ''@'localhost'"). Values stay in this request's
 * memory instead.
 *
 * Resolution: real environment variable (non-blank) > .env file > default.
 */

/** Parses .env text into array(key => value). Pure. */
function env_parse($text){

	$out = array();
	$text = preg_replace('/^\xEF\xBB\xBF/', '', (string) $text);

	foreach( preg_split('/\r\n|\n|\r/', $text) as $line ){

		$line = trim($line);

		if( $line === '' || $line[0] === '#' || $line[0] === ';' ){ continue; }

		$eq = strpos($line, '=');
		if( $eq === FALSE ){ continue; }

		$key = trim(substr($line, 0, $eq));
		if( !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) ){ continue; }

		$value = trim(substr($line, $eq + 1));
		$len = strlen($value);

		// one pair of surrounding quotes is removed; everything else is literal (no escapes, no inline comments)
		if( $len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0] ){
			$value = substr($value, 1, -1);
		}

		$out[$key] = $value;
	}

	return $out;
}

/** The value of a configuration key for this request. */
function app_env($key, $default = ''){

	static $file = NULL;

	// reading the process environment is safe; only writing it is not
	$real = getenv($key);
	if( $real !== FALSE && trim($real) !== '' ){ return $real; }

	if( $file === NULL ){
		$path = FCPATH.'.env';
		$text = is_file($path) ? file_get_contents($path) : FALSE;
		$file = $text === FALSE ? array() : env_parse($text);
	}

	return isset($file[$key]) ? $file[$key] : $default;
}
