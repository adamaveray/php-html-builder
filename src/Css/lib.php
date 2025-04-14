<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Css;

/**
 * @param string $string An unescaped value.
 * @return string The safe representation of `$value` for use in a CSS statement.
 */
function escapeString(string $string): string
{
  return "'" . \addcslashes($string, '\\\'') . "'";
}
