# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- changelog-insert: bin/changelog.sh puts generated sections here; 0.3.0 and older are hand-written and frozen. -->

## [0.3.0] - 2026-09-24

First release since `v0.2.5` (2020). The parser was rewritten on top of
[PHP stream filters](https://www.php.net/manual/en/stream.filters.php), so this release carries
breaking changes alongside the fixes.

### Breaking

- **PHP >= 8.2 is now required.** EOL versions were dropped from the matrix and from
  `composer.json` ([#135], closes [#120]). PHP 8 compatibility itself landed earlier in [#126].
- **Parser rewritten.** Directives moved into separate classes, parsing runs through stream
  filters, and [PSR-3](https://www.php-fig.org/psr/psr-3/) `LoggerInterface` is supported
  throughout ([#89], touches [#71], [#74], [#77]).
- **Dead API removed.** `parseURL()` (`protected`) and `WarmingMessages::INLINED_HOST`
  (public const) are gone, together with the unreachable `clean-param` / `host` arms of
  `checkRuleSwitch()` and their helpers ([#129], closes [#127]).
- `Url::encode()` is now `public static` — the constructor calls it via `static::`, so
  subclasses can still override it ([#131]).

### Added

- **Parse size limit.** At most 500 KiB is read by default, the cap RFC 9309 and Google settle
  on. Counted over fetched bytes, so a hostile file is never read past the limit, let alone
  filtered or matched. A cut landing mid-line drops the partial rule instead of shortening it,
  so `Disallow: /admin/secret` can never silently widen into `/admin` ([#138], closes [#75]).
- **Configuration object.** A readonly `Configuration`, built directly or through
  `ConfigurationFactory` from an array or the environment. Warnings collected while building it
  are replayed once a logger is attached ([#138]).
- **Line-break-safe batching.** A filter at the head of the chain guarantees every downstream
  filter receives whole lines, so a 4096-byte batch boundary can no longer split a rule
  ([#119], thanks [@edim24]).
- CI matrix across the supported PHP versions ([#121]) and Windows runners ([#133]).
- `.gitattributes` pinning LF, with a CI job enforcing it (`test/Fixtures/**` exempt — the
  served bytes are the fixture) ([#129]).
- A YAML bug report form asking for the robots.txt, URL, user-agent, expected vs actual, and
  library + PHP version ([#131]).
- Docker image for development ([#89]).

### Fixed

- **Lookup is now O(1) in file size.** `isAllowed()` costs a flat ~3 µs whether the file is
  500 KB or 1 GB; it used to re-walk every user-agent block per call (134x slower at 1 GB).
  Parse throughput stays flat at ~208 MB/s across a 2000x size range ([#140], closes [#62]).
- **Rule ordering.** Specificity and precedence between competing rules reworked
  ([#134], closes [#76]).
- **Windows line endings** are detected correctly ([#133], closes [#83]); earlier newline
  determination fix in [#86] (thanks [@kudmni]).
- **Unknown encodings no longer fail or warn.** An unknown encoding trips
  `stream_filter_prepend()` in `GeneratorBasedReader::setEncoding()`, whose `false` branch
  never ran because PHP's warning fired first. It is now logged at DEBUG with PHP's exact text.
  An empty encoding no longer installs a pointless `convert.iconv./utf-8` filter
  ([#132], closes [#70]).
- **Percent-encoding at match time.** `Disallow: /café` did not block `/café` — the queried path
  was encoded but rules were matched raw, so the two could never meet, for any non-ASCII rule or
  any rule containing `| ^ { } \` or a space. Both sides are now encoded prior to comparison per
  RFC 9309 §2.2.2; `getRules()` still returns readable raw paths ([#131], part of [#69]).
- **Regex escaping.** `prepareRegexRule()` hand-escaped six characters, leaking
  `( ) + | ^ { } \` into `preg_match()` as regex. Replaced with the inverse rule — quote
  everything, reintroduce only what robots.txt defines as special (`*`, and a trailing `$`)
  ([#130], closes [#59] and [#87]).
- **Allow vs Disallow** when the only matching rule is a `Disallow` ([#128], closes [#93]).

### Dependencies

- `vipnytt/useragentparser` 1.0.5 → 1.0.7 ([#125]).
- `phpunit/phpunit` 9.5.10 → 9.6.4 ([#91], [#92], [#95], [#118]).
- `monolog/monolog` 2.3.2 → 2.9.1 ([#90], [#117]); dev requirement now `^3.0` ([#135]).
- `actions/checkout` 4 → 7 ([#124]), `actions/cache` 4 → 6 ([#123]),
  `codecov/codecov-action` 5 → 7 ([#122]).

[0.3.0]: https://github.com/t1gor/Robots.txt-Parser-Class/compare/v0.2.5...v0.3.0

[#59]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/59
[#62]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/62
[#69]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/69
[#70]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
[#71]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/71
[#74]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/74
[#75]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/75
[#76]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/76
[#77]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/77
[#83]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/83
[#86]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/86
[#87]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/87
[#89]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/89
[#90]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/90
[#91]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/91
[#92]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/92
[#93]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/93
[#95]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/95
[#117]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/117
[#118]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/118
[#119]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/119
[#120]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/120
[#121]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/121
[#122]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/122
[#123]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/123
[#124]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/124
[#125]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/125
[#126]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/126
[#127]: https://github.com/t1gor/Robots.txt-Parser-Class/issues/127
[#128]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/128
[#129]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/129
[#130]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/130
[#131]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/131
[#132]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/132
[#133]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/133
[#134]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/134
[#135]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/135
[#138]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/138
[#140]: https://github.com/t1gor/Robots.txt-Parser-Class/pull/140

[@edim24]: https://github.com/edim24
[@kudmni]: https://github.com/kudmni
