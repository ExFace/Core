<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeMetaObjectActionLoadedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;
/**
 * Applies mutations before an object action is instantiated - right after the TranslatableBehavior
 *
 * @author Andrej Kabachnik
 */
class ObjectActionMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     *  See static event listeners in System.config.json
     *
     * @param OnBeforeMetaObjectActionLoadedEvent $event
     * @return void
     */
    public static function onActionLoadedApplyMutations(OnBeforeMetaObjectActionLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.OBJECT_ACTION', $event->getActionUid());
        $point->applyMutations($target, $event->getUxon());
    }
}