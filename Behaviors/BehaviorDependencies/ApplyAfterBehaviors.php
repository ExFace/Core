<?php

namespace exface\Core\Behaviors\BehaviorDependencies;

use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Make sure the subject is applied AFTER a given list of behaviors.
 */
class ApplyAfterBehaviors extends AbstractBehaviorOrderDependency
{
    /**
     * @inheritDoc
     */
    protected function comparePriorities(BehaviorInterface $self, BehaviorInterface $other): bool
    {
        return ($self->getPriority() ?? 0) < ($other->getPriority() ?? 0);
    }

    /**
     * @inheritDoc
     */
    protected function isInOrder(int $currentPriority, int $targetPriority): bool
    {
        if($targetPriority > EventManagerInterface::PRIORITY_MIN) {
            if($currentPriority <= $targetPriority) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getNextPriority(int $priority): int
    {
        return $priority + 1;
    }

    /**
     * @inheritDoc
     */
    protected function getShiftDelta(array $shiftedIndices) : int
    {
        $maxPriority = max($shiftedIndices);
        return EventManagerInterface::PRIORITY_MAX - $maxPriority;
    }
}