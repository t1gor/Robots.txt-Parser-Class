Robots.txt php parser class
=====================

Php class to parse robots.txt rules according to Google & Yandex specifications.

Example:
````
<?php
	// импорт библиотеки
	require_once('robotstxtparsermachine.php');
	$parser = new robotstxtparsermachine(file_get_contents('http://pr-cy.lo/robots.txt'), 'UTF-8');
	var_dump($parser->isDisallowed('/someurl'));
	var_dump($parser->isAllowed('/someotherurl.html'));
	print_r($parser->rules);
?>
````
