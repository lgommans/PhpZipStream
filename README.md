PhpZipStream
============

Output a zip file as a stream of data, without keeping the whole zip file in
memory or writing it temporarily to disk.

It is very small and simple; but also very fast, lightweight, and easy to use.

Deploy only `zipstream.php`, zero dependencies.

	require('zipstream.php');

	$z = new Zipstream();

	// Output the file "statement-draft19.pdf",
	// but call it "statement.pdf" in the zip.
	$z->outputFile('statement-draft19.pdf', 'statement.pdf');

	$z->finish();

It doesn't do compression because large files (jpg, mp4, pdf, mp3, odt, etc.)
are already compressed. This makes it a very clean implementation.

Because it's so easy to use, a few examples have all the info,
see [examples.php](examples.php).


Decoder Compatibility
=====================

If everything went well, it should be compatible with *all* decoders, even
including the original one for MS DOS (I think that's the oldest, from 1989).

However, it's tricky to say because there is a large amount of legacy in the
official zip file format documentation. The spec even defines some fields as
"we don't know what this field does, it's platform-specific, just fill it in
yourself".

I documented the basic ZIP file format in a blog post. For the shortest ZIP
file format reference you will ever read, see
[lgms.nl/blog-8](https://lgms.nl/blog-8).


Wishlist
========

- Automated tests could use expansion (also parse it, e.g. with PHP's built-in parser)
- Maybe the filesize calculation function could be made easier to use
