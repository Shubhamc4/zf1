# Zend Framework 1 for PHP 8.2

This is an updated version of the Zend Framework 1 package adapted to work with PHP 8.2.

## Background

Zend Framework 1 (ZF1) officially ended its life on September 28, 2016. However, many applications still rely on it, and with PHP moving forward, compatibility issues have emerged.

This package aims to provide a PHP 8.2 compatible version of Zend Framework 1, allowing legacy applications to continue functioning on modern PHP environments.

## PHP 8.2 Compatibility

This version of Zend Framework 1 has been updated to be fully compatible with PHP 8.2. Key updates include:

- Added `#[\ReturnTypeWillChange]` attributes to interface implementations (Iterator, Countable, ArrayAccess, etc.)
- Added `#[AllowDynamicProperties]` attribute to classes that use dynamic properties
- Replaced deprecated `utf8_encode()`/`utf8_decode()` with `mb_convert_encoding()`
- Updated string access syntax (replaced curly braces with square brackets)
- Fixed constructor compatibility issues
- Updated parameter default value ordering in method signatures
- Removed usage of deprecated PHP functions and features

For more detailed information about the PHP 8.2 compatibility changes, see the [PHP82_COMPATIBILITY.md](PHP82_COMPATIBILITY.md) file.

## Installation

```bash
composer require shubhamc4/zf1
```

## Requirements

- PHP 8.2 or higher

## Usage with Legacy Applications

When using this package with an existing ZF1 application, you may need to make some changes to your code to ensure compatibility with PHP 8.2:

1. Add `#[\ReturnTypeWillChange]` to any custom classes that implement PHP interfaces
2. Add property declarations or use `#[AllowDynamicProperties]` for classes that create properties dynamically
3. Replace deprecated functions like `utf8_encode()`, `utf8_decode()`, `create_function()`, etc.
4. Update string access syntax from `$string{0}` to `$string[0]`

## License

BSD-3-Clause (same as the original Zend Framework)
