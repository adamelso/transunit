Transunit
===

Convert PhpSpec tests to PHPUnit using the Prophecy library.

This tool takes a different approach to [Rector's implementation][1].
Whilst Rector will convert to PHPUnit's internal mocking library, this
tool will instead rewrite the test to use PhpSpec's Prophecy library
instead within PHPUnit. This tool also has a more linear pipeline.

Dependencies
---

 - nikic/php-parser
 - symfony/finder
 - symfony/filesystem

Usage
---

```bash
php ./transunit.php spec tests/unit
```

[1]: https://github.com/rectorphp/custom-phpspec-to-phpunit/tree/main
