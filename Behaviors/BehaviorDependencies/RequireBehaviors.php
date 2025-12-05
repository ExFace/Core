<?php

namespace exface\Core\Behaviors\BehaviorDependencies;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Interfaces\Model\BehaviorDependencyInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;

class RequireBehaviors implements BehaviorDependencyInterface
{
    private array $requiredBehaviorClasses;
    private array $forbiddenBehaviorClasses;

    function __construct(array $requiredBehaviorClasses = [], array $forbiddenBehaviorClasses = [])
    {
        $this->requiredBehaviorClasses = $requiredBehaviorClasses;

        $this->forbiddenBehaviorClasses = [];
        foreach ($forbiddenBehaviorClasses as $class) {
            if(!in_array($class, $requiredBehaviorClasses)) {
                $this->forbiddenBehaviorClasses[] = $class;
            }
        }
    }
    
    public function apply(
        BehaviorInterface $toBehavior, 
        BehaviorListInterface $behaviors, 
        array $behaviorClasses
    ) : void
    {
        $missingBehaviors = [];
        $conflictingBehaviors = [];
        
        foreach ($this->requiredBehaviorClasses as $requiredBehaviorClass) {
            if(!in_array($requiredBehaviorClass, $behaviorClasses)) {
                $missingBehaviors[] = StringDataType::substringAfter(
                    $requiredBehaviorClass,
                    '\\',
                    false,
                    false,
                    true
                );
            }
        }

        foreach ($this->forbiddenBehaviorClasses as $forbiddenBehaviorClass) {
            if(in_array($forbiddenBehaviorClass, $behaviorClasses)) {
                $conflictingBehaviors[] = StringDataType::substringAfter(
                    $forbiddenBehaviorClass,
                    '\\',
                    false,
                    false,
                    true
                );
            }
        }
        
        if(empty($missingBehaviors) && empty($conflictingBehaviors)) {
            return;
        }
        
        $msg = 'Could not register behavior "' . $toBehavior->getAliasWithNamespace() . '" on object "' .
            $toBehavior->getObject()->getAliasWithNamespace() . '".';
        
        if(!empty($missingBehaviors)) {
            $msg .= ' Missing REQUIRED behaviors: ' . json_encode($missingBehaviors) . '.';
        }

        if(!empty($conflictingBehaviors)) {
            $msg .= ' Detected CONFLICTING behaviors: ' . json_encode($conflictingBehaviors) . '.';
        }

        throw new BehaviorConfigurationError($toBehavior, $msg);
    }
}