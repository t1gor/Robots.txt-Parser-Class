<?php
require_once(__DIR__ . '/../robotstxtparser.php');
$data = "
User-Agent: AhrefsBot
Crawl-Delay: 1.5
";
$parser = new robotstxtparser($data, 'UTF-8');
assert(isset($parser->rules['ahrefsbot']));
assert(isset($parser->rules['ahrefsbot']['crawl-delay']));
assert($parser->rules['ahrefsbot']['crawl-delay'] == "1.5");
echo "OK\n";