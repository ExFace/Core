<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeDefaultObjectEditorInitEvent;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

/**
 * Applies mutations before the default editor of the object is instantiated - right after the TranslatableBehavior
 *
 * @author Andrej Kabachnik
 */
class ObjectDefaultEditorMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     * See static event listeners in System.config.json
     *
     * @param OnBeforeDefaultObjectEditorInitEvent $event
     * @return void
     */
    public static function onDefaultEditorLoadedApplyMutations(OnBeforeDefaultObjectEditorInitEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.OBJECT', $event->getObject()->getId());
        $applied = $point->applyMutations($target, $event->getDefaultEditorUxon());

        if (! empty($applied)) {
            $point->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent($point, $applied, 'default editor of ' . $event->getObject()->__toString()));
        }
    }
}