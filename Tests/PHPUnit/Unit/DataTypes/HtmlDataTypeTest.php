<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\DataTypes\HtmlDataType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the HTML indentation of HtmlDataType::prettify().
 *
 * prettify() is used by the HttpMessageDebugger to display HTML response bodies. Its docblock
 * promises that the HTML is not modified - only whitespace/indentation may change. These tests
 * make sure that promise holds for well-formed, broken and non-HTML input alike.
 *
 * The characterization cases were recorded from the previous implementation (gajus/dindent),
 * which was replaced by the in-house HtmlIndenter. Outputs that intentionally differ from
 * dindent are covered by dedicated, documented test methods below.
 */
class HtmlDataTypeTest extends TestCase
{
    /**
     * Provides typical HTML inputs and the exact output produced by gajus/dindent.
     *
     * Exists to prove that replacing dindent does not change the debugger output for common
     * HTTP response bodies.
     *
     * @return array<string, array{string, string}>
     */
    public static function characterizationProvider(): array
    {
        return [
            'nested divs' => [
                '<div class="outer"><div class="inner"><div>Deep</div></div></div>',
                "<div class=\"outer\">\n    <div class=\"inner\">\n        <div>Deep</div>\n    </div>\n</div>"
            ],
            'table' => [
                '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>',
                "<table>\n    <thead>\n        <tr>\n            <th>A</th>\n            <th>B</th>\n        </tr>\n    </thead>\n    <tbody>\n        <tr>\n            <td>1</td>\n            <td>2</td>\n        </tr>\n    </tbody>\n</table>"
            ],
            'list' => [
                '<ul><li>One</li><li>Two</li><li>Three</li></ul>',
                "<ul>\n    <li>One</li>\n    <li>Two</li>\n    <li>Three</li>\n</ul>"
            ],
            'inline elements in paragraph' => [
                '<p>Hello <b>bold</b> and <a href="x.html">link</a> text</p>',
                '<p>Hello <b>bold</b> and <a href="x.html">link</a> text</p>'
            ],
            'inline elements in nested blocks' => [
                '<div><p>Text with <strong>strong</strong>, <em>em</em> and <code>code</code>.</p><span class="x">s</span></div>',
                "<div>\n    <p>Text with <strong>strong</strong>, <em>em</em> and <code>code</code>.</p>\n    <span class=\"x\">s</span>\n</div>"
            ],
            'full document without headings' => [
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test</title><link rel="stylesheet" href="a.css"></head><body><div>Title</div><p>Text</p></body></html>',
                "<!DOCTYPE html>\n<html>\n    <head>\n        <meta charset=\"utf-8\">\n        <title>Test</title>\n        <link rel=\"stylesheet\" href=\"a.css\">\n    </head>\n    <body>\n        <div>Title</div>\n        <p>Text</p>\n    </body>\n</html>"
            ],
            'script and style' => [
                '<html><head><style>body { color: red; }</style><script>var a = 1; if (a < 2) { console.log("x"); }</script></head><body><div>Hi</div></body></html>',
                "<html>\n    <head>\n        <style>body { color: red; }</style>\n        <script>var a = 1; if (a < 2) { console.log(\"x\"); }</script>\n    </head>\n    <body>\n        <div>Hi</div>\n    </body>\n</html>"
            ],
            'already indented input' => [
                "<div>\n    <p>Text</p>\n</div>",
                "<div>\n    <p>Text</p>\n</div>"
            ],
        ];
    }

    /**
     * Pins the output of typical inputs to what gajus/dindent produced.
     *
     * @param string $html
     * @param string $expected
     * @return void
     */
    #[DataProvider('characterizationProvider')]
    public function testPrettifyMatchesPreviousImplementation(string $html, string $expected): void
    {
        $result = HtmlDataType::prettify($html);
        $this->assertSame($expected, $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Intentional change: headings (h1-h6) with plain text stay on one line.
     *
     * dindent only recognized tag names matching [a-z]+, so `<h1>Title</h1>` was split into three
     * lines and the heading text was indented. The text is now kept unchanged inside its tag.
     *
     * @return void
     */
    public function testFullDocumentKeepsHeadingOnOneLine(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test</title><link rel="stylesheet" href="a.css"></head><body><h1>Title</h1><p>Text</p></body></html>';
        $expected = "<!DOCTYPE html>\n<html>\n    <head>\n        <meta charset=\"utf-8\">\n        <title>Test</title>\n        <link rel=\"stylesheet\" href=\"a.css\">\n    </head>\n    <body>\n        <h1>Title</h1>\n        <p>Text</p>\n    </body>\n</html>";
        $result = HtmlDataType::prettify($html);
        $this->assertSame($expected, $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Ensures that a tag whose name only starts with the name of a raw text element
     * (e.g. </prefix> inside <pre>) does not end the raw text element early.
     */
    public function testRawElementIsNotClosedByTagWithLongerName() : void
    {
        $html = '<div><pre>a </prefix>  b</pre><p>x</p></div>';
        $this->assertStringContainsString('<pre>a </prefix>  b</pre>', HtmlDataType::prettify($html));
    }

    /**
     * Intentional change: inline void elements (br, img) stay on the same line as the surrounding text.
     *
     * dindent listed br and img as inline elements, but its inline detection required a closing tag,
     * so they were put on separate lines, splitting the text around them.
     *
     * @return void
     */
    public function testInlineVoidElementsStayInText(): void
    {
        $html = '<div>Line<br>Next<img src="a.png"></div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame('<div>Line<br>Next<img src="a.png"></div>', $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Intentional change: whitespace inside <pre> is preserved.
     *
     * dindent collapsed all whitespace sequences to a single space, including those inside <pre>,
     * which destroyed preformatted content.
     *
     * @return void
     */
    public function testPreContentIsPreserved(): void
    {
        $pre = "<pre>line 1\n    indented  line\n\n\tend</pre>";
        $html = '<div>' . $pre . '</div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame("<div>\n    " . $pre . "\n</div>", $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Content of raw text elements must be byte-identical to the input, because whitespace is
     * significant in scripts, styles, preformatted text and textarea values.
     *
     * @return void
     */
    public function testRawTextElementsAreByteIdentical(): void
    {
        $script = "<script type=\"text/javascript\">\n  if (a <b && c > d) {\n\t\tx = '</div>';\n  }\n</script>";
        $style = "<style>\n   .a > .b {  color: red; }\n</style>";
        $pre = "<pre class=\"x\">  keep\n    <b>this</b>\n</pre>";
        $textarea = "<textarea name=\"t\">  first\n\n  <div>not a tag</div>  </textarea>";
        $html = "<html><head>{$script}{$style}</head><body><div>{$pre}{$textarea}</div></body></html>";
        $result = HtmlDataType::prettify($html);
        $this->assertStringContainsString($script, $result);
        $this->assertStringContainsString($style, $result);
        $this->assertStringContainsString($pre, $result);
        $this->assertStringContainsString($textarea, $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Broken markup must be indented as well as possible, but never repaired: no tags may be added or removed.
     *
     * @return void
     */
    public function testBrokenMarkupIsNotRepaired(): void
    {
        $html = '<div><p>unclosed<div>next</div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame("<div>\n    <p>\n        unclosed\n        <div>next</div>", $result);
        $this->assertSame(substr_count($html, '<'), substr_count($result, '<'));
        $this->assertSame(substr_count($html, '>'), substr_count($result, '>'));
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Surplus closing tags must not lead to negative indentation or removed tags.
     *
     * @return void
     */
    public function testSurplusClosingTagsAreKept(): void
    {
        $html = '</div></span><div>a</div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame("</div>\n</span>\n<div>a</div>", $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Non-HTML bodies (e.g. JSON with an HTML content type) must not be wrapped in any tag.
     *
     * @return void
     */
    public function testNonHtmlIsNotWrapped(): void
    {
        $body = '{"error":"<b>not html</b>"}';
        $this->assertSame($body, HtmlDataType::prettify($body));
        $this->assertSame('plain text', HtmlDataType::prettify('plain text'));
        $this->assertSame('a < b > c', HtmlDataType::prettify('a < b > c'));
    }

    /**
     * UTF-8 characters must stay unchanged and must not be converted to HTML entities.
     *
     * @return void
     */
    public function testUtf8IsNotConvertedToEntities(): void
    {
        $html = '<div><p>Wörld</p><p>Çağrı</p><span>😀 🚀</span></div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame("<div>\n    <p>Wörld</p>\n    <p>Çağrı</p>\n    <span>😀 🚀</span>\n</div>", $result);
        $this->assertStringNotContainsString('&', $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Invalid UTF-8 byte sequences must not make prettify() fail or alter the bytes.
     *
     * @return void
     */
    public function testInvalidUtf8DoesNotFail(): void
    {
        $html = "<div><p>bad \xC3\x28 bytes \xFF</p></div>";
        $result = HtmlDataType::prettify($html);
        $this->assertSame("<div>\n    <p>bad \xC3\x28 bytes \xFF</p>\n</div>", $result);
    }

    /**
     * HTML5 and custom elements are indented like any other block element.
     *
     * @return void
     */
    public function testHtml5AndCustomElements(): void
    {
        $html = '<section><nav><a href="#">Home</a></nav><template><my-widget data-x="1"><p>Inner</p></my-widget></template></section>';
        $expected = "<section>\n    <nav><a href=\"#\">Home</a></nav>\n    <template>\n        <my-widget data-x=\"1\">\n            <p>Inner</p>\n        </my-widget>\n    </template>\n</section>";
        $result = HtmlDataType::prettify($html);
        $this->assertSame($expected, $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Void elements, self-closing tags, comments, doctype and CDATA must not change the indentation depth.
     *
     * @return void
     */
    public function testNonContainerNodesDoNotChangeDepth(): void
    {
        $html = '<!DOCTYPE html><div><!-- a <div> comment --><hr><input type="text"><x-icon/><![CDATA[ <div> ]]><p>After</p></div>';
        $expected = "<!DOCTYPE html>\n<div>\n    <!-- a <div> comment -->\n    <hr>\n    <input type=\"text\">\n    <x-icon/>\n    <![CDATA[ <div> ]]>\n    <p>After</p>\n</div>";
        $result = HtmlDataType::prettify($html);
        $this->assertSame($expected, $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Attributes, their order, quotes and entities must remain untouched - even with whitespace
     * or ">" inside quoted values.
     *
     * @return void
     */
    public function testAttributesAreUntouched(): void
    {
        $tag = "<div data-b='x > y' class=\"a  b\" title=&quot;t&quot; data-a=\"&amp;&lt;\">";
        $html = $tag . '<p>&nbsp;&amp;</p></div>';
        $result = HtmlDataType::prettify($html);
        $this->assertSame($tag . "\n    <p>&nbsp;&amp;</p>\n</div>", $result);
        $this->assertOnlyWhitespaceChanged($html, $result);
    }

    /**
     * Empty and whitespace-only inputs must produce an empty string without errors.
     *
     * @return void
     */
    public function testEmptyAndWhitespaceOnlyInput(): void
    {
        $this->assertSame('', HtmlDataType::prettify(''));
        $this->assertSame('', HtmlDataType::prettify(" \n\t  \r\n "));
    }

    /**
     * Very long single-line inputs (large HTTP responses) must not throw or exhaust regex limits.
     *
     * @return void
     */
    public function testVeryLongSingleLineInputDoesNotThrow(): void
    {
        $html = '<div>' . str_repeat('<div class="row"><span>x</span> text <b>y</b></div>', 20000) . '</div>';
        $result = HtmlDataType::prettify($html);
        $this->assertOnlyWhitespaceChanged($html, $result);

        $text = str_repeat('word ', 200000);
        $this->assertSame(trim($text), HtmlDataType::prettify($text));
    }

    /**
     * Asserts that input and output are equal after removing all whitespace - this proves that
     * prettify() changed nothing but whitespace.
     *
     * @param string $input
     * @param string $output
     * @return void
     */
    private function assertOnlyWhitespaceChanged(string $input, string $output): void
    {
        $this->assertSame(
            preg_replace('/\s+/', '', $input),
            preg_replace('/\s+/', '', $output),
            'prettify() must not change anything but whitespace'
        );
    }
}