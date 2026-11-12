<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\DocsFacade\DocsTemplateRenderer;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Guards documentation exports against platform-dependent line-ending changes.
 */
class DocsTemplateRendererLineEndingsTest extends TestCase
{
    /**
     * Generated content and print wrappers use the original block's line endings.
     *
     * @return void
     */
    public function testRenderingPreservesLineEndingsAndIsIdempotent(): void
    {
        $renderer = new DocsTemplateRenderer(new Workbench([
            'SERVER.INSTALLATION_NAME' => 'docs-line-endings-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]));
        $renderer->addPlaceholder(new class implements PlaceholderResolverInterface {
            /**
             * {@inheritDoc}
             * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
             */
            public function resolve(array $placeholders): array
            {
                return [0 => "- First\n- Second\r\n- Third\r- Fourth"];
            }
        });

        $file = tempnam(sys_get_temp_dir(), 'exface-docs-');
        self::assertNotFalse($file);
        try {
            foreach (["\n", "\r\n", "\r"] as $lineEnding) {
                foreach (['', ':noprint=true'] as $option) {
                    $startTag = '<!-- BEGIN List' . $option . ' -->';
                    $endTag = '<!-- END List -->';
                    $before = '# Example' . $lineEnding . $lineEnding;
                    $after = $lineEnding . 'Unchanged text' . $lineEnding;
                    $source = $before . $startTag . $lineEnding . 'Old content' . $lineEnding . $endTag . $after;
                    $content = implode($lineEnding, ['- First', '- Second', '- Third', '- Fourth']);
                    if ($option !== '') {
                        $content = '<!-- noprint:start -->' . $lineEnding . $content
                            . $lineEnding . '<!-- noprint:end -->';
                    }
                    $expected = $before . $startTag . $lineEnding . $content . $lineEnding . $endTag . $after;

                    file_put_contents($file, $source);
                    $rendered = $renderer->render($file);
                    self::assertSame($expected, $rendered);
                    file_put_contents($file, $rendered);
                    self::assertSame($expected, $renderer->render($file));
                }
            }
        } finally {
            unlink($file);
        }
    }

    /**
     * Local block endings do not cause unrelated mixed-ending text to be normalized.
     *
     * @return void
     */
    public function testReplacementLeavesSurroundingLineEndingsUntouched(): void
    {
        $renderer = (new \ReflectionClass(DocsTemplateRenderer::class))->newInstanceWithoutConstructor();
        $startTag = '<!-- BEGIN List -->';
        $endTag = '<!-- END List -->';
        $before = "# Heading\nParagraph\r\n";
        $after = "\r\nFooter\n";
        $source = $before . $startTag . "\r\nOld\r\n" . $endTag . $after;

        self::assertSame(
            $before . $startTag . "\r\nFirst\r\nSecond\r\n" . $endTag . $after,
            $renderer->replaceAtOffset($source, $startTag, $endTag, "First\nSecond", 0, 'List', '')
        );
    }

    /**
     * Inline image references stay inline, including documents with no line endings.
     *
     * @return void
     */
    public function testImageReferencesStayInline(): void
    {
        $renderer = (new \ReflectionClass(DocsTemplateRenderer::class))->newInstanceWithoutConstructor();
        $startTag = '<!-- BEGIN ImageRef -->';
        $endTag = '<!-- END ImageRef -->';
        $source = 'See ' . $startTag . 'old' . $endTag . '.';

        self::assertSame(
            'See ' . $startTag . 'Figure 1' . $endTag . '.',
            $renderer->replaceAtOffset($source, $startTag, $endTag, 'Figure 1', 0, 'ImageRef', '')
        );
    }
}
