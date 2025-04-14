<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Css;

use Averay\HtmlBuilder\Components\Media\HasMediaDensities;
use Averay\HtmlBuilder\Components\Media\HasMediaFormats;

final readonly class CssBuilder
{
  public function buildUrl(string $url): string
  {
    return 'url(' . escapeString($url) . ')';
  }

  /**
   * @param string|list<string> $values A value for the property. A list will output the property multiple times to provide fallback values.
   * @return string
   */
  public function buildProperty(string $name, string|array $values): string
  {
    if (!\is_array($values)) {
      $values = [$values];
    }

    $entries = [];
    foreach ($values as $value) {
      $entries[] = $name . ':' . $value;
    }
    return \implode(';', $entries);
  }

  /**
   * @param array<string, string | list<string>> $properties An associative array with keys representing CSS property names. List values will output the same property multiple times to provide fallback values.
   * @return string
   */
  public function buildProperties(array $properties): string
  {
    /** @var list<string> $entries */
    $entries = [];
    foreach ($properties as $property => $values) {
      $entries[] = $this->buildProperty($property, $values);
    }
    return \implode(';', $entries);
  }

  /**
   * @param iterable<string, string>|HasMediaFormats|HasMediaDensities $entries An iterable with URLs as keys, each mapped to a corresponding `srcset`-compatible size (e.g. a pixel amount or density), or a media interface-compatible object.
   */
  public function buildImageSet(iterable|HasMediaFormats|HasMediaDensities $entries, ?string $format = null): string
  {
    /** @var list<string> $formattedEntries */
    if ($entries instanceof HasMediaDensities) {
      $formattedEntries = self::buildImageSetDensityEntries($entries, $format);
    } elseif ($entries instanceof HasMediaFormats) {
      if ($format !== null) {
        throw new \BadMethodCallException(
          \sprintf('An image format cannot be specified with a %s instance.', HasMediaFormats::class),
        );
      }
      $formattedEntries = self::buildImageSetFormatsEntries($entries);
    } else {
      if ($format !== null) {
        throw new \BadMethodCallException('An image format cannot be specified with an entries iterable.');
      }
      /** @var list<string> $formattedEntries */
      $formattedEntries = [];
      foreach ($entries as $url => $size) {
        $formattedEntries[] = self::buildImageSetEntry($url, resolution: $size, format: null);
      }
    }
    if (empty($formattedEntries)) {
      throw new \InvalidArgumentException('At least one entry must be provided.');
    }
    return 'image-set(' . \implode(', ', $formattedEntries) . ')';
  }

  /**
   * @return list<string>
   */
  private static function buildImageSetDensityEntries(HasMediaDensities $media, ?string $selectedFormat): array
  {
    /**
     * @psalm-suppress InvalidArgument https://github.com/vimeo/psalm/issues/11007
     * @var array<array-key, string> $formats
     */
    $formats = $selectedFormat === null ? \iterator_to_array($media->getImageFormats()) : [$selectedFormat];

    $withFormats = \count($formats) > 1;
    /** @var list<string> $formattedEntries */
    $formattedEntries = [];
    foreach ($formats as $format) {
      foreach ($media->getDensitiesForFormat($format) as ['density' => $density, 'url' => $url]) {
        $formattedEntries[] = self::buildImageSetEntry(
          $url,
          resolution: ((string) $density) . 'x',
          format: $withFormats ? $format : null,
        );
      }
    }
    return $formattedEntries;
  }

  /**
   * @return list<string>
   */
  private static function buildImageSetFormatsEntries(HasMediaFormats $media): array
  {
    /** @var list<string> $formattedEntries */
    $formattedEntries = [];
    foreach ($media->getImageFormats() as $format) {
      $formattedEntries[] = self::buildImageSetEntry(
        $media->getUrlForFormat($format),
        resolution: null,
        format: $format,
      );
    }
    return $formattedEntries;
  }

  private static function buildImageSetEntry(string $url, ?string $resolution, ?string $format): string
  {
    $formattedParts = [\sprintf('url(%s)', escapeString($url))];
    if ($format !== null) {
      $formattedParts[] = \sprintf('type(%s)', escapeString($format));
    }
    if ($resolution !== null) {
      $formattedParts[] = $resolution;
    }
    return \implode(' ', $formattedParts);
  }
}
