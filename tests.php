<?php

require('zipstream.php');

$tmpdir = '/tmp';
$fsize = 314; // test file size

if (Zipstream::swapEndianness('04034b50') != '504b0304') {
	die('swapEndianness not working as expected.');
}

$testfname = "$tmpdir/phpzipstreamtest";
$fid = fopen($testfname, 'w');
fwrite($fid, str_repeat('a', $fsize));
fclose($fid);

function processZipDataFunction($data) {
	// This function is called many times with small chunks of data, while the zip is being generated
	global $result;
	$result .= $data;
}

$result = '';
$z = new Zipstream('processZipDataFunction');
$z->addFile($testfname);
$z->addFile($testfname, NULL, -1);
$z->addFile($testfname, '2006.txt', mktime(15, 40, 57, 10, 11, 2006)); // set modified time to 2006-10-11T15:40:57
$z->addStream('Thank you for reading me.', 'readme.txt'); // Output a string as a file (filename: "readme.txt", last modified: now).
$z->finish();

$result = '';
$z = new Zipstream('processZipDataFunction');
$z->outputFile($testfname, 'test.txt', time());
$z->outputCentralDirectory();
if (strlen($result) != Zipstream::filesize(1, strlen('test.txt'), $fsize)) {
	unlink($testfname);
	die('Output unexpected size or size calculation incorrect');
}

unlink($testfname);

die('All tests have been run.');

