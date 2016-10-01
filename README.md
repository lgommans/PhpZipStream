PhpZipStream
============

Output a zip file as a stream of data, without keeping the whole zip file in
memory or writing it temporarily to disk.

It is very small and simple but also very fast, lightweight and easy to use.

    require('zipstream.php');

	$z = new Zipstream();

	// Output the file "statement-draft19.pdf",
	// but call it "statement.pdf" in the zip.
	$z->outputFile('statement-draft19.pdf', 'statement.pdf');

	$z->finish();


Compatibility
=============

Tricky to say, given the huge amount of legacy crap in the official zip file
format documentation. The spec even says "oh we don't know, it's
platform-specific so fill it in yourself" for some things.

If everything went well, it should be compatible with *all* decoders, even
including the original one for MS DOS (I think that's the oldest, from 1989).

The minimal ZIP file format has been documented in a blog post. For the
shortest ZIP file format reference you will ever read, see
[lgms.nl/blog-8](https://lgms.nl/blog-8).

Wishlist
========

It seems to be bug-free (easy to say over such a small project), but there are
things that can be improved:

- Writing the correct date and time should probably be implemented.
- Automated tests could use expansion.
- And perhaps the filesize function should be made easier to use.

