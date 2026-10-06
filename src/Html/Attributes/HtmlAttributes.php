<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Html\Attributes;

use Averay\HtmlBuilder\Exceptions\IncompatibleAttributeException;
use Twig\Extra\Html\HtmlAttr\MergeableInterface;

/**
 * An immutable set of HTML attributes, which can be rendered as a HTML attributes string.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
final readonly class HtmlAttributes implements \Countable, \IteratorAggregate, \Stringable
{
  /**
   * @param array<string, mixed> $attributes
   */
  public function __construct(
    private array $attributes,
    private AttributesConfig $config,
  ) {}

  /**
   * Renders the attributes as a string.
   *
   * @see AttributesFormatter::format() The formatting logic.
   */
  #[\Override]
  public function __toString(): string
  {
    return AttributesFormatter::format($this->attributes);
  }

  /**
   * @return array<string, mixed> The raw attribute values.
   */
  public function all(): array
  {
    return $this->attributes;
  }

  /**
   * Merges in attributes, with existing values taking priority.
   *
   * @param iterable<string, mixed> $attributes
   */
  public function defaults(iterable $attributes): self
  {
    return $this->cloneWithAttributes($this->merge(self::toArray($attributes), $this->attributes));
  }

  /**
   * Merges in attributes, with new values taking priority.
   *
   * @param iterable<string, mixed> $attributes
   */
  public function add(iterable $attributes): self
  {
    return $this->cloneWithAttributes($this->merge($this->attributes, self::toArray($attributes)));
  }

  /**
   * Extracts the specified attributes.
   */
  public function only(string ...$names): self
  {
    return $this->cloneWithAttributes(\array_intersect_key($this->attributes, \array_flip($names)));
  }

  /**
   * Removes the specified attributes.
   */
  public function without(string ...$names): self
  {
    return $this->cloneWithAttributes(\array_diff_key($this->attributes, \array_flip($names)));
  }

  /**
   * Removes the specified attribute.
   */
  public function remove(string $name): self
  {
    return $this->without($name);
  }

  /**
   * Checks whether an attribute is set.
   *
   * @param bool $strict Whether to consider explicit nulls as present. If not, only actual values are considered present.
   */
  public function has(string $name, bool $strict = true): bool
  {
    return $strict ? \array_key_exists($name, $this->attributes) : isset($this->attributes[$name]);
  }

  #[\Override]
  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->attributes);
  }

  #[\Override]
  public function count(): int
  {
    return \count($this->attributes);
  }

  /**
   * @param array<string, mixed> $base
   * @param array<string, mixed> $override
   *
   * @return array<string, mixed>
   *
   * @throws IncompatibleAttributeException
   */
  private function merge(array $base, array $override): array
  {
    return self::mergeAttributeSets($base, $override, tokenListAttributeNames: $this->config->tokenListAttributeNames);
  }

  /**
   * @param array<string, mixed> $attributes
   */
  private function cloneWithAttributes(array $attributes): self
  {
    return new self($attributes, config: $this->config);
  }

  /**
   * Creates an instance from multiple attribute sets, with later sets overwriting earlier ones.
   *
   * @param list<iterable<string, mixed>|string|false|null> $sets
   */
  public static function createFromSets(array $sets, AttributesConfig $config): self
  {
    /** @var array<string, mixed> $attributes */
    $attributes = [];
    foreach ($sets as $set) {
      // @mago-expect lint:no-empty -- Matching Twig behaviour.
      if (empty($set)) {
        // Skip empty sets
        continue;
      }

      if (\is_string($set)) {
        throw new \InvalidArgumentException('Non-empty strings cannot be combined.');
      }
      $attributes = self::mergeAttributeSets(
        base: $attributes,
        override: self::toArray($set),
        tokenListAttributeNames: [],
      );
    }

    return new self($attributes, config: $config);
  }

  /**
   * Merges two attribute sets, with values from the override set taking priority.
   *
   * @param array<string, mixed> $base
   * @param array<string, mixed> $override
   * @param list<string> $tokenListAttributeNames
   *
   * @return array<string, mixed>
   */
  private static function mergeAttributeSets(array $base, array $override, array $tokenListAttributeNames): array
  {
    foreach ($override as $name => $overrideValue) {
      $baseValue = $base[$name] ?? null;

      $base[$name] = match (true) {
        // Nulls & falses are always overridden
        $baseValue === null, $baseValue === false => $overrideValue,
        // Nulls & falses always override
        $overrideValue === null, $overrideValue === false => $overrideValue,
        // Defer to custom merge implementations
        $overrideValue instanceof MergeableInterface => $overrideValue->mergeInto($baseValue),
        $baseValue instanceof MergeableInterface => $baseValue->appendFrom($overrideValue),
        // Combine iterables
        \is_iterable($baseValue) && \is_iterable($overrideValue) => [
          ...self::valuesToArray($baseValue),
          ...self::valuesToArray($overrideValue),
        ],
        // Append token lists
        \in_array($name, $tokenListAttributeNames, true) => self::mergeTokenValues(
          base: $baseValue,
          override: $overrideValue,
          attributeName: $name,
        ),
        // Reject unsupported iterable and non-iterable combination
        \is_iterable($baseValue) || \is_iterable($overrideValue) => throw new IncompatibleAttributeException(
          attributeName: $name,
          baseValue: $baseValue,
          overrideValue: $overrideValue,
        ),
        // Use override value
        default => $overrideValue,
      };
    }
    return $base;
  }

  /**
   * @return string|array<array-key, mixed> An array of $base with $override merged, or a combined string of both values.
   */
  private static function mergeTokenValues(mixed $base, mixed $override, string $attributeName): string|array
  {
    $baseIsIterable = \is_iterable($base);
    $overrideIsIterable = \is_iterable($override);

    if (!$baseIsIterable && !$overrideIsIterable) {
      // Combining non-iterable values
      if (!self::isStringable($base) || !self::isStringable($override)) {
        throw new IncompatibleAttributeException(
          attributeName: $attributeName,
          baseValue: $base,
          overrideValue: $override,
        );
      }
      return (string) $base . ' ' . (string) $override;
    }

    if ($baseIsIterable xor $overrideIsIterable) {
      // Combining iterable with non-iterable
      if (\str_starts_with($attributeName, 'data-')) {
        // Iterable `data-*` values render as JSON, so cannot safely be combined (cannot determine if caller wants to append or set whole value)
        throw new IncompatibleAttributeException(
          attributeName: $attributeName,
          baseValue: $base,
          overrideValue: $override,
          message: \sprintf(
            'Cannot safely merge iterable with non-iterable values (%s vs %s) for data attribute "%s".',
            \get_debug_type($base),
            \get_debug_type($override),
            $attributeName,
          ),
        );
      }
    }

    return [
      ...($baseIsIterable ? self::valuesToArray($base) : [$base]),
      ...($overrideIsIterable ? self::valuesToArray($override) : [$override]),
    ];
  }

  /**
   * @template TKey of array-key
   * @template TValue
   *
   * @param iterable<TKey, TValue> $values
   *
   * @return array<TKey, TValue>
   */
  private static function toArray(iterable $values): array
  {
    return $values instanceof \Traversable ? \iterator_to_array($values) : $values;
  }

  /**
   * @param iterable<mixed, mixed> $values
   *
   * @return array<array-key, mixed>
   */
  private static function valuesToArray(iterable $values): array
  {
    return \is_array($values) ? $values : \iterator_to_array($values);
  }

  /**
   * @return bool Whether $value can be converted to a string.
   *
   * @psalm-assert-if-true scalar|\Stringable $value
   */
  private static function isStringable(mixed $value): bool
  {
    return \is_scalar($value) || $value instanceof \Stringable;
  }
}
