# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-11-01

Nothing in this release changes an existing call. Every new behaviour is behind
a new method or an optional parameter that defaults to the previous behaviour,
with one exception noted under Changed.

### Added

- `Preloader::getFailedFiles()`, listing the files the last `load()` call could
  not require, keyed by path with the error message as value ([#16]).
- `Preloader::setExcludes()`, `Preloader::addExcludes()` and
  `Preloader::getExcludes()` to keep files out of the preload by glob pattern,
  for files that must not be required ahead of time. Excluded files are left out
  of both `listFiles()` and `listPackagesFiles()`, so they are neither preloaded
  nor registered in the class map ([#6]).
- `Preloader::getClassMap()` and `Preloader::exportClassMap()` to generate a
  static classmap for production, skipping the namespace walk and the per-class
  filesystem lookups at run time ([#13]).
- `Autoloader::setClasses()` gained a `$validate` parameter, default `true`.
  Passing `false` assigns paths as given, skipping the `realpath()` and
  `is_file()` check that costs one stat per class, which is safe for a map
  produced by `Preloader::exportClassMap()` ([#13]).
- `Autoloader::withConflictDetection()`, `Autoloader::isDetectingConflicts()`,
  `Autoloader::getConflicts()` and `Autoloader::clearConflicts()` to surface
  registrations that silently replace or duplicate an earlier one. Off by
  default. Nothing is thrown and no registration is refused, so enabling it
  cannot change which file a class loads from ([#4]).
- `Locator::withClassNameCache()`, `Locator::isCachingClassNames()` and
  `Locator::clearClassNameCache()`, an optional in-memory cache of parsed class
  names keyed by file path and modification time. Off by default. Measured at
  about 5.6x over 400 files scanned five times ([#5]).
- `CHANGELOG.md` ([#11]).
- PHPStan at level 6 over `src` and `tests`, with `phpstan.neon.dist`, composer
  scripts for `test`, `lint` and `phpstan`, and a CI job. `src` was already
  clean at level 6 ([#8]).

### Changed

- `Preloader::load()` now skips a file that raises an `Error` instead of letting
  it abort the whole preload, so a class referencing an interface from an
  uninstalled optional dependency no longer breaks preloading. This is the one
  behaviour change in this release; pass `load(false)` for the previous strict
  behaviour ([#16]).
- CI runs the test suite against PHP 8.2, 8.3, 8.4 and 8.5. It previously
  proved only one version although `composer.json` requires `php >=8.2`. The
  matrix job is named `tests` and a small aggregating job keeps the name `test`,
  so the required status check stays stable as supported versions change ([#7]).

### Fixed

- `Locator::getClassName()` read the declared name from a fixed token offset,
  which only holds for a plain `class Name` declaration. Four defects followed,
  three of them also emitting `Warning: Uninitialized string offset 1` ([#20]):
  - a `::class` constant at statement level was treated as a declaration and
    produced a truncated name
  - an anonymous class produced a broken name, and a named class declared after
    an anonymous one was never found
  - any top-level `return` aborted the scan, hiding a declaration after a guard
    clause
  - an unparseable file returned `""` rather than `null`

  Names are now resolved relative to the keyword, anonymous declarations are
  skipped, and `null` is returned unless a name was captured.

### Documentation

- Rewrote the README overview to describe the `Autoloader`, `Locator` and
  `Preloader` instead of the boilerplate placeholder text ([#2]).
- Removed the duplicated `## Requirements` section ([#1]).
- Documented PSR-4 namespace registration against direct class mapping, with
  examples, including prefix precedence and path validation ([#9]).
- Added a copy-paste usage example that registers a namespace and autoloads a
  class ([#10]).
- Documented how the default `Preloader` `$packagesDir` resolves under both the
  Composer and monorepo layouts, which was verified rather than changed ([#3]).

### Tests

- 20 data-provider cases for `Locator::getClassName()`, covering the defects
  above plus backed enums, interfaces, traits, `readonly` and attributed
  classes, brace-delimited namespaces, first-of-many wins, missing files and
  directories ([#12]).
- Suite grew from 31 tests and 95 assertions to 87 tests and 201 assertions.

## [1.0.2] - 2026-05-01

### Documentation

- README corrections.

## [1.0.0] - 2026-04-29

### Added

- Initial release, with the `Autoloader`, `Locator` and `Preloader` classes and
  the debug collector integration.

[Unreleased]: https://github.com/webisters/autoload/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/webisters/autoload/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/webisters/autoload/compare/v1.0.0...v1.0.2
[1.0.0]: https://github.com/webisters/autoload/releases/tag/v1.0.0

[#1]: https://github.com/webisters/autoload/issues/1
[#2]: https://github.com/webisters/autoload/issues/2
[#3]: https://github.com/webisters/autoload/issues/3
[#4]: https://github.com/webisters/autoload/issues/4
[#5]: https://github.com/webisters/autoload/issues/5
[#6]: https://github.com/webisters/autoload/issues/6
[#7]: https://github.com/webisters/autoload/issues/7
[#8]: https://github.com/webisters/autoload/issues/8
[#9]: https://github.com/webisters/autoload/issues/9
[#10]: https://github.com/webisters/autoload/issues/10
[#11]: https://github.com/webisters/autoload/issues/11
[#12]: https://github.com/webisters/autoload/issues/12
[#13]: https://github.com/webisters/autoload/issues/13
[#16]: https://github.com/webisters/autoload/issues/16
[#20]: https://github.com/webisters/autoload/issues/20
