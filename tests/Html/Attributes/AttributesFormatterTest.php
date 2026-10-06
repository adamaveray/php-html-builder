<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests\Html\Attributes;

use Averay\HtmlBuilder\Html\Attributes\AttributesFormatter;
use Averay\HtmlBuilder\Tests\Lib\TestCase;
use League\Uri\UriScheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Twig\Extra\Html\HtmlAttr\InlineStyle;
use Twig\Extra\Html\HtmlAttr\SeparatedTokenList;

/**
 * @internal
 */
#[CoversClass(AttributesFormatter::class)]
final class AttributesFormatterTest extends TestCase
{
  /**
   * @param array<string, mixed> $attributes
   */
  #[Test]
  #[DataProvider('formatDataProvider')]
  public function format(string $expected, array $attributes): void
  {
    self::assertSame($expected, AttributesFormatter::format($attributes));
  }

  /**
   * @return iterable<string, array{ expected: string, attributes: array<string, mixed> }>
   */
  public static function formatDataProvider(): iterable
  {
    $stringable = new class implements \Stringable {
      #[\Override]
      public function __toString(): string
      {
        return 'stringable';
      }
    };

    // Values
    yield 'Empty' => ['expected' => '', 'attributes' => []];
    yield 'Only omitted values' => ['expected' => '', 'attributes' => ['id' => null, 'hidden' => false]];
    yield 'Single' => ['expected' => ' id="a"', 'attributes' => ['id' => 'a']];
    yield 'Multiple' => ['expected' => ' id="a" title="b"', 'attributes' => ['id' => 'a', 'title' => 'b']];
    yield 'Numbers' => ['expected' => ' tabindex="0" step="1.5"', 'attributes' => ['tabindex' => 0, 'step' => 1.5]];
    yield 'Boolean' => ['expected' => ' disabled=""', 'attributes' => ['disabled' => true, 'hidden' => false]];
    yield 'Escaped value' => [
      'expected' => ' title="&lt;a&gt; &amp; &quot;b&quot;"',
      'attributes' => ['title' => '<a> & "b"'],
    ];
    yield 'Prefixed name' => ['expected' => ' xml:lang="en"', 'attributes' => ['xml:lang' => 'en']];
    yield 'Enum' => ['expected' => ' data-scheme="https"', 'attributes' => ['data-scheme' => UriScheme::Https]];
    yield 'Stringable' => ['expected' => ' title="stringable"', 'attributes' => ['title' => $stringable]];

    // ARIA attributes
    yield 'ARIA booleans' => [
      'expected' => ' aria-hidden="false" aria-expanded="true"',
      'attributes' => ['aria-hidden' => false, 'aria-expanded' => true],
    ];
    yield 'ARIA null' => ['expected' => '', 'attributes' => ['aria-hidden' => null]];

    // Data attributes
    yield 'Data booleans' => ['expected' => ' data-a="true"', 'attributes' => ['data-a' => true, 'data-b' => false]];
    yield 'Data JSON' => [
      'expected' => ' data-a="[1,2]" data-b="{&quot;x&quot;:1}"',
      'attributes' => ['data-a' => [1, 2], 'data-b' => ['x' => 1]],
    ];
    yield 'Data Stringable' => ['expected' => ' data-a="stringable"', 'attributes' => ['data-a' => $stringable]];

    // Token lists
    yield 'Token list' => ['expected' => ' class="a b"', 'attributes' => ['class' => ['a', 'b']]];
    yield 'Token list, omitted values' => [
      'expected' => ' class="a b"',
      'attributes' => ['class' => ['a', null, false, 'b']],
    ];
    yield 'Token list, only omitted values' => ['expected' => '', 'attributes' => ['class' => [null, false]]];
    yield 'Token list, true values' => ['expected' => ' class="a"', 'attributes' => ['class' => ['a', true]]];
    yield 'Token list, only true values' => ['expected' => ' class=""', 'attributes' => ['class' => [true]]];
    yield 'Token list, empty' => ['expected' => '', 'attributes' => ['class' => []]];
    yield 'Token list, traversable' => [
      'expected' => ' class="a b"',
      'attributes' => ['class' => new \ArrayIterator(['a', 'b'])],
    ];

    // Styles
    yield 'Style mapping' => [
      'expected' => ' style="color: red; margin: 0;"',
      'attributes' => ['style' => ['color' => 'red', 'margin' => 0]],
    ];
    yield 'Style declarations' => [
      'expected' => ' style="color: red; margin: 0;"',
      'attributes' => ['style' => ['color: red;', ' margin: 0 ']],
    ];
    yield 'Style, omitted values' => [
      'expected' => ' style="color: red;"',
      'attributes' => ['style' => ['color' => 'red', 'a' => null, 'b' => false, 'c' => true, 'd' => '', 'e' => []]],
    ];
    yield 'Style, only omitted values' => ['expected' => '', 'attributes' => ['style' => ['a' => null]]];

    // HTML Extra values
    yield 'HTML Extra token list' => [
      'expected' => ' srcset="a.jpg 1x, b.jpg 2x"',
      'attributes' => ['srcset' => new SeparatedTokenList(['a.jpg 1x', 'b.jpg 2x'], ', ')],
    ];
    yield 'HTML Extra style' => [
      'expected' => ' style="color: red;"',
      'attributes' => ['style' => new InlineStyle(['color' => 'red'])],
    ];
    yield 'HTML Extra value in data attribute' => [
      'expected' => ' data-a="x y"',
      'attributes' => ['data-a' => new SeparatedTokenList(['x', 'y'])],
    ];
  }

  /**
   * @param array<string, mixed> $attributes
   */
  #[Test]
  #[DataProvider('formatInvalidDataProvider')]
  public function formatInvalid(array $attributes): void
  {
    self::assertThrows(
      static function () use ($attributes): void {
        AttributesFormatter::format($attributes);
      },
      test: static fn(\Throwable $exception): bool => $exception instanceof \InvalidArgumentException,
    );
  }

  /**
   * @return iterable<string, array{ attributes: array<string, mixed> }>
   */
  public static function formatInvalidDataProvider(): iterable
  {
    yield 'Invalid name' => ['attributes' => ['a b' => 'c']];
    yield 'Non-stringable object' => ['attributes' => ['title' => new \stdClass()]];
    yield 'Non-stringable token' => ['attributes' => ['class' => [new \stdClass()]]];
    yield 'Non-stringable style value' => ['attributes' => ['style' => ['color' => new \stdClass()]]];
    yield 'Unencodable data' => ['attributes' => ['data-a' => [\NAN]]];
  }
}
