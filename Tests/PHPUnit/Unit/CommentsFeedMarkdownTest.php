<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\CommentsFeed;
use PHPUnit\Framework\TestCase;

/**
 * Covers opt-in comment Markdown using a real, unstarted Workbench without a database.
 */
class CommentsFeedMarkdownTest extends TestCase
{
    /**
     * Keep existing feeds on plain-text rendering unless explicitly configured.
     *
     * @return void
     */
    public function testMarkdownIsDisabledByDefault() : void
    {
        $this->assertFalse($this->createFeed()->getEnableMarkdown());
    }

    /**
     * Import both enabled and disabled values through the normal UXON setter mapping.
     *
     * @return void
     */
    public function testMarkdownCanBeEnabledAndDisabledViaUxon() : void
    {
        $feed = $this->createFeed();
        $feed->importUxonObject(new UxonObject(['enable_markdown' => true]));
        $this->assertTrue($feed->getEnableMarkdown());
        $feed->importUxonObject(new UxonObject(['enable_markdown' => false]));
        $this->assertFalse($feed->getEnableMarkdown());
    }

    /**
     * Preserve fluent configuration for callers using the setter directly.
     *
     * @return void
     */
    public function testMarkdownSetterReturnsTheFeed() : void
    {
        $feed = $this->createFeed();
        $this->assertSame($feed, $feed->setEnableMarkdown(true));
        $this->assertTrue($feed->getEnableMarkdown());
    }

    /**
     * Build a feed without a persisted metamodel or application startup.
     *
     * @return CommentsFeed
     */
    private function createFeed() : CommentsFeed
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'comments-markdown-unit-test']);
        $workbench->getConfig()->loadConfigFile(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'exface.Core.config.json'
        );
        return new CommentsFeed(UiPageFactory::createBlank($workbench, ''));
    }
}
