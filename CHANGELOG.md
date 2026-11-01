# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Preloader::setExcludes()`, `Preloader::addExcludes()` and
  `Preloader::getExcludes()` to keep files out of the preload by glob pattern,
  for files that must not be required ahead of time ([#6]).
- `Preloader::getFailedFiles()`, listing the files the last `load()` call could
  not require, keyed by path with the error message as value ([#16]).
- `CHANGELOG.md` ([#11]).

### Changed

- `Preloader::load()` now skips a file that raises an `Error` instead of letting
  it abort the whole preload, so a class referencing an interface from an
  uninstalled optional dependency no longer breaks preloading. Pass
  `load(false)` for the previous strict behaviour ([#16]).

### Documentation

- Rewrote the README overview to describe the `Autoloader`, `Locator` and
  `Preloader` instead of the boilerplate placeholder text ([#2]).
- Removed the duplicated `## Requirements` section ([#1]).
- Documented PSR-4 namespace registration against direct class mapping, with
  examples ([#9]).
- Added a copy-paste usage example that registers a namespace and autoloads a
  class ([#10]).
- Documented how the default `Preloader` `$packagesDir` resolves under both the
  Composer and monorepo layouts ([#3]).

## [1.0.2] - 2026-05-01

### Documentation

- README corrections.

## [1.0.0] - 2026-04-29

### Added

- Initial release, with the `Autoloader`, `Locator` and `Preloader` classes and
  the debug collector integration.

[Unreleased]: https://github.com/webisters/autoload/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/webisters/autoload/compare/v1.0.0...v1.0.2
[1.0.0]: https://github.com/webisters/autoload/releases/tag/v1.0.0

[#1]: https://github.com/webisters/autoload/issues/1
[#2]: https://github.com/webisters/autoload/issues/2
[#3]: https://github.com/webisters/autoload/issues/3
[#6]: https://github.com/webisters/autoload/issues/6
[#9]: https://github.com/webisters/autoload/issues/9
[#10]: https://github.com/webisters/autoload/issues/10
[#11]: https://github.com/webisters/autoload/issues/11
[#16]: https://github.com/webisters/autoload/issues/16
