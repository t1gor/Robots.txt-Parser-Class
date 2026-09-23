Robots.txt php parser class
=====================

[![CI](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/ci.yml/badge.svg)](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/ci.yml) [![Code Climate](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class/badges/gpa.svg)](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) [![Test Coverage](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class/badges/coverage.svg)](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) [![License](https://poser.pugx.org/t1gor/robots-txt-parser/license.svg)](https://packagist.org/packages/t1gor/robots-txt-parser) [![Total Downloads](https://poser.pugx.org/t1gor/robots-txt-parser/downloads.svg)](https://packagist.org/packages/t1gor/robots-txt-parser)

PHP class to parse robots.txt rules according to Google, Yandex, W3C and The Web Robots Pages specifications.

Full list of supported specifications (and what's not supported, yet) are available in our [Wiki](https://github.com/t1gor/Robots.txt-Parser-Class/wiki/Specifications).

### Supported directives:

- User-agent
- Allow
- Disallow
- Sitemap
- Host
- Cache-delay
- Clean-param
- Crawl-delay
- Request-rate (in progress)
- Visit-time (in progress)

### Installation
The library is available for install via Composer package. To install via Composer, please add the requirement to your `composer.json` file, like this:

```sh
composer require t1gor/robots-txt-parser
```

You can find out more about Composer here: https://getcomposer.org/

### Usage example

###### Creating parser instance

```php
use t1gor\RobotsTxtParser\RobotsTxtParser;

# from string
$parser = new RobotsTxtParser("User-agent: * \nDisallow: /");

# from local file
$parser = new RobotsTxtParser(fopen('some/robots.txt'));

# or a remote one (make sure it's allowed in your php.ini)
# even FTP should work (but this is not confirmed)
$parser = new RobotsTxtParser(fopen('http://example.com/robots.txt'));
```

###### Logging parsing process

We are implementing `LoggerAwareInterface` from `PSR`, so it should work out of the box with any logger supporting that standard. Please see below for Monolog example with Telegram bot:

```php
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

$monologLogger = new Logger('robot.txt-parser');
$monologLogger->setHandler(new TelegramBotHandler('api-key', 'channel'));

$parser = new RobotsTxtParser(fopen('some/robots.txt'));
$parser->setLogger($monologLogger);
```

Most log entries we have are of `LogLevel::DEBUG`, but there might also be some `LogLevel::WARNINGS` where it is appropriate.

###### Parsing non UTF-8 encoded files

```php
use t1gor\RobotsTxtParser\RobotsTxtParser;

/** @see EncodingTest for more details */
$parser = new RobotsTxtParser(fopen('market-yandex-Windows-1251.txt', 'r'), 'Windows-1251');
```

###### Inspecting the applied stream filters

Input runs through a chain of stream filters before any directive is read. `filters()` shows which ones are actually active, in order:

```php
use t1gor\RobotsTxtParser\RobotsTxtParser;

$parser = new RobotsTxtParser(fopen('robots.txt', 'r'));

print_r($parser->filters());
// RTP_ensure_end_of_lines, RTP_skip_commented_lines, RTP_skip_end_of_commented_line, RTP_trim_spaces_left, RTP_skip_unsupported_directives, RTP_skip_directives_invalid_value, RTP_skip_empty_lines
```

A missing filter means it failed to apply - attach a logger to see why. A non UTF-8 encoding adds `convert.iconv.*` at the front.

###### Limiting how much gets parsed

An endless or hostile robots.txt will happily burn your crawler's CPU and memory, so the parser reads
at most 500 KiB by default - the size [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309#section-2.5)
and Google both settle on. The cap counts *fetched* bytes, before any decoding, and anything past it
is ignored. A rule cut in half by the limit is dropped rather than shortened, so `Disallow: /admin/secret`
can never silently widen into `Disallow: /admin`.

```php
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;

$parser = new RobotsTxtParser(fopen('robots.txt', 'r'), config: new Configuration(100 * 1024));

$parser->isTruncated(); // did the limit actually cut anything off?
```

Pass `null` to switch the limit off. That is logged as a warning, and so is any limit below
`Configuration::RECOMMENDED_MIN_BYTE_LIMIT` (24 KiB), where robots.txt risks truncating to nothing -
and unmatched paths then default to allowed. `0` and negative values throw a `ConfigurationException`.

Warnings go through the PSR-3 logger. Attaching one after construction is fine: anything decided
earlier is replayed as soon as a logger turns up.

###### Bootstrapping the configuration from a framework

`ConfigurationFactory` turns whatever shape your framework keeps settings in into a `Configuration`.
Unknown keys are rejected with a suggestion, and strings are accepted wherever an integer is - config
layers hand those over constantly.

**Laravel** - `config/robots.php`, then bind it in a service provider:

```php
// config/robots.php
return ['byte_limit' => env('RTP_BYTE_LIMIT', 512000)];

// app/Providers/AppServiceProvider.php
use t1gor\RobotsTxtParser\Config\ConfigurationFactory;
use t1gor\RobotsTxtParser\Configuration;

$this->app->singleton(Configuration::class, fn () => ConfigurationFactory::fromArray(config('robots')));
```

**Symfony** - `config/services.yaml`:

```yaml
t1gor\RobotsTxtParser\Configuration:
    factory: ['t1gor\RobotsTxtParser\Config\ConfigurationFactory', 'fromArray']
    arguments:
        - { byte_limit: '%env(int:RTP_BYTE_LIMIT)%' }
```

**WordPress** - no container and no environment convention, so `fromEnvironment()` falls back to a
constant of the same name:

```php
// wp-config.php
define('RTP_BYTE_LIMIT', 512000);

// anywhere in the plugin
$config = ConfigurationFactory::fromEnvironment();
```

**Anything else** - `ConfigurationFactory::fromArray()` takes a plain array, which every PHP config
layer produces, and `ConfigurationFactory::fromEnvironment()` reads `RTP_`-prefixed variables.

### Public API

| Method | Params | Returns | Description |
| ------ | ------ | ------ | ----------- |
| `setLogger` | `Psr\Log\LoggerInterface $logger` | `void` |  |
| `getLogger` | `-` | `Psr\Log\LoggerInterface` |  |
| `setHttpStatusCode` | `int $code` | `void` | Set HTTP response code for allowance checks |
| `isAllowed` | `string $url, ?string $userAgent` | `bool` | If no `$userAgent` is passed, will return for `*` |
| `isDisallowed` | `string $url, ?string $userAgent` | `bool` | If no `$userAgent` is passed, will return for `*` |
| `getDelay` | `string $userAgent, string $type = 'crawl-delay'` | `float` | Get any of the delays, e.g. `Crawl-delay`, `Cache-delay`, etc. |
| `getCleanParam` | `-` | `[ string => string[] ]` | Where key is the path, and values are params |
| `getRules` | `?string $userAgent` | `array` | Get the rules the parser read in a tree-line structure |
| `getHost` | `?string $userAgent` | `string[]` or `string` or `null` | If no `$userAgent` is passed, will return all |
| `getSitemaps` | `?string $userAgent` | `string[]` | If no `$userAgent` is passed, will return all |
| `filters` | `-` | `string[]` | Stream filters applied to the input, in the order they run |
| `getConfiguration` | `-` | `Configuration` | The options the parser was built with |
| `isTruncated` | `-` | `bool` | Whether the byte limit cut the input short |
| `getContent` | `-` | `string` | The content that was parsed. |
| `getLog` | `-` | `[]` | **Deprecated.** Please use PSR logger as described above. |
| `render` | `-` | `string` | **Deprecated.** Please `getContent` |

Even more code samples could be found in the [tests folder](https://github.com/t1gor/Robots.txt-Parser-Class/tree/master/test).

**Some useful links and materials:**
* [Google: Robots.txt Specifications](https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt)
* [Yandex: Using robots.txt](http://help.yandex.com/webmaster/?id=1113851)
* [The Web Robots Pages](http://www.robotstxt.org/)
* [W3C Recommendation](https://www.w3.org/TR/html4/appendix/notes.html#h-B.4.1.2)
* [Some inspirational code](http://socoder.net/index.php?snippet=23824), and [some more](http://www.the-art-of-web.com/php/parse-robots/)
* [Google Webmaster tools Robots.txt testing tool](https://www.google.com/webmasters/tools/robots-testing-tool)

### Contributing
First of all - thank you for your interest and a desire to help! If you found an issue and know how to fix it, please submit a pull request to the dev branch. Please do not forget the following:
- Your fixed issue should be covered with tests (we are using phpUnit)
- Please mind the [code climate](https://codeclimate.com/github/t1gor/Robots.txt-Parser-Class) recommendations. It some-how helps to keep things simpler, or at least seems to :)
- Following the coding standard would also be much appreciated (4 tabs as an indent, camelCase, etc.)

I would really appreciate if you could share the link to your project that is utilizing the lib.

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
