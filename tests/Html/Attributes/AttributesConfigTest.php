<?php

declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests\Html\Attributes;

use Averay\HtmlBuilder\Html\Attributes\AttributesConfig;
use Averay\HtmlBuilder\Tests\Lib\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(AttributesConfig::class)]
final class AttributesConfigTest extends TestCase
{
  #[Test]
  #[DoesNotPerformAssertions] // Ensures a default instance can be created successfully
  public function createDefault(): void
  {
    AttributesConfig::createDefault();
  }
}
