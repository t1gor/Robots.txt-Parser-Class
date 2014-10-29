<?php
	// load file
	require_once(__DIR__ . '/../robotstxtparser.php');

	// prepare sample data
	$data = "
		User-Agent: *
		Disallow: /url_containing_@_symbol
	";

	// init parser
	$parser = new RobotsTxtParser($data);

	// do assertions
	assert(!$parser->isDisallowed("/peanuts"));
	assert($parser->isDisallowed("/url_containing_@_symbol"));

	echo "OK\n";