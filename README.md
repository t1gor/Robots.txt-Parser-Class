Robots.txt php parser class
=====================

[![Build Status](https://travis-ci.org/t1gor/Robots.txt-Parser-Class.svg?branch=master)](https://travis-ci.org/t1gor/Robots.txt-Parser-Class) [![Code Climate](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class/badges/gpa.svg)](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) [![Test Coverage](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class/badges/coverage.svg)](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) [![License](https://poser.pugx.org/t1gor/robots-txt-parser/license.svg)](https://packagist.org/packages/t1gor/robots-txt-parser) [![Total Downloads](https://poser.pugx.org/t1gor/robots-txt-parser/downloads.svg)](https://packagist.org/packages/t1gor/robots-txt-parser)

Php class to parse robots.txt rules according to Google & Yandex specifications. Please keep in mind that this is an alpha-version project, as you might see from the coverage report :) But I am about to change that.

### Installation
The library is available for install via Composer package. To install via Composer, please add the requerement to your `composer.json` file, like this:

```json
{
    "require": {
        "t1gor/robots-txt-parser": "dev-master"
    }
}
```

and then use composer to load the lib:

```php
<?php
    require 'vendor/autoload.php';
    $parser = new RobotsTxtParser(file_get_contents('http://example.com/robots.txt'));
    ...
```

You can find out more about Composer here: https://getcomposer.org/

### Usage example
```php
<?php
    require_once('robotstxtparser.php');
    $parser = new RobotsTxtParser(file_get_contents('http://example.com/robots.txt'));
    var_dump($parser->isDisallowed('/someurl'));
    var_dump($parser->isAllowed('/someotherurl.html'));
    print_r($parser->rules);
?>
```

More code samples could be found in the [tests folder](https://github.com/t1gor/Robots.txt-Parser-Class/tree/master/test).

### Algorythm schema:
**Conditions:**
* (0) ZERO_POINT
* (1) READ_DIRECTIVE
* (2) SKIP_SPACE
* (3) READ_VALUE
* (4) SKIP_LINE

![Schema](https://raw.githubusercontent.com/t1gor/Robots.txt-Parser-Class/master/assets/schema.png)
![Components graph](https://raw.githubusercontent.com/t1gor/Robots.txt-Parser-Class/master/assets/components-graph.png)

**Some useful links and materials:**
* [Google: Robots.txt Specifications](https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt)
* [Yandex: Using robots.txt](http://help.yandex.com/webmaster/?id=1113851)
* [Some inspirational code](http://socoder.net/index.php?snippet=23824), and [some more](http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I)
* [Google Webmaster tools Robots.txt testing tool](https://www.google.com/webmasters/tools/robots-testing-tool)

### Contributing
First of all - thank you for your interest and a desire to help! If you found an issue and know how to fix it, please submit a pull request to the dev branch. Please do not forget the following:
- Your fixed issue should be covered with tests (we are using phpUnit)
- Please mind the [code climate](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) recommendations. It some-how helps to keep things simplier, or at least seems to :)
- Following the coding standard would also be much appreciated (4 tabs as an ident, camelCase, etc.)

I would really appreciate if you could share the link to your project that is utilizing the lib.

### TODO:
 * [Fix open issues](https://github.com/t1gor/Robots.txt-Parser-Class/issues)
 * [Raise coverage](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class/code?sort=covered_percent&sort_direction=desc). The goal is to reach at least 50% and release v1.0
 * Add a method for validation

License
-------

    The MIT License

    Copyright (c) 2013 Igor Timoshenkov

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
