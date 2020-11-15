<?php
class Zipstream {
	/*
	 * PhpZipStream. Simple and fast.
	 * Made by Luc Gommans. Works in PHP 5.4 and newer (tested until and including 7.3).
	 * https://github.com/lgommans/PhpZipStream
	 *
	 * Minimal example
	 * ===============
	 *
	 * // For other examples, see examples.php
	 *
	 * require('zipstream.php');
	 *
	 * $readmeData = 'Thank you for reading me.';
	 *
	 * $z = new Zipstream();
	 * $z->addString($readmeData, 'readme.txt');
	 * $z->finish(); // Zip format requires some data at the end
	 *
	 *
	 * License
	 * =======
	 *
	 * Copyright Luc Gommans, 2020
	 *
	 * This code is free software, licensed under the simplified BSD license:
	 *
	 *  Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
	 *
	 *   - Redistributions of source code must retain the above copyright notice and this list of conditions.
	 *   - Redistributions in binary form must reproduce the above copyright notice and the project name (PhpZipStream) in the documentation.
	 *
	 */

	private $centralDirectory = [];
	private $byteOffset = 0;
	private $outFunction = '';

	static function leadingZeros($n, $zeros) {
		return str_repeat('0', $zeros - strlen($n)) . $n;
	}

	static function swapEndianness($hex) {
		// 0x04034b50 -> 0x504b0304
		// From: http://stackoverflow.com/a/7548355
		return implode('', array_reverse(str_split($hex, 2)));
	}

	static function unixTimestamp2MSDOShex($unixtime) {
		list($year, $month, $day, $hour, $minute, $second) = array_map('intval', explode(' ', date('Y m d H i s', $unixtime)));

		if ($year < 1980) {
			// unix timestamps can go negative, or at least until 1970-01-01, but MS DOS only goes until 1980 so let's reset the date to all
			// zeroes (so also month and day zero, which don't exist) instead of resetting only the year and giving a false sense of accuracy.
			list($year, $month, $day, $hour, $minute, $second) = [1980, 0, 0,  0, 0, 0];
		}

		$bintime = self::leadingZeros(decbin($year - 1980), 7)
			. self::leadingZeros(decbin($month), 4)
			. self::leadingZeros(decbin($day), 5)
			. self::leadingZeros(decbin($hour), 5)
			. self::leadingZeros(decbin($minute), 6)
			. self::leadingZeros(decbin($second / 2), 5);
		$hextime = dechex(bindec($bintime));
		return self::swapEndianness(self::leadingZeros($hextime, 8));
	}

	function __construct($outFunction="print") {
		// Optionally pass a function to call for outputting data instead of print
		$this->outFunction = $outFunction;
	}

	function print($data) {
		$x = $this->outFunction;
		if ($x == 'print') {
			print($data); // For some reason print() is not a function so we need to manually call the print() function...
		}
		else {
			$x($data);
		}
	}

	function outputFileHeader($dataLength, $output_filename, $unixtime, $crc32) {
		$size = self::swapEndianness(self::leadingZeros(dechex($dataLength), 8));
		$crc32 = self::swapEndianness($crc32);
		$header_hex = '0a00' // minimum version to extract: 1
			. '0000' // general purpose flag (indicates encryption and compression options)
			. '0000' // no compression
			. self::unixTimestamp2MSDOShex($unixtime) // date and time in MS DOS format.
			. $crc32
			. $size // compressed size
			. $size // uncompressed size
			. self::swapEndianness(self::leadingZeros(dechex(strlen($output_filename)), 4)) // filename length
			. '0000'; // "extra field" length

		$this->print(hex2bin('504b0304' . $header_hex));
		$this->print($output_filename);

		$this->centralDirectory[] = [
			'header' => $header_hex,
			'offset' => $this->byteOffset,
			'fname' => $output_filename
		];
		$this->byteOffset += $dataLength + strlen($output_filename) + 4 /* magic */ + strlen($header_hex) / 2;
	}

	function outputFile($real_filename, $output_filename=NULL, $unixtime=NULL) {
		/*
		 * $real_filename   the name on disk
		 * $output_filename the name you want in your zip file (default: the $real_filename)
		 * $unixtime        the "last modified" value as unix timestamp (default: time from file on disk)
		 *
		 * Basic example:
		 *    $zipstream->outputFile('statement-draft19.pdf', 'statement.pdf');
		 * Set "last modified" to right now:
		 *    $zipstream->outputFile('statement-draft19.pdf', 'statement.pdf', time());
		 */

		if ($output_filename === NULL) {
			$output_filename = $real_filename;
		}

		if ($unixtime === NULL) {
			$unixtime = filemtime($real_filename);
			if ($unixtime === false) { // Getting the mtime failed, reset to the current time
				$unixtime = time();
			}
		}

		$filesize = filesize($real_filename);
		$crc32 = hash_file('crc32b', $real_filename);
		$this->outputFileHeader($filesize, $output_filename, $unixtime, $crc32);

		$i = 0;
		$blocksize = 1024 * 1024 * 2;
		$fid = fopen($real_filename, 'r');
		while ($i < $filesize) {
			$this->print(fread($fid, $blocksize));
			$i += $blocksize;
		}
		fclose($fid);
	}

	function addStream($data, $output_filename, $unixtime=NULL) {
		/*
		 * $data            the file data
		 * $output_filename the name you want in your zip file
		 * $unixtime        the "last modified" value as unix timestamp (default: now)
		 *
		 * Basic example:
		 *    $zipstream->addStream(file_get_contents('filename.jpg'), 'example.jpg');
		 * Set "last modified" to 3am today:
		 *    $zipstream->addStream("Hello, World!", 'readme.txt', mktime(3));
		 */

		if ($unixtime === NULL) {
			$unixtime = time();
		}

		$crc32 = hash('crc32b', $data);
		$this->outputFileHeader(strlen($data), $output_filename, $unixtime, $crc32);
		$this->print($data);
	}

	function addString($data, $output_filename, $unixtime=NULL) {
		// Alias for addStream
		return $this->addStream($data, $output_filename, $unixtime);
	}

	function addFile($real_filename, $output_filename=NULL, $unixtime=NULL) {
		// Alias for outputFile
		return $this->outputFile($real_filename, $output_filename, $unixtime);
	}

	function close() {
		// Alias
		$this->outputCentralDirectory();
	}

	function finish() {
		// Alias
		$this->outputCentralDirectory();
	}

	function finalize() {
		// Alias
		$this->outputCentralDirectory();
	}

	function outputCentralDirectory() {
		// Outputs the "central directory" (which is a list of all files), which is at the end of each zip file

		$centralDirectorySize = 0;

		foreach ($this->centralDirectory as $entry) {
			$output = '';
			$output .= hex2bin('504b0102'
				. '1e03' // version made by
				. $entry['header']
				. '0000' // comment length
				. '0000' // disk number start
				. '0000' // internal file attributes
				. '0000ed81' // external file attributes (I have no idea what ed81 means)
				. self::swapEndianness(self::leadingZeros(dechex($entry['offset']), 8))
			);
			$output .= $entry['fname'];
			$this->print($output);
			$centralDirectorySize += strlen($output);
		}

		$this->print(hex2bin('504b0506' // magic (EOCD)
			. '00000000' // some disk stuff
			. self::swapEndianness(self::leadingZeros(dechex(count($this->centralDirectory)), 4)) // entries in CD on disk
			. self::swapEndianness(self::leadingZeros(dechex(count($this->centralDirectory)), 4)) // total entries in CD
			. self::swapEndianness(self::leadingZeros(dechex($centralDirectorySize), 8)) // CD size
			. self::swapEndianness(self::leadingZeros(dechex($this->byteOffset), 8)) // offset of start of CD on disk
			. '0000' // comment length
		));
	}

	static function filesize($fileCount, $totalFilenameBytes, $totalDataSize) {
		// Calculate the filesize that your zip will have
		// Example:
		// $numberOfFiles = 2;
		// $filenameBytes = strlen("a.txt") + strlen("b.txt");
		// $dataSize = filesize("a.txt") + filesize("b.txt");
		// $size = filesize($numberOfFiles, $filenameBytes, $dataSize);
		// header('Content-Length: ' . $size);

		$fixedHeaderSize = 2+2+2+4+4+4+4+2+2;
		$localHeaderSize = 4+$fixedHeaderSize; // +filename length
		$centralDirectoryEntrySize = 4+2+$fixedHeaderSize+2+2+2+4+4; // +filename length
		$EOCDSize = 4+4+2+2+4+4+2;

		// $filenameLengths times two because they appear in both the local header and central directory entry
		return $fileCount * $localHeaderSize + $fileCount * $centralDirectoryEntrySize + $totalFilenameBytes * 2 + $totalDataSize + $EOCDSize;
	}
}

