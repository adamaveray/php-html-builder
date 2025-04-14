<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Html;

/**
 * @param string $value An unescaped value.
 * @return string The safe representation of `$value` for use in a HTML document.
 */
function escape(string $value): string
{
  return \htmlspecialchars($value, \ENT_HTML5 | \ENT_QUOTES, 'UTF-8');
}

/**
 * @param string $value A PHP value.
 * @return string The safe JavaScript representation of `$value` for use in an inline JavaScript script within a HTML document.
 */
function escapeJson(mixed $value, int $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE): string
{
  $json = \json_encode($value, \JSON_THROW_ON_ERROR | $flags);
  if ($json === false) {
    throw new \RuntimeException('Failed converting JSON: ' . \json_last_error_msg());
  }
  return escape($json);
}
