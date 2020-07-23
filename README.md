PhpZipStream
============

Output a zip file as a stream of data, without keeping the whole zip file in
memory or writing it temporarily to disk.

It is very small and simple; but also very fast, lightweight, and easy to use.

	require('zipstream.php');

	$z = new Zipstream();

	// Output the file "statement-draft19.pdf",
	// but call it "statement.pdf" in the zip.
	$z->outputFile('statement-draft19.pdf', 'statement.pdf');

	$z->finish();

It doesn't do compression because most large files that we use (jpg, mp4, pdf,
mp3, etc.) are already compressed and this makes it a very clean implementation.

Documentation is included at the top of [`zipstream.php`](zipstream.php). Since
it's so simple to use, the short example and comments there show everything you
need.


Decoder Compatibility
=====================

It's tricky to say which decoders will be compatible because there is a huge
amount of legacy in the official zip file format documentation. The spec even
defines some fields as "we don't know what this field does, it's
platform-specific, just fill it in yourself".

If everything went well, the output should be compatible with *all* decoders,
even including the original one for MS DOS (I think that's the oldest, from
1989).

I documented the basic ZIP file format in a blog post. For the shortest ZIP
file format reference you will ever read, see
[lgms.nl/blog-8](https://lgms.nl/blog-8).


Wishlist
========

Things that could be improved:

- Don't require a second argument for `outputFile($local_filename, $output_filename)` but instead use the original (local) filename if the second is not given
- Including the correct date and time in the zip file
- Automated tests could use expansion
- Maybe the filesize function should be made easier to use
