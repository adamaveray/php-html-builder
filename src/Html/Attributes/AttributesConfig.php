<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Html\Attributes;

final readonly class AttributesConfig
{
  private const array DEFAULT_TOKEN_LIST_ATTRIBUTE_NAMES = ['class', 'data-controller', 'data-action'];

  /**
   * @param list<string> $tokenListAttributeNames HTML attributes containing space-separated lists.
   */
  public function __construct(
    public array $tokenListAttributeNames,
  ) {}

  public static function createDefault(): self
  {
    return new self(tokenListAttributeNames: self::DEFAULT_TOKEN_LIST_ATTRIBUTE_NAMES);
  }
}
