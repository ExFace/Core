<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnUiPageLoadedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

/**
 * Applies mutations when UI pages are loaded - right after the TranslatableBehavior
 *
 * TODO allow mutations to change page properties like name or description
 * TODO also need to apply mutations to menu items
 *
 * @author Andrej Kabachnik
 */
class UiPageMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     * See static event listeners in System.config.json
     *
     * @param OnUiPageLoadedEvent $event
     * @return void
     */
    public static function onPageLoadedApplyMutations(OnUiPageLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.PAGE', $event->getPage()->getUid());
        $point->applyMutations($target, $event->getPage());
    }
}