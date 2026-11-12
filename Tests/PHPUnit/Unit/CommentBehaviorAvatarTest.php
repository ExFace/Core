<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\CommentBehavior;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\CommentsFeed;
use PHPUnit\Framework\TestCase;

/**
 * Covers optional behavior-provided avatars using an in-memory model and a real, unstarted Workbench.
 */
class CommentBehaviorAvatarTest extends TestCase
{
    /**
     * Existing behavior configurations must remain valid without an avatar attribute.
     *
     * @return void
     */
    public function testAvatarAttributeIsOptional() : void
    {
        [$feed, $behavior] = $this->createFeedWithBehavior();

        $this->assertNull($behavior->getCommentAvatarUrlAttribute());
        $this->assertCount(6, $behavior->getCommentAttributes());
        $this->assertFalse($feed->hasCommentAvatarUrlColumn());
        $this->assertSame('CONTENT', $feed->getCommentContentColumn()->getAttributeAlias());
    }

    /**
     * UXON configuration must resolve and enumerate the behavior's avatar attribute.
     *
     * @return void
     */
    public function testBehaviorResolvesAvatarAttribute() : void
    {
        [, $behavior] = $this->createFeedWithBehavior(['comment_avatar_url_attribute' => 'AVATAR_URL']);
        $attribute = $behavior->getObject()->getAttribute('AVATAR_URL');

        $this->assertSame($attribute, $behavior->getCommentAvatarUrlAttribute());
        $this->assertContains($attribute, $behavior->getCommentAttributes());
        $this->assertCount(7, $behavior->getCommentAttributes());
    }

    /**
     * Requesting the avatar first must infer and register its hidden data column.
     *
     * @return void
     */
    public function testFeedInfersAvatarBeforeOtherColumns() : void
    {
        [$feed] = $this->createFeedWithBehavior(['comment_avatar_url_attribute' => 'AVATAR_URL']);
        $column = $feed->getCommentAvatarUrlColumn();

        $this->assertTrue($feed->hasCommentAvatarUrlColumn());
        $this->assertSame('AVATAR_URL', $column->getAttributeAlias());
        $this->assertTrue($column->isHidden());
        $this->assertSame($column, $feed->getColumnByAttributeAlias('AVATAR_URL'));
    }

    /**
     * Facades must discover behavior-provided avatars through the presence check alone.
     *
     * @return void
     */
    public function testPresenceCheckInfersAvatar() : void
    {
        [$feed] = $this->createFeedWithBehavior(['comment_avatar_url_attribute' => 'AVATAR_URL']);

        $this->assertTrue($feed->hasCommentAvatarUrlColumn());
        $this->assertSame('AVATAR_URL', $feed->getColumnByAttributeAlias('AVATAR_URL')->getAttributeAlias());
    }

    /**
     * Inferring other comment columns must not replace an explicit widget avatar.
     *
     * @return void
     */
    public function testWidgetAvatarOverridesBehavior() : void
    {
        [$feed] = $this->createFeedWithBehavior(['comment_avatar_url_attribute' => 'AVATAR_URL']);
        $feed->importUxonObject(new UxonObject(['comment_avatar_url_attribute' => 'CUSTOM_AVATAR_URL']));
        $feed->getCommentContentColumn();

        $this->assertSame('CUSTOM_AVATAR_URL', $feed->getCommentAvatarUrlColumn()->getAttributeAlias());
    }

    /**
     * Build a fully configured comment behavior and its feed without database access or application startup.
     *
     * @param array<string,string> $configuration
     * @return array{CommentsFeed,CommentBehavior}
     */
    private function createFeedWithBehavior(array $configuration = []) : array
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'comment-behavior-unit-test']);
        $workbench->getConfig()->loadConfigFile(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'exface.Core.config.json'
        );
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('COMMENT');
        $configuration = array_merge([
            'comment_content_attribute' => 'CONTENT',
            'comment_id_attribute' => 'ID',
            'comment_created_date_attribute' => 'CREATED_ON',
            'comment_edited_date_attribute' => 'MODIFIED_ON',
            'comment_author_id_attribute' => 'AUTHOR_ID',
            'comment_author_attribute' => 'AUTHOR_NAME'
        ], $configuration);
        foreach (array_unique(array_merge(array_values($configuration), ['AVATAR_URL', 'CUSTOM_AVATAR_URL'])) as $alias) {
            $attribute = new Attribute($object, $alias, $alias);
            $attribute->setDataType('exface.Core.String');
            $object->getAttributes()->add($attribute);
        }
        $behavior = BehaviorFactory::createFromUxon($object, CommentBehavior::class, new UxonObject($configuration));
        $object->getBehaviors()->add($behavior, 'comment-behavior');
        $feed = new CommentsFeed(UiPageFactory::createBlank($workbench, ''));
        $feed->setMetaObject($object);
        return [$feed, $behavior];
    }
}