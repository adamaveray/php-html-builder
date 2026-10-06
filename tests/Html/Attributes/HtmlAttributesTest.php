<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests\Html\Attributes;

use Averay\HtmlBuilder\Exceptions\IncompatibleAttributeException;
use Averay\HtmlBuilder\Html\Attributes\AttributesConfig;
use Averay\HtmlBuilder\Html\Attributes\HtmlAttributes;
use Averay\HtmlBuilder\Tests\Lib\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Twig\Extra\Html\HtmlAttr\SeparatedTokenList;

/**
 * @internal
 */
#[CoversClass(HtmlAttributes::class)]
final class HtmlAttributesTest extends TestCase
{
  #[Test]
  public function rendering(): void
  {
    self::assertSame(' id="a" title="b"', (string) self::makeAttributes(['id' => 'a', 'title' => 'b']));
  }

  /**
   * @param array<string, mixed> $expected
   * @param array<string, mixed> $initial
   * @param array<string, mixed> $attributes
   * @param list<string>|null $tokenListAttributeNames
   */
  #[Test]
  #[DataProvider('mergingDataProvider')]
  public function merging(
    array $expected,
    string $method,
    array $initial,
    array $attributes,
    ?array $tokenListAttributeNames = null,
  ): void {
    $config = $tokenListAttributeNames === null
      ? AttributesConfig::createDefault()
      : new AttributesConfig(tokenListAttributeNames: $tokenListAttributeNames);
    $original = self::makeAttributes($initial, $config);
    $merged = match ($method) {
      'defaults' => $original->defaults($attributes),
      'add' => $original->add($attributes),
      default => throw new \InvalidArgumentException('Unknown method.'),
    };
    self::assertEquals($expected, $merged->all());
    self::assertSame($initial, $original->all(), 'The original instance should not be modified.');
  }

  /**
   * @return iterable<string, array{
   *   expected: array<string, mixed>,
   *   method: string,
   *   initial: array<string, mixed>,
   *   attributes: array<string, mixed>,
   *   tokenListAttributeNames?: list<string>,
   * }>
   */
  public static function mergingDataProvider(): iterable
  {
    yield 'Defaults, new attribute' => [
      'expected' => ['title' => 'b', 'id' => 'a'],
      'method' => 'defaults',
      'initial' => ['id' => 'a'],
      'attributes' => ['title' => 'b'],
    ];
    yield 'Defaults, existing wins' => [
      'expected' => ['id' => 'one'],
      'method' => 'defaults',
      'initial' => ['id' => 'one'],
      'attributes' => ['id' => 'two'],
    ];
    yield 'Defaults, existing null removes' => [
      'expected' => ['id' => null],
      'method' => 'defaults',
      'initial' => ['id' => null],
      'attributes' => ['id' => 'two'],
    ];
    yield 'Defaults, existing false removes' => [
      'expected' => ['class' => false],
      'method' => 'defaults',
      'initial' => ['class' => false],
      'attributes' => ['class' => 'base'],
    ];
    yield 'Defaults, default false replaced' => [
      'expected' => ['class' => 'extra'],
      'method' => 'defaults',
      'initial' => ['class' => 'extra'],
      'attributes' => ['class' => false],
    ];
    yield 'Defaults, class strings joined' => [
      'expected' => ['class' => 'base extra'],
      'method' => 'defaults',
      'initial' => ['class' => 'extra'],
      'attributes' => ['class' => 'base'],
    ];
    yield 'Defaults, Stimulus strings joined' => [
      'expected' => ['data-controller' => 'base extra', 'data-action' => 'base->a extra->b'],
      'method' => 'defaults',
      'initial' => ['data-controller' => 'extra', 'data-action' => 'extra->b'],
      'attributes' => ['data-controller' => 'base', 'data-action' => 'base->a'],
    ];
    yield 'Defaults, class string and list joined' => [
      'expected' => ['class' => ['base', 'a', 'b']],
      'method' => 'defaults',
      'initial' => ['class' => ['a', 'b']],
      'attributes' => ['class' => 'base'],
    ];
    yield 'Defaults, class string and keyed list joined' => [
      'expected' => ['class' => ['base', 'size' => 'small']],
      'method' => 'defaults',
      'initial' => ['class' => ['size' => 'small']],
      'attributes' => ['class' => 'base'],
    ];
    yield 'Defaults, lists joined' => [
      'expected' => ['class' => ['base', 'a']],
      'method' => 'defaults',
      'initial' => ['class' => ['a']],
      'attributes' => ['class' => ['base']],
    ];
    yield 'Defaults, mergeable values' => [
      'expected' => ['rel' => new SeparatedTokenList(['base', 'a'])],
      'method' => 'defaults',
      'initial' => ['rel' => new SeparatedTokenList(['a'])],
      'attributes' => ['rel' => ['base']],
    ];
    yield 'Defaults, custom token list attribute' => [
      'expected' => ['rel' => 'base extra', 'class' => 'extra'],
      'method' => 'defaults',
      'initial' => ['rel' => 'extra', 'class' => 'extra'],
      'attributes' => ['rel' => 'base', 'class' => 'base'],
      'tokenListAttributeNames' => ['rel'],
    ];
    yield 'Add, new attribute' => [
      'expected' => ['id' => 'a', 'title' => 'b'],
      'method' => 'add',
      'initial' => ['id' => 'a'],
      'attributes' => ['title' => 'b'],
    ];
    yield 'Add, new wins' => [
      'expected' => ['id' => 'two'],
      'method' => 'add',
      'initial' => ['id' => 'one'],
      'attributes' => ['id' => 'two'],
    ];
    yield 'Add, new null removes' => [
      'expected' => ['id' => null],
      'method' => 'add',
      'initial' => ['id' => 'one'],
      'attributes' => ['id' => null],
    ];
    yield 'Add, class strings joined' => [
      'expected' => ['class' => 'base extra'],
      'method' => 'add',
      'initial' => ['class' => 'base'],
      'attributes' => ['class' => 'extra'],
    ];
    yield 'Add, class list and string joined' => [
      'expected' => ['class' => ['a', 'extra']],
      'method' => 'add',
      'initial' => ['class' => ['a']],
      'attributes' => ['class' => 'extra'],
    ];
    yield 'Add, no token list attributes' => [
      'expected' => ['class' => 'extra'],
      'method' => 'add',
      'initial' => ['class' => 'base'],
      'attributes' => ['class' => 'extra'],
      'tokenListAttributeNames' => [],
    ];
  }

  /**
   * @param array<string, mixed> $initial
   * @param array<string, mixed> $attributes
   */
  #[Test]
  #[DataProvider('mergingIncompatibleValuesDataProvider')]
  public function mergingIncompatibleValues(array $initial, array $attributes): void
  {
    $original = self::makeAttributes($initial);
    self::assertThrows(
      static function () use ($original, $attributes): void {
        $original->defaults($attributes);
      },
      test: static fn(\Throwable $exception): bool => $exception instanceof IncompatibleAttributeException,
    );
  }

  /**
   * @return iterable<string, array{
   *   initial: array<string, mixed>,
   *   attributes: array<string, mixed>,
   * }>
   */
  public static function mergingIncompatibleValuesDataProvider(): iterable
  {
    yield 'Data list and string' => [
      'initial' => ['data-controller' => ['a']],
      'attributes' => ['data-controller' => 'base'],
    ];
    yield 'Data string and list' => [
      'initial' => ['data-controller' => 'a'],
      'attributes' => ['data-controller' => ['base']],
    ];
    yield 'Non-token list and string' => [
      'initial' => ['title' => ['a']],
      'attributes' => ['title' => 'base'],
    ];
    yield 'Non-stringable token values' => [
      'initial' => ['class' => new \stdClass()],
      'attributes' => ['class' => 'base'],
    ];
  }

  #[Test]
  public function mergingFromAttributesInstance(): void
  {
    $attributes = self::makeAttributes(['id' => 'a'])->defaults(self::makeAttributes(['title' => 'b']));
    self::assertSame(['title' => 'b', 'id' => 'a'], $attributes->all());
  }

  #[Test]
  public function configPreservedWhenChaining(): void
  {
    $attributes = self::makeAttributes(['id' => 'a', 'class' => 'extra'], new AttributesConfig(['class']))
      ->without('id')
      ->defaults(['class' => 'base']);
    self::assertSame(['class' => 'base extra'], $attributes->all());
  }

  /**
   * @param array<string, mixed> $expected
   * @param list<iterable<string, mixed>|string|false|null> $sets
   */
  #[Test]
  #[DataProvider('createFromSetsDataProvider')]
  public function createFromSets(array $expected, array $sets): void
  {
    $attributes = HtmlAttributes::createFromSets($sets, config: AttributesConfig::createDefault());
    self::assertEquals($expected, $attributes->all());
  }

  /**
   * @return iterable<string, array{
   *   expected: array<string, mixed>,
   *   sets: list<iterable<string, mixed>|string|false|null>,
   * }>
   */
  public static function createFromSetsDataProvider(): iterable
  {
    yield 'None' => ['expected' => [], 'sets' => []];
    yield 'Later sets win' => [
      'expected' => ['id' => 'b', 'title' => 'c'],
      'sets' => [['id' => 'a'], ['id' => 'b', 'title' => 'c']],
    ];
    yield 'Class strings replaced' => [
      'expected' => ['class' => 'b'],
      'sets' => [['class' => 'a'], ['class' => 'b']],
    ];
    yield 'Lists joined' => [
      'expected' => ['class' => ['a', 'b']],
      'sets' => [['class' => ['a']], ['class' => ['b']]],
    ];
    yield 'Empty sets skipped' => [
      'expected' => ['id' => 'a'],
      'sets' => [['id' => 'a'], '', '0', false, null, []],
    ];
    yield 'Traversable set' => [
      'expected' => ['id' => 'a'],
      'sets' => [new \ArrayIterator(['id' => 'a'])],
    ];
  }

  #[Test]
  public function createFromSetsConfigApplied(): void
  {
    $attributes = HtmlAttributes::createFromSets([['class' => 'extra']], config: new AttributesConfig(['class']));
    self::assertSame(['class' => 'base extra'], $attributes->defaults(['class' => 'base'])->all());
  }

  /**
   * @param list<iterable<string, mixed>|string|false|null> $sets
   * @param class-string<\Throwable> $exceptionClass
   */
  #[Test]
  #[DataProvider('createFromSetsInvalidDataProvider')]
  public function createFromSetsInvalid(array $sets, string $exceptionClass): void
  {
    self::assertThrows(
      static function () use ($sets): void {
        HtmlAttributes::createFromSets($sets, config: AttributesConfig::createDefault());
      },
      test: static fn(\Throwable $exception): bool => $exception instanceof $exceptionClass,
    );
  }

  /**
   * @return iterable<string, array{
   *   sets: list<iterable<string, mixed>|string|false|null>,
   *   exceptionClass: class-string<\Throwable>,
   * }>
   */
  public static function createFromSetsInvalidDataProvider(): iterable
  {
    yield 'Non-empty string' => [
      'sets' => ['id="a"'],
      'exceptionClass' => \InvalidArgumentException::class,
    ];
    yield 'List and string' => [
      'sets' => [['class' => ['a']], ['class' => 'b']],
      'exceptionClass' => IncompatibleAttributeException::class,
    ];
    yield 'String and list' => [
      'sets' => [['class' => 'a'], ['class' => ['b']]],
      'exceptionClass' => IncompatibleAttributeException::class,
    ];
  }

  #[Test]
  public function filtering(): void
  {
    $attributes = self::makeAttributes(['id' => 'a', 'title' => 'b', 'class' => 'c']);

    self::assertSame(['id' => 'a', 'class' => 'c'], $attributes->only('class', 'id')->all());
    self::assertSame(['title' => 'b'], $attributes->without('id', 'class')->all());
    self::assertSame(['id' => 'a', 'class' => 'c'], $attributes->remove('title')->all());
    self::assertSame(
      ['id' => 'a', 'title' => 'b', 'class' => 'c'],
      $attributes->all(),
      'The original instance should not be modified.',
    );
  }

  #[Test]
  #[DataProvider('hasDataProvider')]
  public function has(bool $expected, string $name, bool $strict): void
  {
    $attributes = self::makeAttributes(['id' => 'a', 'title' => null, 'hidden' => false]);
    self::assertSame($expected, $attributes->has($name, $strict));
  }

  /**
   * @return iterable<string, array{ expected: bool, name: string, strict: bool }>
   */
  public static function hasDataProvider(): iterable
  {
    yield 'Strict, value' => ['expected' => true, 'name' => 'id', 'strict' => true];
    yield 'Strict, null' => ['expected' => true, 'name' => 'title', 'strict' => true];
    yield 'Strict, false' => ['expected' => true, 'name' => 'hidden', 'strict' => true];
    yield 'Strict, missing' => ['expected' => false, 'name' => 'class', 'strict' => true];
    yield 'Non-strict, value' => ['expected' => true, 'name' => 'id', 'strict' => false];
    yield 'Non-strict, null' => ['expected' => false, 'name' => 'title', 'strict' => false];
    yield 'Non-strict, false' => ['expected' => true, 'name' => 'hidden', 'strict' => false];
    yield 'Non-strict, missing' => ['expected' => false, 'name' => 'class', 'strict' => false];
  }

  #[Test]
  public function inspection(): void
  {
    $attributes = self::makeAttributes(['id' => 'a', 'title' => null]);

    self::assertCount(2, $attributes);
    self::assertSame(['id' => 'a', 'title' => null], \iterator_to_array($attributes));
  }

  /**
   * @param array<string, mixed> $attributes
   */
  private static function makeAttributes(array $attributes, ?AttributesConfig $config = null): HtmlAttributes
  {
    return new HtmlAttributes($attributes, config: $config ?? AttributesConfig::createDefault());
  }
}
