<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests;

use Averay\HtmlBuilder\Css;
use Averay\HtmlBuilder\Html;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversFunction('\\Averay\\HtmlBuilder\\Css\\escapeString')]
#[CoversFunction('\\Averay\\HtmlBuilder\\Html\\escape')]
#[CoversFunction('\\Averay\\HtmlBuilder\\Html\\unescape')]
#[CoversFunction('\\Averay\\HtmlBuilder\\Html\\escapeJsValue')]
final class LibsTest extends TestCase
{
  #[Test]
  #[DataProvider('htmlEscapeUnescapeDataProvider')]
  public function htmlEscapeUnescape(string $expected, string $value): void
  {
    $escaped = Html\escape($value);
    self::assertEquals($expected, $escaped, 'The value should be escaped correctly.');

    $unescaped = Html\unescape($escaped);
    self::assertEquals($value, $unescaped, 'The value should be unescaped correctly.');
  }

  /**
   * @return iterable<string, array{ expected: string, value: string }>
   */
  public static function htmlEscapeUnescapeDataProvider(): iterable
  {
    yield 'Plain' => [
      'expected' => 'Hello world.',
      'value' => 'Hello world.',
    ];

    yield 'HTML' => [
      'expected' => '&lt;p&gt;&apos;Hello&apos; &quot;world&quot;.&lt;/p&gt;',
      'value' => '<p>\'Hello\' "world".</p>',
    ];
  }

  #[Test]
  #[DataProvider('htmlEscapeJsValueDataProvider')]
  public function htmlEscapeJsValue(string $expected, mixed $value, int $flags = 0): void
  {
    self::assertEquals($expected, Html\escapeJsValue($value, $flags), 'The JS value should be escaped correctly.');
  }

  /**
   * @return iterable<string, array{ 0: string, 1: mixed, 2?: int }>
   */
  public static function htmlEscapeJsValueDataProvider(): iterable
  {
    yield 'String' => ['"Hello world."', 'Hello world.'];
    yield 'Integer' => ['123', 123];
    yield 'Float' => ['123.45', 123.45];
    yield 'Boolean' => ['true', true];
    yield 'List' => ['["a","b","c"]', ['a', 'b', 'c']];
    yield 'Associative array' => ['{"a":1,"b":2,"c":3}', ['a' => 1, 'b' => 2, 'c' => 3]];

    yield 'Custom flags' => [
      <<<'TXT'
        {
            "a": 1,
            "b": 2,
            "c": 3
        }
        TXT,
      ['a' => 1, 'b' => 2, 'c' => 3],
      \JSON_PRETTY_PRINT,
    ];

    yield 'Unsafe string' => ['"\\u003CHello world.\\u003E"', '<Hello world.>'];
    yield 'Closing script tag' => ['"\\u003C/script\\u003E"', '</script>', \JSON_UNESCAPED_SLASHES];
  }

  #[Test]
  #[DataProvider('cssEscapeStringDataProvider')]
  public function cssEscapeString(string $expected, string $string): void
  {
    self::assertEquals($expected, Css\escapeString($string), 'The string should be escaped correctly.');
  }

  /**
   * @return iterable<string, array{ string, string }>
   */
  public static function cssEscapeStringDataProvider(): iterable
  {
    yield 'No quotes' => [
      <<<'TXT'
        'hello world'
        TXT,
      <<<'TXT'
        hello world
        TXT,
    ];

    yield 'With quotes' => [
      <<<'TXT'
        'hello \'world\''
        TXT,
      <<<'TXT'
        hello 'world'
        TXT,
    ];
  }
}
