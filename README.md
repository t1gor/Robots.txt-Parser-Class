Robots.txt php parser class
=====================

Php class to parse robots.txt rules according to Google & Yandex specifications.

**Code sample:**
```php
<?php
// lib import
require_once('robotstxtparser.php');
$parser = new RobotsTxtParser(file_get_contents('http://google.com/robots.txt'));
var_dump($parser->isDisallowed('/someurl'));
var_dump($parser->isAllowed('/someotherurl.html'));
print_r($parser->rules);
?>
```

**Some useful links and materials:**
* https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
* http://help.yandex.com/webmaster/?id=1113851
* http://socoder.net/index.php?snippet=23824
* http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I
