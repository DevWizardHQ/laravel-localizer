# Changelog

All notable changes to `laravel-localizer` will be documented in this file.

## [Unreleased]

### Fixed
- Fixed `localizer:sync` command to properly detect translation strings with escaped quotes (e.g., `__('Here\'s a string')`)
- Improved regex patterns in `extractJsonKeys()` and `extractPhpKeys()` methods to handle escaped characters
- Translation keys with apostrophes and quotes are now correctly extracted and unescaped
