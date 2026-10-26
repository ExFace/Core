<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Markdown;

use exface\Core\CommonLogic\Markdown\GithubAlertBlock;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers GitHub-style alert boxes (`> [!WARNING]`) rendered by GithubAlertExtension.
 *
 * The tests go through MarkdownDataType::convertMarkdownToHtml() to also make sure the extension
 * is registered in the environment used by all Core Markdown rendering paths.
 */
class GithubAlertExtensionTest extends TestCase
{
    /**
     * Provides every supported alert type with its expected CSS class suffix and title.
     *
     * Exists to make sure all five GitHub alert types produce GitHub's markup.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function alertTypeProvider() : array
    {
        return [
            'note' => ['NOTE', 'note', 'Note'],
            'tip' => ['TIP', 'tip', 'Tip'],
            'important' => ['IMPORTANT', 'important', 'Important'],
            'warning' => ['WARNING', 'warning', 'Warning'],
            'caution' => ['CAUTION', 'caution', 'Caution']
        ];
    }

    /**
     * Each supported marker turns the blockquote into an alert box without the marker text.
     *
     * @param string $marker
     * @param string $cssType
     * @param string $title
     * @return void
     */
    #[DataProvider('alertTypeProvider')]
    public function testAlertTypeIsRenderedAsGithubAlert(string $marker, string $cssType, string $title) : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-{$cssType}\">\n<p class=\"markdown-alert-title\">{$title}</p>\n<p>This action cannot be undone.</p>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!{$marker}]\n> This action cannot be undone.")
        );
    }

    /**
     * Markers are case-insensitive, but the title always uses GitHub's capitalization.
     *
     * @return void
     */
    public function testMarkerIsCaseInsensitive() : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-warning\">\n<p class=\"markdown-alert-title\">Warning</p>\n<p>Text</p>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!wArNiNg]\n> Text")
        );
    }

    /**
     * Only the marker line is removed - further lines keep their line breaks in both newline modes.
     *
     * @return void
     */
    public function testMultilineContentKeepsLineBreaks() : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n<p>a<br />\nb</p>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!NOTE]\n> a\n> b")
        );
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n<p>a\nb</p>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!NOTE]\n> a\n> b", false)
        );
    }

    /**
     * A marker followed by a blank line and several blocks produces an alert with all those blocks.
     *
     * @return void
     */
    public function testMarkerFollowedByBlankLineAndMultipleBlocks() : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-tip\">\n<p class=\"markdown-alert-title\">Tip</p>\n<p>First</p>\n<ul>\n<li>Item</li>\n</ul>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!TIP]\n>\n> First\n>\n> - Item")
        );
    }

    /**
     * Trailing spaces after the marker (a hard line break) are tolerated like on GitHub.
     *
     * @return void
     */
    public function testMarkerWithTrailingSpaces() : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-tip\">\n<p class=\"markdown-alert-title\">Tip</p>\n<p>Text</p>\n</div>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!TIP]  \n> Text")
        );
    }

    /**
     * Alerts are also detected inside other blocks like list items.
     *
     * @return void
     */
    public function testAlertInsideListItem() : void
    {
        $this->assertSame(
            "<ul>\n<li>\n<div class=\"markdown-alert markdown-alert-caution\">\n<p class=\"markdown-alert-title\">Caution</p>\n<p>Text</p>\n</div>\n</li>\n</ul>\n",
            MarkdownDataType::convertMarkdownToHtml("- > [!CAUTION]\n  > Text")
        );
    }

    /**
     * Content after the blockquote is not pulled into the alert.
     *
     * @return void
     */
    public function testContentAfterAlertStaysOutside() : void
    {
        $this->assertSame(
            "<div class=\"markdown-alert markdown-alert-important\">\n<p class=\"markdown-alert-title\">Important</p>\n<p>Inside</p>\n</div>\n<p>Outside</p>\n",
            MarkdownDataType::convertMarkdownToHtml("> [!IMPORTANT]\n> Inside\n\nOutside")
        );
    }

    /**
     * Provides Markdown, that looks similar to an alert, but must stay a regular blockquote.
     *
     * Exists to guard against false positives, that would alter existing content.
     *
     * @return array<string, array{string, string}>
     */
    public static function noAlertProvider() : array
    {
        return [
            'normal blockquote' => [
                "> Just a quote\n> second line",
                "<blockquote>\n<p>Just a quote<br />\nsecond line</p>\n</blockquote>\n"
            ],
            'unknown type' => [
                "> [!FOO]\n> Text",
                "<blockquote>\n<p>[!FOO]<br />\nText</p>\n</blockquote>\n"
            ],
            'marker not alone on its line' => [
                "> [!NOTE] Text on the same line\n> More",
                "<blockquote>\n<p>[!NOTE] Text on the same line<br />\nMore</p>\n</blockquote>\n"
            ],
            'marker not at the beginning' => [
                "> Text\n> [!NOTE]",
                "<blockquote>\n<p>Text<br />\n[!NOTE]</p>\n</blockquote>\n"
            ],
            'marker in code span' => [
                "> `[!NOTE]`\n> Text",
                "<blockquote>\n<p><code>[!NOTE]</code><br />\nText</p>\n</blockquote>\n"
            ],
            'marker in fenced code block' => [
                "> ```\n> [!NOTE]\n> Text\n> ```",
                "<blockquote>\n<pre><code>[!NOTE]\nText\n</code></pre>\n</blockquote>\n"
            ],
            'marker in code block outside blockquote' => [
                "```\n> [!NOTE]\n> Text\n```",
                "<pre><code>&gt; [!NOTE]\n&gt; Text\n</code></pre>\n"
            ],
            'marker without content' => [
                "> [!WARNING]",
                "<blockquote>\n<p>[!WARNING]</p>\n</blockquote>\n"
            ],
            'marker without content followed by blank quote line' => [
                "> [!WARNING]\n>",
                "<blockquote>\n<p>[!WARNING]</p>\n</blockquote>\n"
            ],
            'marker without blockquote' => [
                "[!NOTE]\nText",
                "<p>[!NOTE]<br />\nText</p>\n"
            ]
        ];
    }

    /**
     * Markdown not matching the GitHub alert syntax exactly is rendered unchanged.
     *
     * @param string $markdown
     * @param string $expectedHtml
     * @return void
     */
    #[DataProvider('noAlertProvider')]
    public function testNonAlertsStayUnchanged(string $markdown, string $expectedHtml) : void
    {
        $this->assertSame($expectedHtml, MarkdownDataType::convertMarkdownToHtml($markdown));
    }

    /**
     * Creating an alert node with an unsupported type fails instead of rendering an unstyled box.
     *
     * @return void
     */
    public function testUnsupportedAlertTypeThrows() : void
    {
        $this->expectException(RuntimeException::class);
        new GithubAlertBlock('foo');
    }
}
