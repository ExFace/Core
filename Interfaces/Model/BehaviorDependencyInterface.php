<?php

namespace exface\Core\Interfaces\Model;

/**
 * Basic interface for behavior dependencies.
 * 
 * Behavior dependencies allow you to define certain conditions that behaviors must fulfill BEFORE they become
 * active. This can be a simple validation check or even transformation logic. 
 */
interface BehaviorDependencyInterface
{
    /**
     * Resolves this dependency for a given subject.
     * 
     * @param BehaviorInterface     $subjectBehavior
     * @param BehaviorListInterface $otherBehaviors
     * @param array                 $behaviorClasses
     * @return void
     */
    public function resolve(
        BehaviorInterface     $subjectBehavior, 
        BehaviorListInterface $otherBehaviors,
        array                 $behaviorClasses
    ) : void;
}