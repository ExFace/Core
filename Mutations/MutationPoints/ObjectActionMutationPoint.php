<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeMetaObjectActionLoadedEvent;
use exface\Core\Interfaces\Mutations\MutationPointInterface;
use exface\Core\Interfaces\Mutations\MutationTargetInterface;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

class ObjectActionMutationPoint extends AbstractMutationPoint
{
    public static function onActionLoadedApplyMutations(OnBeforeMetaObjectActionLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.OBJECT_ACTION', $event->getActionUid());
        $point->applyMutations($target, $event->getUxon());
    }
}