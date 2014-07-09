Robots.txt php parser class
=====================

Php class to parse robots.txt rules according to Google & Yandex specifications. **Please note that the class name was changed in the recent [commits](https://github.com/t1gor/Robots.txt-Parser-Class/commit/b4db1555cd35f3f3d34845af53aa403a8537fbef#diff-ff40970a7a5d1e6998d9eafe3e228456L19)**, so if upgrading from the older code - please consider checking your code.

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

More code samples could be found in the [tests folder](https://github.com/t1gor/Robots.txt-Parser-Class/tree/master/test).

**Algorythm schema:**
![Schema](https://raw.githubusercontent.com/t1gor/Robots.txt-Parser-Class/master/assets/schema.png)

**Some useful links and materials:**
* [Google: Robots.txt Specifications](https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt)
* [Yandex: Using robots.txt](http://help.yandex.com/webmaster/?id=1113851)
* [Some inspirational code](http://socoder.net/index.php?snippet=23824), and [some more](http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I)

Thanks for the contriution!
