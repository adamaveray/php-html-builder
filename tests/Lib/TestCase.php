<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests\Lib;

use PHPUnit\Framework\MockObject\MockObject;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
  /**
   * @param iterable<array{ parameters: array<array-key, mixed>, return: mixed }> $invocations
   */
  final protected function expectMultipleInvocations(MockObject $mock, string $method, array $invocations): void
  {
    $mock
      ->expects($this->exactly(\count($invocations)))
      ->method($method)
      ->willReturnCallback(static function (mixed ...$parameters) use ($invocations): mixed {
        foreach ($invocations as $invocation) {
          if ($parameters === $invocation['parameters']) {
            return $invocation['return'];
          }
        }
        self::assertContainsEquals(
          $parameters,
          \array_column($invocations, 'parameters'),
          'Each invocation’s parameters must be defined.',
        );
        self::fail();
      });
  }

  final protected static function assertEqualsIgnoringWhitespace(
    string $expected,
    string $value,
    string $message = '',
  ): void {
    $convert = static fn(string $string): string => \preg_replace('~[\s\n]+~u', '', $string);

    $expected = $convert($expected);
    $value = $convert($value);
    self::assertEquals($expected, $value, $message);
  }
}
