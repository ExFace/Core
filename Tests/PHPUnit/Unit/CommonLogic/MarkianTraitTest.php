<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use PHPUnit\Framework\TestCase;

/**
 * Protects source serialization independently of the metamodel and browser lifecycle.
 */
class MarkianTraitTest extends TestCase
{
    /**
     * Keeps document text inside inline scripts without changing the decoded source.
     *
     * @return void
     */
    public function testInlineScriptEncodingRetainsExactMarkdown() : void
    {
        $element = new class {
            use MarkianTrait {
                escapeMarkdownForInlineJavaScript as public encode;
            }
        };
        $markdown = "# Notes\n\n</ScRiPt><script>alert('unsafe')</script>\n\n<!-- BEGIN payload -->\n";
        $encoded = $element->encode($markdown);
        $this->assertStringNotContainsString('<', $encoded);
        $this->assertSame($markdown, json_decode($encoded, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('""', $element->encode(null));
    }

    /**
     * Keeps teardown executable inside easyUI's double-quoted data-options attribute.
     *
     * @return void
     */
    public function testDialogDestroySnippetContainsNoAttributeDelimiters() : void
    {
        $element = new class {
            use MarkianTrait {
                buildJsMarkdownRemove as public destroySnippet;
            }

            /**
             * Includes both quote types to exercise safe mount serialization.
             *
             * @return string
             */
            public function getId() : string
            {
                return "markdown_'\"fixture";
            }
        };
        $snippet = $element->destroySnippet();
        $this->assertStringNotContainsString('"', $snippet);
        $this->assertStringContainsString('\\u0027\\u0022', $snippet);
        $this->assertStringContainsString('.destroy()', $snippet);
    }

    /**
     * Reads and replaces Markdown through the owned handle rather than rendered HTML.
     *
     * @return void
     */
    public function testValueSnippetsUseTheMountOwnedBridge() : void
    {
        $element = new class {
            use MarkianTrait;

            /**
             * Supplies a stable mount for this pure code-generation fixture.
             *
             * @return string
             */
            public function getId() : string
            {
                return 'markdown_fixture';
            }
        };
        $getter = $element->buildJsValueGetter();
        $setter = $element->buildJsValueSetter('nextValue');
        $this->assertStringContainsString('._exfMarkian', $getter);
        $this->assertStringContainsString('.get()', $getter);
        $this->assertStringNotContainsString('.html(', $getter);
        $this->assertStringContainsString("String(value == null ? '' : value)", $setter);
        $this->assertStringContainsString('nextValue', $setter);
        $this->assertStringNotContainsString('toastui', $setter);
    }
}