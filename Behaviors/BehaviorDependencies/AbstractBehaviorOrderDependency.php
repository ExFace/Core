<?php

namespace exface\Core\Behaviors\BehaviorDependencies;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Model\BehaviorDependencyInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;

abstract class AbstractBehaviorOrderDependency implements BehaviorDependencyInterface
{
    private array $orderAgainstBehaviorClasses;

    function __construct(array $orderAgainstBehaviorClasses)
    {
        $this->orderAgainstBehaviorClasses = $orderAgainstBehaviorClasses;
    }
    
    public function apply(
        BehaviorInterface $toBehavior,
        BehaviorListInterface $behaviors,
        array $behaviorClasses
    ): void
    {
        $targetPriority = $currentPriority = $toBehavior->getPriority();
        
        foreach ($behaviors as $i => $behavior) {
            if(in_array($behaviorClasses[$i], $this->orderAgainstBehaviorClasses)) {
                $targetPriority = $this->comparePriorities($targetPriority, $behavior->getPriority());
            }
        }

        $targetPriority = $this->processTargetPriority($currentPriority, $targetPriority);
        if($targetPriority === true) {
            return;
        }

        $toBehavior->setPriority($targetPriority);
        $occupiedPriority = $targetPriority;
        
        foreach ($behaviors as $behavior) {
            // TODO 2025-12-05: Should we flatten the entire list or shift as few priorities as possible?
            // TODO Flattening the entire list feels "right" but is a bigger change than just cleaning up locally.
            if($behavior->getPriority() !== $occupiedPriority) {
                break;
            }
            
            $occupiedPriority = $this->shiftOccupiedPriority($occupiedPriority);
            $behavior->setPriority($occupiedPriority);
        }
    }
    
    protected abstract function comparePriorities(int $self, int $other) : int;
    
    protected abstract function processTargetPriority(
        int $currentPriority,
        int $targetPriority
    ) : int|true;
    
    protected abstract function shiftOccupiedPriority(int $priority) : int;
}