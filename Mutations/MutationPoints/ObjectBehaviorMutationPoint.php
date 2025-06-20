<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeMetaObjectBehaviorLoadedEvent;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

/**
 * Applies mutations before a behavior is instantiated - right after the TranslatableBehavior
 *
 * @author Andrej Kabachnik
 */
class ObjectBehaviorMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     * See static event listeners in System.config.json
     *
     * @param OnBeforeMetaObjectBehaviorLoadedEvent $event
     * @return void
     */
    public static function onBehaviorLoadedApplyMutations(OnBeforeMetaObjectBehaviorLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.OBJECT_BEHAVIORS', $event->getBehaviorUid());
        $applied = $point->applyMutations($target, $event->getUxon());

        if (! empty($applied)) {
            $point->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent($point, $applied, 'behavior "' . $event->getUxon()->getProperty('name') . '"'));
        }
    }
}