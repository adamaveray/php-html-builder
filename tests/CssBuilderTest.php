<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests;

use Averay\HtmlBuilder\Components\Media\HasMediaDensities;
use Averay\HtmlBuilder\Components\Media\HasMediaFormats;
use Averay\HtmlBuilder\Css\CssBuilder;
use Averay\HtmlBuilder\Tests\Lib\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CssBuilder::class)]
final class CssBuilderTest extends TestCase
{
  private static function makeCssBuilder(): CssBuilder
  {
    return new CssBuilder();
  }

  #[DataProvider('buildUrlDataProvider')]
  public function testBuildUrl(string $expected, string $url): void
  {
    $cssBuilder = self::makeCssBuilder();
    self::assertEquals($expected, $cssBuilder->buildUrl($url), 'The URL statement should be built correctly.');
  }

  public static function buildUrlDataProvider(): iterable
  {
    yield 'No quotes' => [
      <<<'TXT'
      url('hello-world.jpg')
      TXT
      ,
      <<<'TXT'
      hello-world.jpg
      TXT
    ,
    ];

    yield 'With quotes' => [
      <<<'TXT'
      url('hello \'world\'.jpg')
      TXT
      ,
      <<<'TXT'
      hello 'world'.jpg
      TXT
    ,
    ];
  }

  #[DataProvider('buildPropertyDataProvider')]
  public function testBuildProperty(string $expected, string $name, string|array $values): void
  {
    self::assertEquals($expected, self::makeCssBuilder()->buildProperty($name, $values));
  }

  public static function buildPropertyDataProvider(): iterable
  {
    yield 'Single value' => [
      'expected' => 'hello:world',
      'name' => 'hello',
      'values' => 'world',
    ];

    yield 'Multiple fallback values' => [
      'expected' => 'height:100%;height:100vh',
      'name' => 'height',
      'values' => ['100%', '100vh'],
    ];
  }

  #[DataProvider('buildPropertiesDataProvider')]
  public function testBuildProperties(string $expected, array $properties): void
  {
    $cssBuilder = self::makeCssBuilder();
    self::assertEquals(
      $expected,
      $cssBuilder->buildProperties($properties),
      'The properties should be built correctly.',
    );
  }
  public static function buildPropertiesDataProvider(): iterable
  {
    yield 'Single property, single value' => ['hello:world', ['hello' => 'world']];

    yield 'Single property, multiple values' => ['height:100%;height:100vh', ['height' => ['100%', '100vh']]];

    yield 'Multiple properties, single values' => [
      'hello:world;foo:bar',
      [
        'hello' => 'world',
        'foo' => 'bar',
      ],
    ];

    yield 'Multiple properties, multiple values' => [
      'width:100%;width:100vw;height:100%;height:100vh',
      [
        'width' => ['100%', '100vw'],
        'height' => ['100%', '100vh'],
      ],
    ];

    yield 'Mixed' => [
      'hello:world;height:100%;height:100vh;foo:bar',
      [
        'hello' => 'world',
        'height' => ['100%', '100vh'],
        'foo' => 'bar',
      ],
    ];
  }

  #[DataProvider('buildImageSetDirectDataProvider')]
  public function testBuildImageSetDirect(string $expected, iterable $entries): void
  {
    $cssBuilder = self::makeCssBuilder();
    self::assertEqualsIgnoringWhitespace($expected, $cssBuilder->buildImageSet($entries));
  }

  public static function buildImageSetDirectDataProvider(): iterable
  {
    yield 'Values' => [
      'expected' => <<<'TXT'
      image-set(
        url('image@2x.jpg') 2x,
        url('image.jpg') 1x
      )
      TXT
      ,
      'entries' => [
        'image@2x.jpg' => '2x',
        'image.jpg' => '1x',
      ],
    ];
  }

  #[DataProvider('buildImageSetObjectDataProvider')]
  public function testBuildImageSetObject(string $expected, \Closure $objectBuilder, ?string $format = null): void
  {
    $cssBuilder = self::makeCssBuilder();
    $object = $objectBuilder->call($this);
    self::assertEqualsIgnoringWhitespace($expected, $cssBuilder->buildImageSet($object, $format));
  }

  public static function buildImageSetObjectDataProvider(): iterable
  {
    yield 'Formats' => [
      'expected' => <<<'TXT'
      image-set(
        url('image.jpg') type('image/jpeg'),
        url('image.webp') type('image/webp')
      )
      TXT
      ,
      'objectBuilder' => function (): HasMediaFormats {
        $mock = $this->createMock(HasMediaFormats::class);
        $mock
          ->expects($this->once())
          ->method('getImageFormats')
          ->willReturn(['image/jpeg', 'image/webp']);
        $this->expectMultipleInvocations($mock, 'getUrlForFormat', [
          ['parameters' => ['image/jpeg'], 'return' => 'image.jpg'],
          ['parameters' => ['image/webp'], 'return' => 'image.webp'],
        ]);
        return $mock;
      },
    ];

    yield 'Densities with all formats' => [
      'expected' => <<<'TXT'
      image-set(
        url('image.jpg') type('image/jpeg') 1x,
        url('image@2x.jpg') type('image/jpeg') 2x,
        url('image.webp') type('image/webp') 1x,
        url('image@2x.webp') type('image/webp') 2x
      )
      TXT
      ,
      'objectBuilder' => function (): HasMediaDensities {
        $mock = $this->createMock(HasMediaDensities::class);
        $mock
          ->expects($this->once())
          ->method('getImageFormats')
          ->willReturn(['image/jpeg', 'image/webp']);
        $this->expectMultipleInvocations($mock, 'getDensitiesForFormat', [
          [
            'parameters' => ['image/jpeg'],
            'return' => [['density' => 1, 'url' => 'image.jpg'], ['density' => 2, 'url' => 'image@2x.jpg']],
          ],
          [
            'parameters' => ['image/webp'],
            'return' => [['density' => 1, 'url' => 'image.webp'], ['density' => 2, 'url' => 'image@2x.webp']],
          ],
        ]);
        return $mock;
      },
    ];

    yield 'Densities with single format' => [
      'expected' => <<<'TXT'
      image-set(
        url('image.jpg') 1x,
        url('image@2x.jpg') 2x
      )
      TXT
      ,
      'objectBuilder' => function (): HasMediaDensities {
        $mock = $this->createMock(HasMediaDensities::class);
        $mock->expects($this->never())->method('getImageFormats');
        $mock
          ->expects($this->once())
          ->method('getDensitiesForFormat')
          ->with('image/jpeg')
          ->willReturn([['density' => 1, 'url' => 'image.jpg'], ['density' => 2, 'url' => 'image@2x.jpg']]);
        return $mock;
      },
      'format' => 'image/jpeg',
    ];
  }

  public function testBuildImageSetDisallowsEmptyEntriesArray(): void
  {
    $cssBuilder = self::makeCssBuilder();
    $this->expectException(\InvalidArgumentException::class);
    $cssBuilder->buildImageSet([]);
  }

  public function testBuildImageSetDisallowsFormatWithEntriesArray(): void
  {
    $cssBuilder = self::makeCssBuilder();
    $this->expectException(\BadMethodCallException::class);
    $cssBuilder->buildImageSet(['image.jpg' => '1x'], 'test');
  }

  public function testBuildImageSetDisallowsFormatWithMediaFormats(): void
  {
    $cssBuilder = self::makeCssBuilder();

    $mock = $this->createMock(HasMediaFormats::class);
    $mock->expects($this->never())->method('getImageFormats');
    $mock->expects($this->never())->method('getUrlForFormat');

    $this->expectException(\BadMethodCallException::class);
    $cssBuilder->buildImageSet($mock, 'test');
  }
}
