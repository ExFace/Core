<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\DataTypes\MarkdownDataType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the Markdown-to-HTML conversion of MarkdownDataType.
 *
 * All Markdown rendering in Core (DocsFacade, Markdown widget, UXON schema docs, debug context
 * and MarkdownDataType::toHtml()) goes through MarkdownDataType::convertMarkdownToHtml(). These
 * tests make sure a parser upgrade or configuration change cannot silently alter the HTML of
 * the Markdown patterns used in Docs/ and in app models.
 *
 * Behavior that intentionally changed when replacing the previous Markdown parser with
 * league/commonmark is covered by dedicated, documented test methods below.
 */
class MarkdownDataTypeTest extends TestCase
{
    /**
     * Provides Markdown inputs and the exact HTML expected with newline mode enabled.
     *
     * Exists to cover every Markdown construct used in the Docs/ corpus in one place.
     *
     * @return array<string, array{string, string}>
     */
    public static function conversionProvider(): array
    {
        return [
            'table with alignment' => [
                "| A | B |\n|---|:-:|\n| 1 | 2 |",
                "<table>\n<thead>\n<tr>\n<th>A</th>\n<th align=\"center\">B</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>1</td>\n<td align=\"center\">2</td>\n</tr>\n</tbody>\n</table>\n"
            ],
            'link with title and query' => [
                '[Docs](https://example.com/a?b=1&c=2 "Title")',
                "<p><a href=\"https://example.com/a?b=1&amp;c=2\" title=\"Title\">Docs</a></p>\n"
            ],
            'relative link' => [
                '[Other page](../Folder/Page.md)',
                "<p><a href=\"../Folder/Page.md\">Other page</a></p>\n"
            ],
            'image' => [
                '![Alt](images/pic.png)',
                "<p><img src=\"images/pic.png\" alt=\"Alt\" /></p>\n"
            ],
            'mermaid code block' => [
                "```mermaid\ngraph TD\n  A-->B\n```",
                "<pre><code class=\"language-mermaid\">graph TD\n  A--&gt;B\n</code></pre>\n"
            ],
            'front matter followed by blank line' => [
                "---\ntitle: Test\n---\n\nSome text",
                "<blockquote>\n<pre><code class=\"language-yaml-frontmatter\">---\ntitle: Test\n---\n</code></pre>\n</blockquote>\n<p>Some text</p>\n"
            ],
            'fenced code with language' => [
                "```php\n\$a = '<b>';\n```",
                "<pre><code class=\"language-php\">\$a = '&lt;b&gt;';\n</code></pre>\n"
            ],
            'fenced code without language' => [
                "```\nplain  text\n\n  indented\n```",
                "<pre><code>plain  text\n\n  indented\n</code></pre>\n"
            ],
            'indented code' => [
                "Text\n\n    code <x>\n    line2",
                "<p>Text</p>\n<pre><code>code &lt;x&gt;\nline2\n</code></pre>\n"
            ],
            'inline code' => [
                'Use `<b>` and `a  b`',
                "<p>Use <code>&lt;b&gt;</code> and <code>a  b</code></p>\n"
            ],
            'raw inline html' => [
                'Hello <span class="x">World</span>!',
                "<p>Hello <span class=\"x\">World</span>!</p>\n"
            ],
            'raw block html' => [
                "<div class=\"note\">\n<b>Raw</b>\n</div>\n\nAfter",
                "<div class=\"note\">\n<b>Raw</b>\n</div>\n<p>After</p>\n"
            ],
            'unordered list' => [
                "- One\n- Two",
                "<ul>\n<li>One</li>\n<li>Two</li>\n</ul>\n"
            ],
            'ordered list starting at 1' => [
                "1. One\n2. Two",
                "<ol>\n<li>One</li>\n<li>Two</li>\n</ol>\n"
            ],
            'emphasis and strikethrough' => [
                '*em* **strong** ~~gone~~',
                "<p><em>em</em> <strong>strong</strong> <del>gone</del></p>\n"
            ],
            'headings' => [
                "# H1\n\n## H2",
                "<h1>H1</h1>\n<h2>H2</h2>\n"
            ],
            'blockquote' => [
                "> Quote\n\nAfter",
                "<blockquote>\n<p>Quote</p>\n</blockquote>\n<p>After</p>\n"
            ],
            'http autolink with trailing period' => [
                'See http://example.com/path.',
                "<p>See <a href=\"http://example.com/path\">http://example.com/path</a>.</p>\n"
            ],
            'https autolink with trailing comma' => [
                'See https://example.com/a, and more',
                "<p>See <a href=\"https://example.com/a\">https://example.com/a</a>, and more</p>\n"
            ],
            'ftp autolink with trailing exclamation mark' => [
                'Get ftp://files.example.com/x.zip!',
                "<p>Get <a href=\"ftp://files.example.com/x.zip\">ftp://files.example.com/x.zip</a>!</p>\n"
            ],
            'autolink inside parentheses' => [
                '(see https://example.com/x)',
                "<p>(see <a href=\"https://example.com/x\">https://example.com/x</a>)</p>\n"
            ],
            'angle bracket email autolink' => [
                'Mail <user@example.com> please',
                "<p>Mail <a href=\"mailto:user@example.com\">user@example.com</a> please</p>\n"
            ],
            'angle bracket url autolink' => [
                'Go <https://example.com> now',
                "<p>Go <a href=\"https://example.com\">https://example.com</a> now</p>\n"
            ],
            'single newline becomes br' => [
                "Line one\nLine two",
                "<p>Line one<br />\nLine two</p>\n"
            ],
        ];
    }

    /**
     * Pins the HTML of common Markdown constructs with newline mode enabled (the default).
     *
     * @param string $markdown
     * @param string $expectedHtml
     * @return void
     */
    #[DataProvider('conversionProvider')]
    public function testConvertMarkdownToHtml(string $markdown, string $expectedHtml): void
    {
        $this->assertSame($expectedHtml, MarkdownDataType::convertMarkdownToHtml($markdown));
    }

    /**
     * Without newline mode a single line break must stay a soft break, so that hard-wrapped
     * source text is rendered as one flowing paragraph.
     *
     * @return void
     */
    public function testSingleNewlineIsSoftBreakWhenNewlinesDisabled(): void
    {
        $this->assertSame("<p>Line one\nLine two</p>\n", MarkdownDataType::convertMarkdownToHtml("Line one\nLine two", false));
    }

    /**
     * Two trailing spaces must still force a hard break when newline mode is disabled, because
     * that is the only way to get a line break in this mode.
     *
     * @return void
     */
    public function testTrailingDoubleSpaceIsHardBreakWhenNewlinesDisabled(): void
    {
        $this->assertSame("<p>Line one<br />\nLine two</p>\n", MarkdownDataType::convertMarkdownToHtml("Line one  \nLine two", false));
    }

    /**
     * Intentional change: ordered lists keep their start number.
     *
     * The previous parser always started at 1, which broke continued numbering in Docs
     * (e.g. Docs/Security/Authentication/index.md and the BookClub tutorial).
     *
     * @return void
     */
    public function testOrderedListKeepsStartNumber(): void
    {
        $this->assertSame("<ol start=\"3\">\n<li>Three</li>\n<li>Four</li>\n</ol>\n", MarkdownDataType::convertMarkdownToHtml("3. Three\n4. Four"));
    }

    /**
     * Intentional change: task list markers render as disabled checkboxes instead of literal "[ ]" / "[x]".
     *
     * @return void
     */
    public function testTaskListMarkersRenderAsCheckboxes(): void
    {
        $this->assertSame(
            "<ul>\n<li><input disabled=\"\" type=\"checkbox\"> Open</li>\n<li><input checked=\"\" disabled=\"\" type=\"checkbox\"> Done</li>\n</ul>\n",
            MarkdownDataType::convertMarkdownToHtml("- [ ] Open\n- [x] Done")
        );
    }

    /**
     * Intentional change (security fix): quotes in an email autolink must not break out of the href attribute.
     *
     * The previous parser turned this input into an <a> tag with an injected onmouseover attribute
     * (its upstream issue #192). The input is now rendered as escaped text.
     *
     * @return void
     */
    public function testEmailAutolinkQuoteInjectionIsEscaped(): void
    {
        $html = MarkdownDataType::convertMarkdownToHtml('<foo"onmouseover="alert(1)"@example.com>');
        $this->assertSame("<p>&lt;foo&quot;onmouseover=&quot;alert(1)&quot;@example.com&gt;</p>\n", $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    /**
     * Intentional change: www. URLs are now linked like scheme URLs.
     *
     * @return void
     */
    public function testWwwUrlIsLinked(): void
    {
        $this->assertSame(
            "<p>Visit <a href=\"http://www.example.com\">www.example.com</a> now</p>\n",
            MarkdownDataType::convertMarkdownToHtml('Visit www.example.com now')
        );
    }

    /**
     * Intentional change: bare email addresses are now linked with a mailto: href.
     *
     * @return void
     */
    public function testBareEmailIsLinked(): void
    {
        $this->assertSame(
            "<p>Mail <a href=\"mailto:user@example.com\">user@example.com</a> please</p>\n",
            MarkdownDataType::convertMarkdownToHtml('Mail user@example.com please')
        );
    }

    /**
     * Intentional change: text directly after front matter is no longer pulled into the
     * front matter blockquote (no lazy continuation into a fenced code block).
     *
     * @return void
     */
    public function testTextDirectlyAfterFrontMatterIsOutsideBlockquote(): void
    {
        $this->assertSame(
            "<blockquote>\n<pre><code class=\"language-yaml-frontmatter\">---\ntitle: Test\n---\n</code></pre>\n</blockquote>\n<p>Some text</p>\n",
            MarkdownDataType::convertMarkdownToHtml("---\ntitle: Test\n---\nSome text")
        );
    }

    /**
     * Intentional change: a heading directly after front matter is no longer pulled into the
     * front matter blockquote.
     *
     * @return void
     */
    public function testHeadingDirectlyAfterFrontMatterIsOutsideBlockquote(): void
    {
        $this->assertSame(
            "<blockquote>\n<pre><code class=\"language-yaml-frontmatter\">---\ntitle: Test\n---\n</code></pre>\n</blockquote>\n<h1>Head</h1>\n",
            MarkdownDataType::convertMarkdownToHtml("---\ntitle: Test\n---\n# Head")
        );
    }

    /**
     * escapeCodeInline() exists to keep values verbatim; its docblock promises that URLs
     * wrapped by it are not turned into links by the autolink extension.
     *
     * @return void
     */
    public function testEscapeCodeInlinePreventsAutolinking(): void
    {
        $html = MarkdownDataType::convertMarkdownToHtml(MarkdownDataType::escapeCodeInline('https://example.com'));
        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }
}
