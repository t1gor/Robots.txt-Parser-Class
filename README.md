Robots.txt php parser class
=====================

[![CI](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/ci.yml/badge.svg)](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/ci.yml) [![Performance](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/performance.yml/badge.svg)](https://github.com/t1gor/Robots.txt-Parser-Class/actions/workflows/performance.yml) [![Coverage](https://codecov.io/gh/t1gor/Robots.txt-Parser-Class/branch/master/graph/badge.svg)](https://codecov.io/gh/t1gor/Robots.txt-Parser-Class) [![PHP](https://img.shields.io/packagist/php-v/t1gor/robots-txt-parser/dev-master)](https://packagist.org/packages/t1gor/robots-txt-parser) [![Latest release](https://img.shields.io/packagist/v/t1gor/robots-txt-parser)](https://packagist.org/packages/t1gor/robots-txt-parser) [![License](https://img.shields.io/packagist/l/t1gor/robots-txt-parser)](https://packagist.org/packages/t1gor/robots-txt-parser) [![Downloads](https://img.shields.io/packagist/dt/t1gor/robots-txt-parser)](https://packagist.org/packages/t1gor/robots-txt-parser)

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
- Comment
- Noindex
- Request-rate
- Robot-version
- Visit-time

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

$parser = new RobotsTxtParser();

# from string
$parser->setContent("User-agent: * \nDisallow: /");

# from local file
$parser->setContent(fopen('some/robots.txt', 'r'));

# or a remote one (make sure it's allowed in your php.ini)
# even FTP should work (but this is not confirmed)
$parser->setContent(fopen('http://example.com/robots.txt', 'r'));

# non UTF-8 input - the encoding describes the document, so it travels with it
$parser->setContent(fopen('market-yandex-Windows-1251.txt', 'r'), 'Windows-1251');
```

The constructor takes only dependencies, all optional, so a DI container can resolve the parser
once and hand it round; `setContent()` is what you call per document, and it clears everything the
previous one left behind.

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

$parser = new RobotsTxtParser();
$parser->setLogger($monologLogger);
$parser->setContent(fopen('some/robots.txt', 'r'));
```

Most log entries we have are of `LogLevel::DEBUG`, but there might also be some `LogLevel::WARNINGS` where it is appropriate.

###### Inspecting the applied stream filters

Input runs through a chain of stream filters before any directive is read. `filters()` shows which ones are actually active, in order:

```php
use t1gor\RobotsTxtParser\RobotsTxtParser;

$parser = (new RobotsTxtParser())->setContent(fopen('robots.txt', 'r'));

print_r($parser->getReader()->filters());
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

$parser = new RobotsTxtParser(new Configuration(100 * 1024));
$parser->setContent(fopen('robots.txt', 'r'));

$parser->getReader()->wasTruncated(); // did the limit actually cut anything off?
```

Pass `null` to switch the limit off. That is logged as a warning, and so is any limit below
`Configuration::RECOMMENDED_MIN_BYTE_LIMIT` (24 KiB), where robots.txt risks truncating to nothing -
and unmatched paths then default to allowed. `0` and negative values throw a `ConfigurationException`.

Warnings go through the PSR-3 logger. Attaching one after construction is fine: anything decided
earlier is replayed as soon as a logger turns up.

###### The extended standard

`Robot-version`, `Visit-time`, `Request-rate` and `Comment` describe the group rather than a path, and each has an accessor of its own. `Request-rate` and `Comment` may repeat; the other two keep the last value seen. Anything that cannot be read as the directive is dropped and logged.

```php
$parser->getRobotVersion('GoogleBot');   // '2.0', or null when the group does not say
$parser->getComments();                  // ['regenerated nightly by the CMS']
$parser->getVisitTime()?->covers(new DateTimeImmutable('now'));   // is the crawler welcome right now?

foreach ($parser->getRequestRates() as $rate) {
    $rate->getSecondsPerRequest();       // 300.0 for "Request-rate: 1/5m"
    $rate->appliesAt(new DateTimeImmutable('now'));
}
```

`Visit-time` comes back as a `TimeWindow` and `Request-rate` as a `RequestRate` - value objects, so "0600-0845" is parsed once rather than by every caller. Times are UTC, as the [extended standard](http://www.conman.org/people/spc/robots2.html) has them, and a window that ends before it starts runs over midnight.

Both answer the scheduling questions directly, so a crawl loop is the two calls and nothing of your own:

```php
$now = new DateTimeImmutable('now');

# when may I start? - the moment you asked about, if the window is already open
$startAt = $parser->getVisitTime()?->nextOpening($now) ?? $now;

# how long between two requests? - as seconds, or as something to do date arithmetic with
foreach ($parser->getRequestRates('MyBot') as $rate) {
    if ($rate->appliesAt($startAt)) {
        $nextRequestAt = $startAt->add($rate->getPeriod());   // DateInterval, e.g. PT5M
        usleep((int) ($rate->getSecondsPerRequest() * 1_000_000));
    }
}
```

`nextOpening()` returns UTC whatever zone you hand it, and hands back the moment you gave it when the window is open then - so you can sleep until whatever comes back without testing first. It is also what makes the midnight case painless: for `2300-0200` at 01:00 the answer is 01:00, and at 02:30 it is 23:00 tonight.

`getPeriod()` counts in hours rather than days on purpose. Added to a zoned date, `P1D` keeps the clock time and so moves by 23 or 25 hours over a DST change, while `PT24H` stays the 86400 seconds `Request-rate: 3/1d` actually means.

A group without a `Robot-version` reads as 1.0.0 by the spec, but `getRobotVersion()` returns `null` rather than inventing it - write `?? '1.0'` where you want the default, and "did not say" stays apart from "said 1.0".

`Noindex` is a path, like `Disallow`, but answers a different question - keep this page out of the index, rather than stay away from it. So it has its own check and does not affect `isAllowed()`:

```php
$parser->isIndexable('/drafts/post-1');  // false for "Noindex: /drafts"
$parser->isAllowed('/drafts/post-1');    // ... still true, nothing disallows it
$parser->getNoIndex();                   // ['/drafts']
```

###### Writing it back out

The writers are separate from the parser - it parses, they write, and nothing that only reads a
robots.txt pays for them. Hand one the rules and ask for the document:

```php
use t1gor\RobotsTxtParser\Writer\StringWriter;

$bytes = (new StringWriter($logger))
    ->setTree($parser->getRules())
    ->setEol("\n")
    ->setOutput(fopen('robots.txt', 'w'))
    ->render();
```

It normalises rather than echoes: anything that cannot be valid is dropped, duplicates go, directive
names get their canonical casing, `Host`, `Clean-param` and `Sitemap` are collected into one block at
the end since they apply to the whole file, and user-agents carrying the same rules share a group.
Each group opens with what describes it - `Robot-version`, `Visit-time`, `Request-rate`, `Comment` -
and rates are written in the largest unit that fits them, so `1/300` and `1/5m` are one line.
Rules are written longest first, with `Allow` ahead of an equally long `Disallow` - the order
[RFC 9309](https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2) resolves them in, so a reader that
stops at the first match still gets the same answer. Everything dropped is logged, so a file that
comes back shorter says why. Pass the parser's own logger and both halves report to one place.

The output is settled: parsing what comes out and writing it again gives the same bytes.

###### Choosing a writer

`StringWriter` and `StreamWriter` answer the same `WriterInterface` - setters for the tree, line
ending, encoding and output, then `render()`, which returns how many bytes it wrote.

```php
use t1gor\RobotsTxtParser\Writer\StreamWriter;

$bytes = (new StreamWriter())
    ->setTree($parser->getRules())
    ->setEncoding('Windows-1251')
    ->setOutput(fopen('robots.txt', 'w'))
    ->render();
```

`StringWriter` builds the document, converts it and writes it in one go; `StreamWriter` converts and
writes each line as it is produced. They emit the same bytes and neither holds anything once
`render()` returns, so the choice is only ever about peak memory:

**Use `StringWriter`** unless you have a reason not to. A real robots.txt is kilobytes, where both
finish in well under a millisecond, and it is the simpler and slightly quicker of the two.

**Use `StreamWriter`** when the document is large or you do not control its size - it peaks at about
half the document rather than one and a half times it, and that gap widens as the file grows. You
only reach that territory deliberately: the parser reads 500 KiB by default, so a tree big enough to
matter here means you passed `byteLimit: null`.

Measured on PHP 8.3, best of five, output to `/dev/null`:

| rules | document | `StringWriter` | `StreamWriter` |
| --- | --- | --- | --- |
| 100 | 5 KB | 0.0001 s, 0.02 MB | 0.0001 s, 0.02 MB |
| 10k | 0.49 MB | 0.0127 s, 0.71 MB | 0.0149 s, 0.28 MB |
| 250k | 12.7 MB | 0.3615 s, 19.1 MB | 0.4051 s, 7.27 MB |
| 1M | 51.5 MB | 1.5061 s, 76.8 MB | 1.6858 s, 27.0 MB |

So streaming costs 10-17% more time - a write per line instead of one for the lot - and saves
roughly two thirds of the peak. Below 10k rules there is nothing in it either way.
`bin/benchmark-writers.php` runs this on your own hardware.

Want the document as a string rather than in a file? Give it a `php://temp` - it spills to disk on
its own, so it costs no more than it has to:

```php
$buffer = fopen('php://temp', 'r+');
(new StringWriter())->setTree($parser->getRules())->setOutput($buffer)->render();
rewind($buffer);

echo stream_get_contents($buffer);
```

The rules tree is UTF-8 whatever the document was, so `setEncoding()` is a conversion on the way out
- warned about, since the spec asks for UTF-8. A conversion that cannot work throws
`EncodingFailedException` rather than quietly writing UTF-8: bytes served under a charset they are
not in, or a rule missing from a policy file, are both worse than a render that fails. A write the
output will not take throws `WriteFailedException` for the same reason.

Groups are assembled before the first line goes out - merging the user-agents that share a rule set,
and putting the catch-all last, cannot be decided until every group has been seen. What is held is
the tree's own path strings in sorted order, a pointer each; the `Disallow: ` line is built as it is
written, so neither writer keeps a second copy of the rules.

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
| `getDelay` | `string $userAgent, Directive $type = Directive::CRAWL_DELAY` | `int\|float` | Get any of the delays, e.g. `Crawl-delay`, `Cache-delay`, etc. |
| `getCleanParam` | `-` | `[ string => string[] ]` | Where key is the path, and values are params |
| `getRules` | `?string $userAgent` | `array` | Get the rules the parser read in a tree-line structure |
| `getHost` | `?string $userAgent` | `string[]` or `string` or `null` | If no `$userAgent` is passed, will return all |
| `getSitemaps` | `?string $userAgent` | `string[]` | If no `$userAgent` is passed, will return all |
| `getRequestRates` | `string $userAgent` | `RequestRate[]` | How often the crawler may ask, and when. `getSecondsPerRequest()`, `getPeriod()` as a `DateInterval`, `appliesAt()` |
| `getVisitTime` | `string $userAgent` | `TimeWindow` or `null` | When the crawler is welcome, UTC. `covers()`, `nextOpening()` |
| `getRobotVersion` | `string $userAgent` | `string` or `null` | The revision of the extended standard the group is written to; `null` when it does not say, whose spec default is `1.0` |
| `getComments` | `string $userAgent` | `string[]` | What the file has to say to whoever runs the crawler |
| `getNoIndex` | `string $userAgent` | `string[]` | Paths to keep out of the index |
| `isIndexable` | `string $url, string $userAgent` | `bool` | Whether `Noindex` leaves the url indexable |
| `setContent` | `resource\|string $content, ?string $encoding` | `self` | The document to parse; resets anything left from the previous one |
| `getReader` | `-` | `ReaderInterface` | The reader holding the current document - filters, raw content, truncation |
| `getConfiguration` | `-` | `Configuration` | The options the parser was built with |

#### `Directive` is an enum

`Directive` is a string-backed enum, so a directive is a case rather than a bare string. Pass the
case where one is expected, and use `->value` wherever a string is - notably the keys of the tree
`getRules()` returns:

```php
use t1gor\RobotsTxtParser\Directive;

$parser->getDelay('GoogleBot', Directive::CACHE_DELAY);       // the case itself
$parser->getRules('*')[Directive::DISALLOW->value];           // ->value for a tree key
```

Even more code samples could be found in the [tests folder](https://github.com/t1gor/Robots.txt-Parser-Class/tree/master/test).

**Some useful links and materials:**
* [Google: Robots.txt Specifications](https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt)
* [Yandex: Using robots.txt](http://help.yandex.com/webmaster/?id=1113851)
* [The Web Robots Pages](http://www.robotstxt.org/)
* [W3C Recommendation](https://www.w3.org/TR/html4/appendix/notes.html#h-B.4.1.2)
* [Some inspirational code](http://socoder.net/index.php?snippet=23824), and [some more](http://www.the-art-of-web.com/php/parse-robots/)
* [Google Webmaster tools Robots.txt testing tool](https://www.google.com/webmasters/tools/robots-testing-tool)

### Benchmarks
Large files are the subject of [#62](https://github.com/t1gor/Robots.txt-Parser-Class/issues/62), so CI parses generated ones of 250 MB, 600 MB and 1 GB against a time budget. They are built on the runner and never committed:

```sh
php bin/generate-robots.php --size=250MB --out=/tmp/robots.txt
php bin/benchmark.php --file=/tmp/robots.txt --max-seconds=20 --memory-limit=256M
```

`--density` is the share of lines carrying a rule; the default 0.02 matches a real oversized robots.txt, where most of the file is HTML and comments. The benchmark parses with no byte limit, reports wall time, CPU and peak memory, and exits non-zero past `--max-seconds`.

Locally on PHP 8.3:

| file | parse | throughput | peak memory | tree |
| --- | --- | --- | --- | --- |
| 250 MB | 1.2 s | 210 MB/s | 8 MB | 92k rules, 1.3k user-agents |
| 600 MB | 2.9 s | 209 MB/s | 14 MB | 220k rules, 3.2k user-agents |
| 1 GB | 5.0 s | 205 MB/s | 22 MB | 377k rules, 5.4k user-agents |
| 250 MB, every line a rule (`--density=1`) | 12.4 s | 20 MB/s | 559 MB | 11M rules, 157k user-agents |
| 25 MB, 50k rules per user-agent (`--group=50000`) | 1.3 s | 20 MB/s | 82 MB | 1.2M rules, 37 user-agents |

Throughput holds flat as the file grows - memory tracks the rules kept, not the bytes read.

Lookups are measured too. `isAllowed()` has to work out which user-agent block applies, which walks every name in the file, so the parser keeps that answer until the document changes - on the 32k-user-agent tree above that moved lookups from 380/s to 50,543/s.

That last row took **40 s** until recently: a repeated rule was spotted by scanning everything kept so far, so one big group cost O(n squared). Past a few hundred rules for the same user-agent there is an index instead, and below that the scan stays - it is quicker there, and costs no memory. Every other row above is unchanged by it, to the megabyte.

### Contributing
First of all - thank you for your interest and a desire to help! If you found an issue and know how to fix it, please submit a pull request to the dev branch. Please do not forget the following:
- Your fixed issue should be covered with tests (we are using phpUnit)
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
