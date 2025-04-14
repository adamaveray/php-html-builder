<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Components\Media;

interface HasMediaDensities extends HasMediaFormats
{
  /**
   * @param string $format One of the MIME types provided by `getImageFormats()`.
   * @return iterable<array{ density: int|float, url: string }> Densities and their corresponding URLs.
   */
  public function getDensitiesForFormat(string $format): iterable;
}
