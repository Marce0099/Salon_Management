<?php
/**
 * Fake Ethernet thermal printer for testing the print queue without hardware.
 *
 *   C:\xampp\php\php.exe tools\fake_printer.php [port=9100] [--silent]
 *
 * Listens on a TCP port like a real printer (raw port 9100), saves every job under tools/fake_printer_out/
 * (job_N.bin = exact bytes, job_N.txt = readable text) and prints the text to the console.
 * In the POS: Impresoras > Nueva impresora > Red, address 127.0.0.1 (or this PC's LAN IP), port 9100.
 * Stop the script (Ctrl+C) to test the "printer off" case; the POS then shows the job as "No impreso".
 */

if( PHP_SAPI !== 'cli' ){ exit("CLI only\n"); }

$port = 9100;
foreach( array_slice($argv, 1) as $a ){ if( ctype_digit($a) ){ $port = (int) $a; } }
$silent = in_array('--silent', $argv, TRUE);

$out = __DIR__.DIRECTORY_SEPARATOR.'fake_printer_out';
if( !is_dir($out) ){ mkdir($out, 0777, TRUE); }

function plain_text($b){

	$esc = array('@' => 0, 'E' => 1, 'G' => 1, '-' => 1, 'a' => 1, 'd' => 1, 'M' => 1, 't' => 1, '2' => 0, '3' => 1, '!' => 1, '{' => 1, 'J' => 1, 'r' => 1, 'p' => 3, '=' => 1, 'S' => 0, 'L' => 0);
	$gs  = array('!' => 1, 'B' => 1, 'H' => 1, 'h' => 1, 'w' => 1, 'f' => 1, 'a' => 1, 'r' => 1);
	$o = ''; $n = strlen($b);

	for( $i = 0; $i < $n; $i++ ){

		$c = ord($b[$i]);

		if( $c === 0x1B && $i + 1 < $n ){
			$cmd = $b[$i + 1];
			if( $cmd === '*' && $i + 4 < $n ){ $i += 4 + (ord($b[$i + 3]) + 256 * ord($b[$i + 4])) * (in_array(ord($b[$i + 2]), array(0, 1), TRUE) ? 1 : 3); }
			else{ $i += 1 + (isset($esc[$cmd]) ? $esc[$cmd] : 0); }
		}
		elseif( $c === 0x1D && $i + 1 < $n ){
			$cmd = $b[$i + 1];
			if( $cmd === 'v' && $i + 7 < $n ){ $i += 7 + (ord($b[$i + 4]) + 256 * ord($b[$i + 5])) * (ord($b[$i + 6]) + 256 * ord($b[$i + 7])); $o .= "[imagen]\n"; }
			elseif( $cmd === 'V' ){ $i += ($i + 2 < $n && ord($b[$i + 2]) >= 65) ? 3 : 2; $o .= "--------- corte ---------\n"; }
			elseif( $cmd === '(' && $i + 4 < $n ){ $i += 4 + ord($b[$i + 3]) + 256 * ord($b[$i + 4]); }
			else{ $i += 1 + (isset($gs[$cmd]) ? $gs[$cmd] : 0); }
		}
		elseif( $c === 0x0A ){ $o .= "\n"; }
		elseif( $c >= 0x20 && $c < 0x7F ){ $o .= $b[$i]; }
	}

	return $o;
}

$srv = @stream_socket_server('tcp://0.0.0.0:'.$port, $errno, $errstr);
if( !$srv ){ exit("Cannot listen on port $port: $errstr\n"); }

echo "Fake printer listening on port $port. Jobs are saved in $out\n";
$job = 0;

while( TRUE ){

	$conn = @stream_socket_accept($srv, 3600);
	if( !$conn ){ continue; }

	stream_set_timeout($conn, 2);
	$data = '';
	while( !feof($conn) ){
		$chunk = fread($conn, 8192);
		if( $chunk === FALSE || $chunk === '' ){ $meta = stream_get_meta_data($conn); if( $meta['timed_out'] || feof($conn) ){ break; } continue; }
		$data .= $chunk;
	}
	fclose($conn);

	if( $data === '' ){ continue; } // a plain connection test sends nothing

	$job++;
	$stamp = date('Ymd_His').'_'.$job;
	file_put_contents($out.DIRECTORY_SEPARATOR.'job_'.$stamp.'.bin', $data);
	$text = plain_text($data);
	file_put_contents($out.DIRECTORY_SEPARATOR.'job_'.$stamp.'.txt', $text);

	echo '[', date('H:i:s'), "] job #$job: ", strlen($data), " bytes\n";
	if( !$silent ){ echo $text, "\n"; }
}
