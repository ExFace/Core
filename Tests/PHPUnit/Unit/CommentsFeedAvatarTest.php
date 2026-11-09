<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\CommentsFeed;
use PHPUnit\Framework\TestCase;

/**
 * Covers optional avatar columns using a real, unstarted Workbench and in-memory model.
 */
class CommentsFeedAvatarTest extends TestCase
{
    /**
     * An unconfigured feed must not require or infer an avatar attribute.
     *
     * @return void
     */
    public function testAvatarColumnIsOptional() : void
    {
        $this->assertFalse($this->createFeed()->hasCommentAvatarUrlColumn());
    }

    /**
     * Accessing an unconfigured avatar column must fail explicitly.
     *
     * @return void
     */
    public function testMissingAvatarColumnRaisesConfigurationError() : void
    {
        $feed = $this->createFeed();
        $this->expectException(WidgetConfigurationError::class);
        $this->expectExceptionMessage('No comment avatar URL attribute configured!');
        $feed->getCommentAvatarUrlColumn();
    }

    /**
     * UXON configuration must register a hidden column for loading avatar URLs.
     *
     * @return void
     */
    public function testUxonRegistersHiddenAvatarColumn() : void
    {
        $feed = $this->createFeed();
        $feed->importUxonObject(new UxonObject(['comment_avatar_url_attribute' => 'AVATAR_URL']));

        $this->assertTrue($feed->hasCommentAvatarUrlColumn());
        $column = $feed->getCommentAvatarUrlColumn();
        $this->assertSame('AVATAR_URL', $column->getAttributeAlias());
        $this->assertTrue($column->isHidden());
        $this->assertSame($column, $feed->getColumnByAttributeAlias('AVATAR_URL'));
    }

    /**
     * Build a feed without database access, authorization or application startup.
     *
     * @return CommentsFeed
     */
    private function createFeed() : CommentsFeed
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'comments-feed-unit-test']);
        $workbench->getConfig()->loadConfigFile(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'exface.Core.config.json'
        );
        $page = UiPageFactory::createBlank($workbench, '');
        $feed = new CommentsFeed($page);
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('COMMENT');
        $attribute = new Attribute($object, 'Avatar URL', 'AVATAR_URL');
        $attribute->setDataType('exface.Core.String');
        $object->getAttributes()->add($attribute);
        $feed->setMetaObject($object);
        return $feed;
    }
}
