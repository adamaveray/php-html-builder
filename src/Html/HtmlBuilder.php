<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Html;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

final readonly class HtmlBuilder
{
  /**
   * @see https://www.w3.org/TR/2011/WD-html5-20110525/syntax.html#attributes-0
   */
  private const INVALID_ATTR_NAME_CHARS = [
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

  private const PRELOAD_TYPES = [
    'audio',
    'document',
    'embed',
    'fetch',
    'font',
    'image',
    'object',
    'script',
    'style',
    'track',
    'video',
    'worker',
  ];

  /**
   * HTML element attributes that contain or reference an element ID.
   */
  private const ID_ATTRIBUTES = ['id', 'for', 'aria-labelledby', 'aria-describedby'];

  public function __construct(private MimeTypeGuesserInterface $mimeTypesGuesser) {}

  /**
   * Formats a collection of attributes for HTML.
   *
   * - For standard attributes, `null` or `false` will omit the attribute, while `true` will output only the attribute name.
   * - For ARIA attributes, booleans will be converted to their required textual representations.
   * - Other attributes will be directly converted to strings.
   *
   * Multiple attribute arrays can be provided which will be merged, with duplicate values intelligently merged (either combined or overwritten depending on the attribute).
   *
   * For simple attributes the attribute name alone can be specified without a corresponding value, i.e. `["attribute-name"]` is equivalent to `["attribute-name" => true]`.
   *
   * @param array<array-key, \Stringable|scalar|null> ...$attrs
   * @return string Formatted HTML attributes safe for use in a HTML document.
   */
  public function buildAttrs(array ...$attrs): string
  {
    /** @var array<array-key, \Stringable|scalar|null> $mergedAttrs */
    $mergedAttrs = [];
    foreach ($attrs as $attrSet) {
      foreach ($attrSet as $name => $value) {
        if (!isset($mergedAttrs[$name])) {
          $mergedAttrs[$name] = $value;
          continue;
        }

        $currentValue = $mergedAttrs[$name];
        $mergedAttrs[$name] = match (true) {
          $name === 'class' => $this->addClasses((string) $currentValue, (string) $value),
          default => $value,
        };
      }
    }

    $pairs = [];
    foreach ($mergedAttrs as $name => $value) {
      if (\is_int($name)) {
        // Value-less - assume boolean
        $name = (string) $value;
        $value = true;
      }

      if (!self::attrNameIsValid($name)) {
        throw new \InvalidArgumentException(\sprintf('Invalid attribute name: %s', $name));
      }

      $formattedValue = $this->formatAttr($name, $value);
      if ($formattedValue !== null) {
        $pairs[] = $formattedValue;
      }
    }
    return implode(' ', $pairs);
  }

  /**
   * @param array<string, bool> $classes Classes
   * @return string A HTML-safe class list.
   */
  public function buildClasses(array $classes): string
  {
    $classes = \array_filter($classes);
    return escape(implode(' ', \array_keys($classes)));
  }

  /**
   * @param string $classList A space-separated HTML element class list.
   * @param string ...$additionalClasses Additional class names to append to the class list.
   * @return string The initial class list with the additional classes appended.
   */
  public function addClasses(string $classList, string ...$additionalClasses): string
  {
    $classList = \trim($classList);
    foreach ($additionalClasses as $additionalClass) {
      if ($classList !== '') {
        $classList .= ' ';
      }
      $classList .= $additionalClass;
    }
    return $classList;
  }

  /**
   * @param string $url A stylesheet’s relative or absolute URL.
   * @param string|null $media A conditional media query.
   * @param string|null $integrity The subresource integrity hash for the stylesheet.
   * @param "anonymous"|"use-credentials"|null $crossorigin
   * @return string HTML to load the stylesheet.
   */
  public function buildStylesheet(
    string $url,
    ?string $media = null,
    ?string $integrity = null,
    ?string $crossorigin = null,
  ): string {
    $attrs = [
      'rel' => 'stylesheet',
      'href' => $url,
      'media' => $media,
      'integrity' => $integrity,
      'crossorigin' => $crossorigin,
    ];
    return '<link ' . $this->buildAttrs($attrs) . '/>';
  }

  /**
   * @param string $url A script’s relative or absolute URL.
   * @param "module"|null $type A script type ("media").
   * @param bool $async Whether to load the script asynchronously.
   * @param string|null $integrity The subresource integrity hash for the script.
   * @param "anonymous"|"use-credentials"|null $crossorigin
   * @return string HTML to load the script.
   */
  public function buildScript(
    string $url,
    ?string $type = null,
    bool $async = false,
    ?string $integrity = null,
    ?string $crossorigin = null,
  ): string {
    $attrs = [
      'src' => $url,
      'type' => $type,
      'async' => $async,
      'integrity' => $integrity,
      'crossorigin' => $crossorigin,
    ];
    return '<script ' . $this->buildAttrs($attrs) . '></script>';
  }

  /**
   * @psalm-type FullResource = array{
   *   url: string,
   *   integrity?: string,
   *   crossorigin?: string,
   * }
   * @param array<value-of<self::PRELOAD_TYPES>, string|list<string|FullResource>> $preloads A number of resource types mapped to one or more URLs to preload.
   * @param list<string> $preconnectHosts A number of hosts (including protocols) to preconnect to.
   * @return string HTML to preload & preconnect to the provided destinations.
   */
  public function buildPreloadLinks(array $preloads, array $preconnectHosts = []): string
  {
    $html = '';
    foreach ($preloads as $type => $entries) {
      /** @psalm-suppress DocblockTypeContradiction Manually validating. */
      if (!\in_array($type, self::PRELOAD_TYPES, true)) {
        /** @psalm-suppress NoValue Manually validating. */
        throw new \OutOfBoundsException(\sprintf('Invalid preload type "%s".', $type));
      }
      foreach ((array) $entries as $entry) {
        if (\is_string($entry)) {
          $entry = ['url' => $entry];
        }
        $html .=
          '<link ' .
          $this->buildAttrs([
            'rel' => 'preload',
            'href' => $entry['url'],
            'as' => $type,
            'integrity' => $entry['integrity'] ?? null,
            'crossorigin' => $entry['crossorigin'] ?? null,
          ]) .
          '/>';
      }
    }
    foreach ($preconnectHosts as $preconnectHost) {
      $html .= '<link ' . $this->buildAttrs(['rel' => 'preconnect', 'href' => $preconnectHost]) . '/>';
    }
    return $html;
  }

  /**
   * @param array<string, string> $entries An array with URLs as keys, each mapped to a corresponding `srcset`-compatible size (e.g. a pixel amount or density).
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Responsive_images
   */
  public function buildSrcSet(array $entries): string
  {
    $formatted = [];
    foreach ($entries as $url => $size) {
      $formatted[] = \sprintf('%s %s', $url, $size);
    }
    return escape(\implode(', ', $formatted));
  }

  /**
   * Wraps each paragraph in a given string with a specified HTML tag.
   *
   * @param string $string A plain text string.
   * @param string $wrappingTag The opening tag of the HTML element to wrap each paragraph with (e.g. `<p class="example">`).
   * @return string The HTML representation of the given string with paragraphs wrapped.
   */
  public function wrapParagraphs(string $string, string $wrappingTag = '<p>'): string
  {
    ['open' => $tagOpen, 'close' => $tagClose] = self::parseHtmlTag($wrappingTag);

    // Split into paragraphs
    $paragraphs = \preg_split('~\R{2,}~', \trim($string));
    \assert(\is_array($paragraphs));
    // Trim surrounding & inter-line whitespace
    $paragraphs = \array_map(static function (string $paragraph): string {
      $result = \preg_replace('~^\s*(.*?)\s*$~m', '$1', $paragraph);
      \assert(\is_string($result));
      return $result;
    }, $paragraphs);
    // Remove empty paragraphs
    $paragraphs = \array_filter($paragraphs, static fn(string $paragraph): bool => $paragraph !== '');

    return \implode(
      '',
      \array_map(static fn(string $paragraph): string => $tagOpen . escape($paragraph) . $tagClose, $paragraphs),
    );
  }

  /**
   * Wraps each word in a given string with a specified HTML tag.
   *
   * @param string $string
   * @param string $wrappingTag
   */
  public function wrapWords(string $string, string $wrappingTag): string
  {
    ['open' => $tagOpen, 'close' => $tagClose] = self::parseHtmlTag($wrappingTag);

    $result = \preg_replace_callback(
      '~(\S+)~',
      static function (array $matches) use ($tagOpen, $tagClose): string {
        \assert(isset($matches[1]));
        return $tagOpen . escape($matches[1]) . $tagClose;
      },
      $string,
    );
    return $result ?? throw new \RuntimeException('Failed processing string.');
  }

  /**
   * @param array<string, string> $parameters
   */
  public function generateDataUri(string $data, ?string $mime = null, array $parameters = []): string
  {
    $mime ??= $this->inferMimeType($data) ?? throw new \UnexpectedValueException('Failed inferring MIME type.');
    $dataUri = 'data:' . $mime;
    foreach ($parameters as $key => $value) {
      $dataUri .= \sprintf(';%s=%s', $key, \rawurlencode($value));
    }
    $dataUri .= \str_starts_with($mime, 'text/') ? ',' . \rawurlencode($data) : ';base64,' . \base64_encode($data);
    return $dataUri;
  }

  /**
   * Applies a transformation function to ID and ID-referencing attributes within the given HTML string.
   *
   * Note this is currently a naïve implementation using regular expressions and so can cause invalid transformations.
   *
   * @param string $html A fragment of HTML to transform.
   * @param callable(string $attributeValue, string $attributeName):string $transformer A callable to process the attribute value. The attribute’s value will be set to the returned string.
   * @param list<string> $additionalAttributes Names of additional attributes to process.
   * @see self::ID_ATTRIBUTES Attributes that will be processed by default.
   *
   * @todo Parse the HTML and apply transformations to the loaded DOM.
   */
  public function mapHtmlIds(string $html, callable $transformer, array $additionalAttributes = []): string
  {
    $attributePatterns = \array_map(
      static fn(string $attributeName): string => \preg_quote($attributeName, '~'),
      \array_merge(self::ID_ATTRIBUTES, $additionalAttributes),
    );
    $result = \preg_replace_callback(
      '~\b(' . \implode('|', $attributePatterns) . ')=(["\'])(.*?)\\2~i',
      static function (array $matches) use ($transformer): string {
        [, $attributeName, $quote, $attributeValue] = $matches;
        $attributeValue = $transformer($attributeValue, $attributeName);
        return escape($attributeName) . '=' . $quote . escape($attributeValue) . $quote;
      },
      $html,
    );
    \assert(\is_string($result));
    return $result;
  }

  private function inferMimeType(string $data): ?string
  {
    $tmp = \tempnam(\sys_get_temp_dir(), 'mime');
    if ($tmp === false) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Failed creating temporary file.');
      // @codeCoverageIgnoreEnd
    }
    \file_put_contents($tmp, $data);
    try {
      return $this->mimeTypesGuesser->guessMimeType($tmp);
    } finally {
      @unlink($tmp);
    }
  }

  /**
   * @param scalar|null|\Stringable $value
   */
  private function formatAttr(string $name, mixed $value): ?string
  {
    $withName = static fn(mixed $value): string => \sprintf('%s="%s"', $name, escape((string) $value));

    switch (true) {
      case \str_starts_with($name, 'aria-'):
        // ARIA attribute
        return $withName(
          match ($value) {
            true => 'true',
            false => 'false',
            default => $value,
          },
        );

      case \str_contains($name, '-'):
        // Custom attribute
        return $withName($value);

      default:
        // Standard attribute
        if ($value === false || $value === null) {
          // Disabled
          return null;
        }
        if ($value === true) {
          // Name only
          return $name;
        }
        return $withName($value);
    }
  }

  private static function attrNameIsValid(string $name): bool
  {
    $pattern = '~[' . \preg_quote(implode('', self::INVALID_ATTR_NAME_CHARS), '~') . ']~';
    return \preg_match($pattern, $name) === 0;
  }

  /**
   * @return array{ open: string, close: string }
   */
  private static function parseHtmlTag(string $tag): array
  {
    if (\preg_match('~^<([\w-]+)(?:\s.+)?>$~', $tag, $matches)) {
      // HTML tag shorthand provided
      /** @var array{ string, string } $matches */
      $tagOpen = $tag;
      $tagClose = \sprintf('</%s>', $matches[1]);
    } else {
      // Assume tag name
      $tagName = escape($tag);
      $tagOpen = \sprintf('<%s>', $tagName);
      $tagClose = \sprintf('</%s>', $tagName);
    }

    return [
      'open' => $tagOpen,
      'close' => $tagClose,
    ];
  }
}
