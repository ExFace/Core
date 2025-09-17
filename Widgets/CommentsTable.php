<?php
namespace exface\Core\Widgets;

use exface\Core\Behaviors\CommentBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

/**
 * BETA! Shows a feed of comments (as seen in blogs or news pages) with a on option of quickly adding a comment
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class CommentsTable extends Data implements iCanEditData, iTakeInput, iFillEntireContainer
{
    use EditableTableTrait;
    
    public function importUxonObject(UxonObject $uxon)
    {
        if ($commentBehavior = $this->getMetaObject()->getBehaviors()->findBehavior(CommentBehavior::class)) {
            $behaviorUxon = new UxonObject([
                // TODO add typical table config for comments
            ]);
            parent::importUxonObject($behaviorUxon);
        }
        parent::importUxonObject($uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
}