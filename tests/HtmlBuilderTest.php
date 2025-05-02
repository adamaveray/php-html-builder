<?php
declare(strict_types=1);

namespace Averay\HtmlBuilder\Tests;

use Averay\HtmlBuilder\Html\HtmlBuilder;
use Averay\HtmlBuilder\Tests\Lib\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;

#[CoversClass(HtmlBuilder::class)]
#[CoversFunction('Averay\HtmlBuilder\Html\escape')]
final class HtmlBuilderTest extends TestCase
{
  private static function makeHtmlBuilder(?MimeTypeGuesserInterface $mimeTypes = null): HtmlBuilder
  {
    $mimeTypes ??= new MimeTypes();
    return new HtmlBuilder($mimeTypes);
  }

  #[DataProvider('buildAttrsDataProvider')]
  public function testBuildAttrs(string $expected, array ...$attrs): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->buildAttrs(...$attrs),
      'The attributes string should be built correctly.',
    );
  }

  public static function buildAttrsDataProvider(): iterable
  {
    yield 'Single' => [
      'hello="world"',
      [
        'hello' => 'world',
      ],
    ];

    yield 'Multiple values' => [
      'hello="world" foo="bar"',
      [
        'hello' => 'world',
        'foo' => 'bar',
      ],
    ];

    yield 'Multiple sets' => [
      'hello="overwritten" foo="bar"',
      [
        'hello' => 'world',
        'foo' => 'bar',
      ],
      [
        'hello' => 'overwritten',
      ],
    ];

    yield 'Standard booleans' => [
      'hidden',
      [
        'hidden' => true,
        'disabled' => false,
        'checked' => null,
      ],
    ];

    yield 'ARIA booleans' => [
      'aria-hidden="true" aria-disabled="false"',
      [
        'aria-hidden' => true,
        'aria-disabled' => false,
      ],
    ];

    yield 'Other data types' => [
      'data-int="1" data-float="1.234" data-bool="1" data-stringable="value"',
      [
        'data-int' => 1,
        'data-float' => 1.234,
        'data-bool' => true,
        'data-stringable' => new class implements \Stringable {
          public function __toString(): string
          {
            return 'value';
          }
        },
      ],
    ];

    yield 'Non-associative attributes' => ['disabled inert', ['disabled', 'inert']];
  }

  #[DataProvider('buildClassesDataProvider')]
  public function testBuildClasses(string $expected, array $classes): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals($expected, $htmlBuilder->buildClasses($classes), 'The classes list should be build correctly.');
  }

  public static function buildClassesDataProvider(): iterable
  {
    yield 'Single' => [
      'expected' => 'hello-world',
      'classes' => ['hello-world' => true],
    ];
    yield 'Multiple' => [
      'expected' => 'hello-world other-class',
      'classes' => ['hello-world' => true, 'other-class' => true],
    ];
    yield 'Disabled' => [
      'expected' => 'hello-world',
      'classes' => ['hello-world' => true, 'other-class' => false],
    ];
  }

  #[DataProvider('addClassesDataProvider')]
  public function testAddClasses(string $expected, string $classList, string ...$additionalClasses): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->addClasses($classList, ...$additionalClasses),
      'The class list should be updated correctly.',
    );
  }

  public static function addClassesDataProvider(): iterable
  {
    yield 'Empty' => [
      'expected' => '',
      'classList' => '',
    ];

    yield 'No additions' => [
      'expected' => 'example-class',
      'classList' => 'example-class',
    ];

    yield 'Only additions' => ['additional-class', '', 'additional-class'];

    yield 'Combined' => ['example-class additional-class', 'example-class', 'additional-class'];
  }

  #[DataProvider('buildStylesheetDataProvider')]
  public function testBuildStylesheet(
    string $expected,
    string $url,
    ?string $media = null,
    ?string $integrity = null,
    ?string $crossorigin = null,
  ): void {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->buildStylesheet($url, $media, $integrity, $crossorigin),
      'The stylesheet HTML should be built correctly.',
    );
  }

  public static function buildStylesheetDataProvider(): iterable
  {
    yield 'Basic' => [
      'expected' => <<<'HTML'
      <link rel="stylesheet" href="stylesheet.css"/>
      HTML
      ,
      'url' => 'stylesheet.css',
    ];

    yield 'With attributes' => [
      'expected' => <<<'HTML'
      <link rel="stylesheet" href="stylesheet.css" media="example-media" integrity="example-hash" crossorigin="example-crossorigin"/>
      HTML
      ,
      'url' => 'stylesheet.css',
      'media' => 'example-media',
      'integrity' => 'example-hash',
      'crossorigin' => 'example-crossorigin',
    ];

    yield 'Safe values' => [
      'expected' => <<<'HTML'
      <link rel="stylesheet" href="&lt;stylesheet.css&gt;" media="&lt;example-media&gt;" integrity="&lt;example-hash&gt;" crossorigin="&lt;example-crossorigin&gt;"/>
      HTML
      ,
      'url' => '<stylesheet.css>',
      'media' => '<example-media>',
      'integrity' => '<example-hash>',
      'crossorigin' => '<example-crossorigin>',
    ];
  }

  #[DataProvider('buildScriptDataProvider')]
  public function testBuildScript(
    string $expected,
    string $url,
    ?string $type = null,
    bool $async = false,
    ?string $integrity = null,
    ?string $crossorigin = null,
  ): void {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->buildScript($url, $type, $async, $integrity, $crossorigin),
      'The script HTML should be built correctly.',
    );
  }

  public static function buildScriptDataProvider(): iterable
  {
    yield 'Basic' => [
      'expected' => <<<'HTML'
      <script src="script.js"></script>
      HTML
      ,
      'url' => 'script.js',
    ];

    yield 'With attributes' => [
      'expected' => <<<'HTML'
      <script src="script.js" type="module" async integrity="example-hash" crossorigin="example-crossorigin"></script>
      HTML
      ,
      'url' => 'script.js',
      'type' => 'module',
      'async' => true,
      'integrity' => 'example-hash',
      'crossorigin' => 'example-crossorigin',
    ];

    yield 'Safe values' => [
      'expected' => <<<'HTML'
      <script src="&lt;script.js&gt;" type="&lt;module&gt;" async integrity="&lt;example-hash&gt;" crossorigin="&lt;example-crossorigin&gt;"></script>
      HTML
      ,
      'url' => '<script.js>',
      'type' => '<module>',
      'async' => true,
      'integrity' => '<example-hash>',
      'crossorigin' => '<example-crossorigin>',
    ];
  }

  #[DataProvider('buildPreloadLinksDataProvider')]
  public function testBuildPreloadLinks(string $expected, array $preloads, array $preconnectHosts = []): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->buildPreloadLinks($preloads, $preconnectHosts),
      'The preload HTML should be built correctly.',
    );
  }

  public static function buildPreloadLinksDataProvider(): iterable
  {
    yield 'Empty' => [
      'expected' => '',
      'preloads' => [],
    ];

    yield 'Preloads only' => [
      'expected' => \implode('', [
        '<link rel="preload" href="script.js" as="script"/>',
        '<link rel="preload" href="style.css" as="style"/>',
      ]),
      'preloads' => [
        'script' => 'script.js',
        'style' => ['style.css'],
      ],
    ];

    yield 'Preconnects only' => [
      'expected' => \implode('', [
        '<link rel="preconnect" href="example.com"/>',
        '<link rel="preconnect" href="example.org"/>',
      ]),
      'preloads' => [],
      'preconnectHosts' => ['example.com', 'example.org'],
    ];

    yield 'Both' => [
      'expected' => \implode('', [
        '<link rel="preload" href="script.js" as="script"/>',
        '<link rel="preload" href="style.css" as="style"/>',
        '<link rel="preconnect" href="example.com"/>',
        '<link rel="preconnect" href="example.org"/>',
      ]),
      'preloads' => [
        'script' => 'script.js',
        'style' => ['style.css'],
      ],
      'preconnectHosts' => ['example.com', 'example.org'],
    ];

    yield 'With attributes' => [
      'expected' => \implode('', [
        '<link rel="preload" href="script.js" as="script" integrity="example-hash-1" crossorigin="example-crossorigin-1"/>',
        '<link rel="preload" href="style.css" as="style" integrity="example-hash-2" crossorigin="example-crossorigin-2"/>',
        '<link rel="preconnect" href="example.com"/>',
        '<link rel="preconnect" href="example.org"/>',
      ]),
      'preloads' => [
        'script' => [
          [
            'url' => 'script.js',
            'integrity' => 'example-hash-1',
            'crossorigin' => 'example-crossorigin-1',
          ],
        ],
        'style' => [
          [
            'url' => 'style.css',
            'integrity' => 'example-hash-2',
            'crossorigin' => 'example-crossorigin-2',
          ],
        ],
      ],
      'preconnectHosts' => ['example.com', 'example.org'],
    ];

    yield 'Safe values' => [
      'expected' => \implode('', [
        '<link rel="preload" href="&lt;script.js&gt;" as="script"/>',
        '<link rel="preload" href="&lt;style.css&gt;" as="style" integrity="&lt;example-hash&gt;" crossorigin="&lt;example-crossorigin&gt;"/>',
        '<link rel="preconnect" href="&lt;example.com&gt;"/>',
        '<link rel="preconnect" href="&lt;example.org&gt;"/>',
      ]),
      'preloads' => [
        'script' => '<script.js>',
        'style' => [
          [
            'url' => '<style.css>',
            'integrity' => '<example-hash>',
            'crossorigin' => '<example-crossorigin>',
          ],
        ],
      ],
      'preconnectHosts' => ['<example.com>', '<example.org>'],
    ];
  }

  public function testBuildPreloadLinksWithInvalidType(): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    $this->expectException(\OutOfBoundsException::class);
    $htmlBuilder->buildPreloadLinks(['unknown' => []]);
  }

  #[DataProvider('buildSrcSetDataProvider')]
  public function testBuildSrcSet(string $expected, array $entries): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals($expected, $htmlBuilder->buildSrcSet($entries), 'The source set should be built correctly.');
  }

  public static function buildSrcSetDataProvider(): iterable
  {
    yield 'Empty' => [
      'expected' => '',
      'entries' => [],
    ];

    yield 'Single' => [
      'expected' => 'image.jpg 1x',
      'entries' => ['image.jpg' => '1x'],
    ];

    yield 'Densities' => [
      'expected' => 'image@3x.jpg 3x, image@2x.jpg 2x, image.jpg 1x',
      'entries' => [
        'image@3x.jpg' => '3x',
        'image@2x.jpg' => '2x',
        'image.jpg' => '1x',
      ],
    ];

    yield 'Widths' => [
      'expected' => 'large.jpg 500px, small.jpg 200px',
      'entries' => [
        'large.jpg' => '500px',
        'small.jpg' => '200px',
      ],
    ];

    yield 'Safe values' => [
      'expected' => '&lt;large.jpg&gt; &lt;500px&gt;, &lt;small.jpg&gt; &lt;200px&gt;',
      'entries' => [
        '<large.jpg>' => '<500px>',
        '<small.jpg>' => '<200px>',
      ],
    ];
  }

  #[DataProvider('wrapParagraphsDataProvider')]
  public function testWrapParagraphs(string $expected, string $string, string $wrappingTag): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->wrapParagraphs($string, $wrappingTag),
      'The paragraphs should be wrapped correctly.',
    );
  }

  public static function wrapParagraphsDataProvider(): iterable
  {
    yield 'Single paragraph' => [
      'expected' => '<p>Hello world.</p>',
      'string' => 'Hello world.',
      'wrappingTag' => '<p>',
    ];

    yield 'Multiple paragraphs' => [
      'expected' => \implode('', ['<p>Hello world.</p>', '<p>Another paragraph.</p>']),
      'string' => <<<'TXT'
      Hello world.

      Another paragraph.
      TXT
      ,
      'wrappingTag' => '<p>',
    ];

    yield 'Custom attributes' => [
      'expected' => '<p class="test">Hello world.</p>',
      'string' => 'Hello world.',
      'wrappingTag' => '<p class="test">',
    ];

    $spaces = \str_repeat(' ', 4);
    yield 'Trimmed whitespace' => [
      'expected' => \implode('', ['<p>Hello world.</p>', '<p>Another paragraph.</p>']),
      'string' => <<<TXT


      Hello world.{$spaces}



           Another paragraph.{$spaces}



      TXT
      ,
      'wrappingTag' => '<p>',
    ];

    yield 'Safe values' => [
      'expected' => '<p class="test">&lt;Hello world.&gt;</p>',
      'string' => '<Hello world.>',
      'wrappingTag' => '<p class="test">',
    ];
  }

  #[DataProvider('wrapWordsDataProvider')]
  public function testWrapWords(string $expected, string $string, string $wrappingTag): void
  {
    $htmlBuilder = self::makeHtmlBuilder();
    self::assertEquals(
      $expected,
      $htmlBuilder->wrapWords($string, $wrappingTag),
      'The words should be wrapped correctly.',
    );
  }

  public static function wrapWordsDataProvider(): iterable
  {
    yield 'Empty' => [
      'expected' => '',
      'string' => '',
      'wrappingTag' => '<span>',
    ];

    yield 'Words' => [
      'expected' => <<<'HTML'
      <span>Hello</span> <span>world.</span> <span>This</span> <span>is</span> <span>a</span> <span>test.</span>
      HTML
      ,
      'string' => 'Hello world. This is a test.',
      'wrappingTag' => '<span>',
    ];

    yield 'Custom attributes' => [
      'expected' => <<<'HTML'
      <span class="test">Hello</span> <span class="test">world.</span>
      HTML
      ,
      'string' => 'Hello world.',
      'wrappingTag' => '<span class="test">',
    ];

    yield 'Preserved whitespace' => [
      'expected' => <<<'HTML'


      <span>Hello</span>     <span>world.</span>


      HTML
      ,
      'string' => <<<'TXT'


      Hello     world.


      TXT
      ,
      'wrappingTag' => '<span>',
    ];

    yield 'Safe values' => [
      'expected' => <<<'HTML'
      <span>&lt;Hello</span> <span>world.&gt;</span>
      HTML
      ,
      'string' => '<Hello world.>',
      'wrappingTag' => '<span>',
    ];
  }

  #[DataProvider('generateDataUriDataProvider')]
  public function testGenerateDataUri(
    string $expected,
    string $data,
    ?string $mime = null,
    array $parameters = [],
    ?string $inferredMime = null,
  ): void {
    $mimeTypes = null;
    if ($inferredMime !== null) {
      $mimeTypes = $this->createMock(MimeTypeGuesserInterface::class);
      $mimeTypes
        ->expects($this->once())
        ->method('guessMimeType')
        ->willReturnCallback(static function (string $path) use ($data, $inferredMime): string {
          self::assertStringEqualsFile(
            $path,
            $data,
            'A temporary file with the provided data should be used for inference.',
          );
          return $inferredMime;
        });
    }

    $htmlBuilder = self::makeHtmlBuilder($mimeTypes);
    self::assertEquals(
      $expected,
      $htmlBuilder->generateDataUri($data, $mime, $parameters),
      'The data URI should be built correctly.',
    );
  }

  public static function generateDataUriDataProvider(): iterable
  {
    yield 'Preset text' => [
      'expected' => 'data:text/plain,Hello%20world.',
      'data' => 'Hello world.',
      'mime' => 'text/plain',
    ];

    yield 'Preset binary' => [
      'expected' => 'data:unknown/test;base64,' . base64_encode('Hello world.'),
      'data' => 'Hello world.',
      'mime' => 'unknown/test',
    ];

    yield 'Inferred type' => [
      'expected' => 'data:foo/bar;base64,' . base64_encode('Hello world.'),
      'data' => 'Hello world.',
      'inferredMime' => 'foo/bar',
    ];

    yield 'Custom parameters' => [
      'expected' => 'data:unknown/test;foo=bar;base64,' . base64_encode('Hello world.'),
      'data' => 'Hello world.',
      'mime' => 'unknown/test',
      'parameters' => ['foo' => 'bar'],
    ];
  }

  #[DataProvider('mapHtmlIdsDataProvider')]
  public static function testMapHtmlIds(
    string $expected,
    string $html,
    callable $transformer,
    array $additionalAttributes = [],
  ): void {
    $htmlBuilder = self::makeHtmlBuilder();
    $result = $htmlBuilder->mapHtmlIds($html, $transformer, $additionalAttributes);
    self::assertEquals($expected, $result, 'The HTML should be processed correctly.');
  }

  public static function mapHtmlIdsDataProvider(): iterable
  {
    yield 'No IDs' => [
      'expected' => <<<'HTML'
      <p>Hello world.</p>
      HTML
      ,
      'html' => <<<'HTML'
      <p>Hello world.</p>
      HTML
      ,
      'transformer' => static fn(string $value): never => self::fail('The transformer should not be called.'),
    ];

    yield 'No transformation' => [
      'expected' => <<<'HTML'
      <p id="test">Hello world.</p>
      HTML
      ,
      'html' => <<<'HTML'
      <p id="test">Hello world.</p>
      HTML
      ,
      'transformer' => static fn(string $value): string => $value,
    ];

    yield 'Transformation' => [
      'expected' => <<<'HTML'
      <p id="new-test-value">Hello world.</p>
      HTML
      ,
      'html' => <<<'HTML'
      <p id="test">Hello world.</p>
      HTML
      ,
      'transformer' => static fn(string $value): string => 'new-' . $value . '-value',
    ];

    yield 'Multiple attributes' => [
      'expected' => <<<'HTML'
      <div aria-labelledby="new-test-title-value" class="test-class">
        <h1 id="new-test-title-value" hidden>Test title</h1>
        <figure inert onclick="console.log('click');" aria-describedby="new-test-caption-value">
          Test figure.
        </figure>
        <figcaption id="new-test-caption-value">Test caption.</figcaption>
        <button data-target="test-title">Test button.</button>
      </div>
      HTML
      ,
      'html' => <<<'HTML'
      <div aria-labelledby="test-title" class="test-class">
        <h1 id="test-title" hidden>Test title</h1>
        <figure inert onclick="console.log('click');" aria-describedby="test-caption">
          Test figure.
        </figure>
        <figcaption id="test-caption">Test caption.</figcaption>
        <button data-target="test-title">Test button.</button>
      </div>
      HTML
      ,
      'transformer' => static fn(string $value): string => 'new-' . $value . '-value',
    ];

    yield 'Custom attributes' => [
      'expected' => <<<'HTML'
      <div aria-labelledby="new-test-title-value">
        <h1 id="new-test-title-value">Test title</h1>
        <figure aria-describedby="new-test-caption-value">
          Test figure.
        </figure>
        <figcaption id="new-test-caption-value">Test caption.</figcaption>
        <button data-target="new-test-title-value">Test button.</button>
      </div>
      HTML
      ,
      'html' => <<<'HTML'
      <div aria-labelledby="test-title">
        <h1 id="test-title">Test title</h1>
        <figure aria-describedby="test-caption">
          Test figure.
        </figure>
        <figcaption id="test-caption">Test caption.</figcaption>
        <button data-target="test-title">Test button.</button>
      </div>
      HTML
      ,
      'transformer' => static fn(string $value): string => 'new-' . $value . '-value',
      'additionalAttributes' => ['data-target'],
    ];
  }
}
