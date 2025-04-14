<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests;

use Averay\HtmlBuilder\Css;
use Averay\HtmlBuilder\Html;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversFunction('\\Averay\\HtmlBuilder\\Css\\escapeString')]
#[CoversFunction('\\Averay\\HtmlBuilder\\Html\\escape')]
#[CoversFunction('\\Averay\\HtmlBuilder\\Html\\escapeJson')]
final class LibsTest extends TestCase
{
  #[DataProvider('htmlEscapeDataProvider')]
  public function testHtmlEscape(string $expected, string $value): void
  {
    self::assertEquals($expected, Html\escape($value), 'The value should be escaped correctly.');
  }

  public static function htmlEscapeDataProvider(): iterable
  {
    yield 'Safe' => [
      'expected' => 'Hello world.',
      'value' => 'Hello world.',
    ];

    yield 'Unsafe' => [
      'expected' => '&lt;p&gt;&apos;Hello&apos; &quot;world&quot;.&lt;/p&gt;',
      'value' => '<p>\'Hello\' "world".</p>',
    ];
  }

  #[DataProvider('htmlEscapeJsonDataProvider')]
  public function testHtmlEscapeJson(string $expected, mixed $value, int $flags = 0): void
  {
    self::assertEquals($expected, Html\escapeJson($value, $flags), 'The JSON value should be escaped correctly.');
  }

  public static function htmlEscapeJsonDataProvider(): iterable
  {
    yield 'String' => ['&quot;Hello world.&quot;', 'Hello world.'];
    yield 'Integer' => ['123', 123];
    yield 'Float' => ['123.45', 123.45];
    yield 'Boolean' => ['true', true];
    yield 'List' => ['[&quot;a&quot;,&quot;b&quot;,&quot;c&quot;]', ['a', 'b', 'c']];
    yield 'Associative array' => ['{&quot;a&quot;:1,&quot;b&quot;:2,&quot;c&quot;:3}', ['a' => 1, 'b' => 2, 'c' => 3]];

    yield 'Custom flags' => [
      <<<'TXT'
      {
          &quot;a&quot;: 1,
          &quot;b&quot;: 2,
          &quot;c&quot;: 3
      }
      TXT
      ,
      ['a' => 1, 'b' => 2, 'c' => 3],
      \JSON_PRETTY_PRINT,
    ];
  }

  #[DataProvider('cssEscapeStringDataProvider')]
  public function testCssEscapeString(string $expected, string $string): void
  {
    self::assertEquals($expected, Css\escapeString($string), 'The string should be escaped correctly.');
  }

  public static function cssEscapeStringDataProvider(): iterable
  {
    yield 'No quotes' => [
      <<<'TXT'
      'hello world'
      TXT
      ,
      <<<'TXT'
      hello world
      TXT
    ,
    ];

    yield 'With quotes' => [
      <<<'TXT'
      'hello \'world\''
      TXT
      ,
      <<<'TXT'
      hello 'world'
      TXT
    ,
    ];
  }
}
