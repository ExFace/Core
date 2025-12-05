<?php

namespace exface\Core\Behaviors\BehaviorDependencies;

use exface\Core\Interfaces\Events\EventManagerInterface;

class ApplyAfterBehaviors extends AbstractBehaviorOrderDependency
{
    protected function comparePriorities(int $self, int $other): int
    {
        return min($self, $other);
    }

    protected function processTargetPriority(int $currentPriority, int $targetPriority): int|true
    {
        if($targetPriority > EventManagerInterface::PRIORITY_MIN) {
            if($currentPriority <= $targetPriority) {
                return true;
            }
            
            $targetPriority -= 1;
        }

        return $targetPriority;
    }

    protected function shiftOccupiedPriority(int $priority): int
    {
        return $priority + 1;
    }
}