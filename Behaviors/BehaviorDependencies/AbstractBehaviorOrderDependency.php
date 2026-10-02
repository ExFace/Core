<?php

namespace exface\Core\Behaviors\BehaviorDependencies;

use exface\Core\Interfaces\Model\BehaviorDependencyInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;

/**
 * Base class for managing behavior order dependencies, i.e. making sure a behavior runs "Before" or "After"
 * a specified list of behaviors. Note that ordering dependencies are resolved naively: If multiple ordering
 * dependencies conflict with one another, the last one to be applied wins out.
 * 
 * // TODO If the dependency pattern proves to be valuable we might upgrade this with proper graph resolution.
 */
abstract class AbstractBehaviorOrderDependency implements BehaviorDependencyInterface
{
    private array $orderAgainstBehaviorClasses;

    function __construct(array $orderAgainstBehaviorClasses)
    {
        $this->orderAgainstBehaviorClasses = $orderAgainstBehaviorClasses;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        BehaviorInterface     $subjectBehavior,
        BehaviorListInterface $otherBehaviors,
        array                 $behaviorClasses
    ): void
    {
        $targetPriority = $currentPriority = $subjectBehavior->getPriority() ?? 0;
        
        // Find the desired priority for the subject
        foreach ($otherBehaviors as $key => $behavior) {
            if(in_array($behaviorClasses[$key], $this->orderAgainstBehaviorClasses)) {
                $targetPriority = $this->comparePriorities($subjectBehavior, $behavior) ? 
                    $targetPriority : 
                    $behavior->getPriority() ?? 0;
            }
        }
        
        if($this->isInOrder($currentPriority, $targetPriority)) {
            return;
        }

        // Shift the priorities of all other behaviors to maintain pre-existing dependencies.
        $otherBehaviors->sort(function ($self, $other) { return !$this->comparePriorities($self, $other);} );
        $currentPriority = $targetPriority;
        $shiftedIndices = [];

        foreach ($otherBehaviors as $key => $behavior) {
            if($behavior === $subjectBehavior) {
                $shiftedIndices[$key] = $targetPriority;
                continue;
            }

            $priority = $behavior->getPriority() ?? 0;
            $nextPriority = $this->getNextPriority($currentPriority);
            
            switch (true) {
                // Shift from current to next.
                case $priority === $currentPriority:
                    break;
                // Move on to nex index, then shift from there.
                case $priority === $nextPriority:
                    $currentPriority = $nextPriority;
                    $nextPriority = $this->getNextPriority($currentPriority);
                    break;
                // If we have not shifted any indices yet, we keep searching.
                case empty($shiftedIndices):
                    continue 2;
                // If we did not create any new overlaps, we are done.
                default:
                    break 2;
            }

            $shiftedIndices[$key] = $nextPriority;
        }
        
        // Now we have to ensure all indices remain within the boundaries of EventManagerInterface.
        $delta = $this->getShiftDelta($shiftedIndices);
        foreach ($shiftedIndices as $key => $index) {
            $behavior = $otherBehaviors->get($key);
            $enable = !$behavior->isDisabled();
            
            // We have to toggle the behaviors to ensure that the shifted priorities are applied
            // to all event delegates.
            $behavior->disable();
            
            $behavior->setPriority($index + $delta);
            
            if($enable) {
                $behavior->enable();
            }
        }
    }

    /**
     * @param BehaviorInterface $self
     * @param BehaviorInterface $other
     * @return bool
     */
    protected abstract function comparePriorities(BehaviorInterface $self, BehaviorInterface $other) : bool;

    /**
     * Returns TRUE if `$currentPriority` already fulfills the desired conditions.
     * 
     * @param int $currentPriority
     * @param int $targetPriority
     * @return bool
     */
    protected abstract function isInOrder(int $currentPriority, int $targetPriority) : bool;

    /**
     * @param int $priority
     * @return int
     */
    protected abstract function getNextPriority(int $priority) : int;

    /**
     * @param array $shiftedIndices
     * @return int
     */
    protected abstract function getShiftDelta(array $shiftedIndices) : int;
}