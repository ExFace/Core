<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeDataTypeLoadedEvent;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;
/**
 * Applies mutations before a data type is instantiated - right after the TranslatableBehavior
 *
 * @author Andrej Kabachnik
 */
class DataTypeMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     * See static event listeners in System.config.json
     *
     * @param OnBeforeDataTypeLoadedEvent $event
     * @return void
     */
    public static function onDataTypeLoadedApplyMutations(OnBeforeDataTypeLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.DATATYPE', $event->getDataTypeUid());
        $applied = $point->applyMutations($target, $event->getUxon());

        if (! empty($applied)) {
            $point->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent($applied, 'data type "' . $event->getDataTypeAlias() . '"', $point));
        }
    }
}