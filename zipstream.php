<?php 
class Zipstream {
	/*
	 * Made by Luc Gommans. Works in PHP 5.4 and newer.
	 *
	 * EXAMPLE
	 * =======
	 *
	 * require('zipstream.php');
	 *
	 * $size = Zipstream::filesize(1, strlen('statement.pdf'), filesize('statement-draft19.pdf'));
	 *
	 * header('Content-Length: ' . $size);
	 * header('Content-Type: application/zip');
	 * header('Content-Disposition: attachment; filename="statement-in-a-zip.zip"');
	 *
	 * $z = new Zipstream();
	 * // Output the file "statement-draft19.pdf", but call it "statement.pdf" in the zip.
	 * $z->outputFile('statement-draft19.pdf', 'statement.pdf');
	 * $z->finish(); // This is (the only thing) required for a valid zip file.
	 *
	 *
	 * LICENSE
	 * =======
	 *
	 * This code is free software, licensed under the simplified BSD license:
	 *
	 *  Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
	 *
	 *   - Redistributions of source code must retain the above copyright notice and this list of conditions.
	 *   - Redistributions in binary form must reproduce the above copyright notice in the documentation.
	 *
	 */

	private $centralDirectory = [];
	private $byteOffset = 0;

	private static function leadingZeros($n, $zeros) {
		return str_repeat('0', $zeros - strlen($n)) . $n;
	}

	private static function swapEndianness($hex) {
		// 0x04034b50 -> 0x504b0304
		// From: http://stackoverflow.com/a/7548355
		return implode('', array_reverse(str_split($hex, 2)));
	}

	function outputFile($real_filename, $output_filename) {
		// The $real_filename is the name on disk. The $output_filename is the name you want it to have in your zip file.
		// Example: $zipstream->outputFile('statement-draft19.pdf', 'statement.pdf');

		$filesize = filesize($real_filename);
		$size = self::swapEndianness(self::leadingZeros(dechex($filesize), 8));
		$crc32 = self::swapEndianness(hash_file('crc32b', $real_filename));
		$header_hex = '0a00' // minimum version to extract: 1 (no idea what this encoding is, but other encoders do it this way and decoders recognize it)
			. '0000' // general purpose flag (indicates encryption and compression options)
			. '0000' // no compression (since they're already zip files)
			. '00000000' // date and time in MS DOS format.
			. $crc32
			. $size // compressed size
			. $size // uncompressed size
			. self::swapEndianness(self::leadingZeros(dechex(strlen($output_filename)), 4)) // filename length
			. '0000'; // extra field length

		echo hex2bin('504b0304' . $header_hex);

		echo $output_filename;

		$i = 0;
		$blocksize = 1024 * 1024 * 2;
		$fid = fopen($real_filename, 'r');
		while ($i < $filesize) {
			echo fread($fid, $blocksize);
			$i += $blocksize;
		}
		fclose($fid);

		$this->centralDirectory[] = ['header' => $header_hex,
			'offset' => $this->byteOffset,
			'fname' => $output_filename];
		$this->byteOffset += $filesize + strlen($output_filename) + 4 /* magic */ + strlen($header_hex) / 2;
	}

	function addFile($real_filename, $output_filename) {
		// Alias
		return $this->outputFile($real_filename, $output_filename);
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
			echo $output;
			$centralDirectorySize += strlen($output);
		}

		echo hex2bin('504b0506' // magic (EOCD)
			. '00000000' // some disk stuff
			. self::swapEndianness(self::leadingZeros(dechex(count($this->centralDirectory)), 4)) // entries in CD on disk
			. self::swapEndianness(self::leadingZeros(dechex(count($this->centralDirectory)), 4)) // total entries in CD
			. self::swapEndianness(self::leadingZeros(dechex($centralDirectorySize), 8)) // CD size
			. self::swapEndianness(self::leadingZeros(dechex($this->byteOffset), 8)) // offset of start of CD on disk
			. '0000' // comment length
		);
	}

	static function filesize($fileCount, $totalFilenameBytes, $totalDataSize) {
		// Calculate the filesize that your zip will have
		// Example:
		// $filenameBytes = strlen("a.txt") + strlen("b.txt");
		// $dataSize = filesize("a.txt") + filesize("b.txt");
		// $size = filesize(2, $filenameBytes, $dataSize);
		// header('Content-Length: ' . $size);

		$fixedHeaderSize = 2+2+2+4+4+4+4+2+2;
		$localHeaderSize = 4+$fixedHeaderSize; // +filename length
		$centralDirectoryEntrySize = 4+2+$fixedHeaderSize+2+2+2+4+4; // +filename length
		$EOCDSize = 4+4+2+2+4+4+2;

		// $filenameLengths times two because they appear in both the local header and central directory entry
		return $fileCount * $localHeaderSize + $fileCount * $centralDirectoryEntrySize + $totalFilenameBytes * 2 + $totalDataSize + $EOCDSize;
	}

	static function runTests($tmpdir) {
		// These tests could be improved a lot.
		// Note that they must be run from a web server for ob_start to work.
		// Returns true on success, string on failure

		$fid = fopen("$tmpdir/phpzipstreamtest", 'w');
		fwrite($fid, str_repeat('a', 314));
		fclose($fid);

		$z = new self();
		ob_start();
		$z->outputFile("$tmpdir/phpzipstreamtest", 'test.txt');
		$z->outputCentralDirectory();
		$result = ob_get_contents();
		if (strlen($result) != self::filesize(1, strlen('test.txt'), 314)) {
			unlink("$tmpdir/phpzipstreamtest");
			return "output unexpected size or size calculation incorrect";
		}
		ob_end_clean();

		unlink("$tmpdir/phpzipstreamtest");

		return true;
	}
}

