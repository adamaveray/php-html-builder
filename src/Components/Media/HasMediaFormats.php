<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Components\Media;

interface HasMediaFormats
{
  /**
   * @return iterable<string> MIME types for each format the resource is available in.
   */
  public function getImageFormats(): iterable;

  /**
   * @param string $format One of the MIME types provided by `getImageFormats()`.
   * @return string The URL for the given format.
   */
  public function getUrlForFormat(string $format): string;
}
