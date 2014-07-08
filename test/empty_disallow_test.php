<?php
require_once(__DIR__ . '/../robotstxtparser.php');
$data = "
User-Agent: *
Disallow: 
Disallow: /foo
Disallow: /bar
";
$parser = new robotstxtparser($data, 'UTF-8');
assert(!$parser->isDisallowed("/peanuts"));
assert($parser->isDisallowed("/foo"));
echo "OK\n";
