<?php

require('zipstream.php');


//// ---------
//// Example 1: Output a string, as a file called "readme.txt", in a zip.

$readmeData = 'Thank you for reading me.';

$z = new Zipstream();
$z->addString($readmeData, 'readme.txt');
$z->finish(); // Zip format requires some data at the end


//// ---------
//// Example 2: Typical use-case. This outputs:
////            - a file,
////            - HTTP headers for the MIME type and zip's filename, and
////            - size calculation as Content-Length header so the browser can show a progress bar.

$numberOfFiles = 1;
$filenamesLength = strlen('statement.pdf');
$totalDataLength = filesize('statement-draft19.pdf');

// The content length is optional. It allows the browser to show a progress bar.
$size = Zipstream::filesize($numberOfFiles, $filenamesLength, $totalDataLength);
header('Content-Length: ' . $size);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="statement.zip"');

// Use the file "statement-draft19.pdf", but call it "statement.pdf" in the zip.
$z = new Zipstream();
$z->addFile('statement-draft19.pdf', 'statement.pdf');
$z->finish();

// The "last modified" time will be the real one (i.e. the one from the file on disk).
// To use a different value, such as the current time, add a parameter:
//    $z->addFile('statement-draft19.pdf', 'statement.pdf', time());


//// ---------
//// Example 3: Put "readme.txt" in the "example/" directory, and process the zip data in a callback function

$whole_zip = '';
function processZipDataFunction($data) {
	// This function is called many times with small chunks of data, while the zip is being generated
	global $whole_zip;
	$whole_zip .= $data;

	// You can also do other stuff like
	//    socket_write($mysocket, $data);
	// or
	//    fwrite($myfile, $data);
}

$readmeData = 'Thank you for reading me.';
$yesterday = time() - (3600 * 24); // Set the "last modified" value to the current time but yesterday

$z = new Zipstream('processZipDataFunction');
$z->addString($readmeData, 'example/readme.txt', $yesterday);
$z->finish();

// All data is now contained in $whole_zip, so you can do stuff like:
hash_hmac('sha256', $whole_zip, 'secret');


