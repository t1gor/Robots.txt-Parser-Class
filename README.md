Robots.txt php parser class
=====================

Php class to parse robots.txt rules according to Google & Yandex specifications.

Example:
```php
<?php
	// lib import
	require_once('robotstxtparser.php');
	$parser = new robotstxtparser(file_get_contents('http://google.com/robots.txt'), 'UTF-8');
	var_dump($parser->isDisallowed('/someurl'));
	var_dump($parser->isAllowed('/someotherurl.html'));
	print_r($parser->rules);
?>
```
