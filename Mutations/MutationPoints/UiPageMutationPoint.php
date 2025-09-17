<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnUiMenuItemLoadedEvent;
use exface\Core\Events\Model\OnUiPageLoadedEvent;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

/**
 * Applies mutations when UI pages or menu items are loaded - right after the TranslatableBehavior
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
    public static function onUiMenuItemLoadedApplyMutations(OnUiMenuItemLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $menuItem = $event->getMenuItem();
        $target = new MetaObjectUidMutationTarget('exface.Core.PAGE', $menuItem->getUid());
        $applied = $point->applyMutations($target, $menuItem);

        if (! empty($applied)) {
            $subjType = $menuItem instanceof UiPageInterface ? 'page' : 'menu item';
            $point->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent($applied, $subjType . ' "' . $menuItem->getAliasWithNamespace() . '"', $point));
        }
    }
}