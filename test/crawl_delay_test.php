<?php
	// load file
	require_once(__DIR__ . '/../robotstxtparser.php');

	// prepare sample data
	$data = "
		User-Agent: AhrefsBot
		Crawl-Delay: 1.5
	";

	// init parser
	$parser = new RobotsTxtParser($data);

	// do assertions
	assert(isset($parser->rules['ahrefsbot']));
	assert(isset($parser->rules['ahrefsbot']['crawl-delay']));
	assert($parser->rules['ahrefsbot']['crawl-delay'] == "1.5");

	echo "OK\n";