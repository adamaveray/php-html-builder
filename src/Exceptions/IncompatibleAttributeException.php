<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Exceptions;

final class IncompatibleAttributeException extends \RuntimeException
{
  public function __construct(
    public readonly string $attributeName,
    public readonly mixed $baseValue,
    public readonly mixed $overrideValue,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = null,
  ) {
    if ($message === '') {
      $message = \sprintf(
        'Cannot merge incompatible value %s with %s for attribute "%s".',
        \get_debug_type($baseValue),
        \get_debug_type($overrideValue),
        $attributeName,
      );
    }

    parent::__construct($message, $code, $previous);
  }
}
