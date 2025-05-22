# PHP 8.2 Compatibility Notes

This document outlines important information about running this Zend Framework 1 fork on PHP 8.2.

## Interface Method Return Types

PHP 8.2 introduces stricter type-checking for methods that implement interfaces. Interface methods
for standard PHP interfaces like Countable, ArrayAccess, Iterator, and IteratorAggregate that return
a value require the #[\ReturnTypeWillChange] attribute or explicit return type declarations.

### Implementation

To maintain compatibility with PHP 8.2, we've addressed this by:

1. Adding `#[\ReturnTypeWillChange]` attributes to interface methods in:

   - `Zend_Config` (for `count()`, `current()`, `key()`, etc.)
   - `Zend_Form` (for `count()`, `current()`, `key()`, etc.)
   - `Zend_Session_Namespace` (for `getIterator()`)
   - `Zend_Registry` (for `offsetExists()`)
   - `Zend_Uri` (for `__toString()`)
   - `Zend_Controller_Action_HelperBroker_PriorityStack` (for `getIterator()`, `offsetExists()`, `offsetGet()`, `offsetSet()`, `offsetUnset()`, and `count()`)

2. Adding proper PHPDoc blocks to interface method implementations to document return types.

## Dynamic Properties

PHP 8.2 deprecates "dynamic properties" (properties that are created at runtime and not declared in the class).
This affects some Zend Framework 1 classes that rely on dynamic property creation.

We've addressed this by conditionally using `#[AllowDynamicProperties]` attribute on affected classes
when running on PHP 8.2+.

## Additional PHP 8.2 Compatibility Changes

Beyond interface method return types and dynamic properties, we've made several other changes to ensure compatibility with PHP 8.2:

1. Updated code to handle the deprecation of `utf8_encode()` and `utf8_decode()` functions by replacing them with `mb_convert_encoding()`

2. Fixed string interpolation in double-quoted strings (curly brace syntax is deprecated)

3. Updated parameter default value ordering in method signatures to comply with PHP 8.2's stricter requirements

4. Fixed constructor compatibility issues for classes that extend PHP core classes

5. Removed usage of other deprecated PHP functions and features

## Verification

A verification script (`verify-php82.php`) is included to test PHP 8.2 compatibility. This script:

1. Tests interface implementations to ensure they work correctly:

   - `Countable::count()`
   - `Iterator` methods (`current()`, `key()`, `next()`, `rewind()`, `valid()`)
   - `ArrayAccess` methods (`offsetExists()`, `offsetGet()`, `offsetSet()`, `offsetUnset()`)
   - `IteratorAggregate::getIterator()`

2. Verifies that modified classes load and function properly under PHP 8.2

Run this script with PHP 8.2 to verify the framework functions correctly:

```bash
php verify-php82.php
```

## Upgrading Your Application

When upgrading a ZF1 application to PHP 8.2, watch for these common issues:

1. Dynamic properties in your custom classes - add property declarations or use the `#[AllowDynamicProperties]` attribute

2. Interface implementations - add `#[\ReturnTypeWillChange]` to methods that implement interfaces

3. Deprecated function usage - especially `utf8_encode()`, `utf8_decode()`, `create_function()`, and `each()`

4. String access with curly braces - update `$string{0}` to `$string[0]`
