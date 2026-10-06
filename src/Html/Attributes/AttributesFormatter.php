<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Html\Attributes;

use Twig\Extra\Html\HtmlAttr\AttributeValueInterface;

use function Averay\HtmlBuilder\Html\escape;

/**
 * Formats attributes as HTML.
 *
 * The output is designed to match Twig HTML Extra's `html_attr` function's value handling.
 */
final readonly class AttributesFormatter
{
  /**
   * @see https://www.w3.org/TR/2011/WD-html5-20110525/syntax.html#attributes-0
   */
  private const array INVALID_NAME_CHARS = [
    "\u{0020}", // SPACE
    "\u{0009}", // CHARACTER TABULATION (tab)
    "\u{000A}", // LINE FEED (LF)
    "\u{000C}", // FORM FEED (FF)
    "\u{000D}", // CARRIAGE RETURN (CR)
    "\u{0000}", // NULL
    "\u{0022}", // QUOTATION MARK (")
    "\u{0027}", // APOSTROPHE (')
    "\u{003E}", // GREATER-THAN SIGN (>)
    "\u{002F}", // SOLIDUS (/)
    "\u{003D}", // EQUALS SIGN (=)
    "\u{003C}", // LESS-THAN SIGN (<) (Technically allowed but causes issues with some parsers.)
  ];

  /**
   * Formats the attributes with each preceded by a space, so an empty string is returned when there are no attributes to output.
   *
   * - `null` and `false` values omit the attribute.
   * - `true` outputs an empty value.
   * - `aria-*` attribute booleans are converted to their textual representations.
   * - `data-*` attribute `true` is converted to its textual representation, and non-scalar values are encoded as JSON.
   * - `style` iterable values are joined as style declarations.
   * - Other iterable values are joined as space-separated lists.
   *
   * @param array<string, mixed> $attributes
   *
   * @return string Formatted HTML attributes safe for use in a HTML document.
   *
   * @throws \InvalidArgumentException If an attribute name or value cannot be formatted.
   */
  public static function format(array $attributes): string
  {
    $output = '';
    foreach ($attributes as $name => $value) {
      if (!self::isValidAttributeName($name)) {
        throw new \InvalidArgumentException(\sprintf('Invalid attribute name "%s".', $name));
      }

      $value = self::formatValue($name, $value);
      if ($value === null) {
        continue;
      }
      $output .= \sprintf(' %s="%s"', $name, escape($value));
    }
    return $output;
  }

  /**
   * @return string|null The unescaped value, or null if the attribute should be omitted.
   */
  private static function formatValue(string $name, mixed $value): ?string
  {
    if ($value instanceof AttributeValueInterface) {
      // Custom-formatted value
      return $value->getValue();
    }

    if ($value instanceof \BackedEnum) {
      $value = $value->value;
    }

    if (\str_starts_with($name, 'aria-')) {
      // ARIA attributes require textual booleans
      if (\is_bool($value)) {
        return $value ? 'true' : 'false';
      }

      // Use default formatting
    }

    if (\str_starts_with($name, 'data-')) {
      // Data attributes are encoded specially
      if ($value === true) {
        return 'true';
      }
      if ($value !== null && !\is_scalar($value) && !$value instanceof \Stringable) {
        try {
          return \json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
          throw new \InvalidArgumentException(
            \sprintf('The "%s" attribute value cannot be JSON encoded.', $name),
            previous: $exception,
          );
        }
      }

      // Use default formatting
    }

    if ($name === 'style') {
      // Styles are formatted as declarations
      if (\is_iterable($value)) {
        return self::formatStyleDeclarations($value);
      }

      // Use default formatting
    }

    return match (true) {
      $value === null, $value === false => null,
      $value === true => '',
      \is_iterable($value) => self::formatTokenList($name, $value),
      default => self::stringifyAttribute($name, $value),
    };
  }

  /**
   * @param iterable<mixed, mixed> $values
   *
   * @return string|null The space-separated values, or null if there are no values to output.
   */
  private static function formatTokenList(string $name, iterable $values): ?string
  {
    /** @var list<string> $tokens */
    $tokens = [];
    $hasValue = false;
    foreach ($values as $value) {
      if ($value === null || $value === false) {
        continue;
      }

      $hasValue = true;
      if ($value !== true) {
        $tokens[] = self::stringifyAttribute($name, $value);
      }
    }
    if (!$hasValue) {
      return null;
    }

    return \trim(\implode(' ', $tokens));
  }

  /**
   * @param iterable<mixed, mixed> $declarations Property names mapped to values or complete declarations.
   *
   * @return string|null The inline style declarations, or null if there are no declarations to output.
   */
  private static function formatStyleDeclarations(iterable $declarations): ?string
  {
    $style = '';
    foreach ($declarations as $property => $value) {
      if ($value === null || $value === false || $value === true || $value === '' || $value === []) {
        continue;
      }

      $value = self::stringifyAttribute('style', $value);
      $style .= \is_numeric($property)
        // Value-only array item
        ? \trim($value, '; ') . '; '
        // Keyed array item
        : \sprintf('%s: %s; ', self::stringifyAttribute('style', $property), $value);
    }

    $style = \trim($style);
    if ($style === '') {
      return null;
    }

    return $style;
  }

  /**
   * @throws \InvalidArgumentException If the value cannot be converted to a string.
   */
  private static function stringifyAttribute(string $name, mixed $value): string
  {
    if (!\is_scalar($value) && !$value instanceof \Stringable) {
      throw new \InvalidArgumentException(\sprintf(
        'The "%s" attribute value should be a scalar, an iterable, or stringable, but is "%s".',
        $name,
        \get_debug_type($value),
      ));
    }
    return (string) $value;
  }

  private static function isValidAttributeName(string $name): bool
  {
    $pattern = '~[' . \preg_quote(\implode('', self::INVALID_NAME_CHARS), '~') . ']~';
    return \preg_match($pattern, $name) === 0;
  }
}
