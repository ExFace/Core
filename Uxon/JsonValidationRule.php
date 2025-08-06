<?php

namespace exface\Core\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

class JsonValidationRule
{
    use ImportUxonObjectTrait;
    
    const MODE_REQUIRE = 'require';
    const MODE_PROHIBIT = 'prohibit';
    const JSON_WILDCARD = '*';
    
    private JsonDataType $jsonDataType;
    private string $alias;
    private string $mode;
    private UxonObject $uxonPattern;
    private string $messageCode;
    private bool $isCritical = false;
    
    public function __construct(
        JsonDataType $dataType, 
        string $alias,
        string $mode, 
        UxonObject $uxonPattern, 
        string $messageCode
    )
    {
        $this->jsonDataType = $dataType;
        $this->alias = $alias;
        $this->mode = $mode;
        $this->uxonPattern = $uxonPattern;
        $this->messageCode = $messageCode;
    }
    
    public function check(UxonObject $uxon, string $wildCard = self::JSON_WILDCARD) : void
    {
        $patternApplies = $this->matchesPattern($uxon, $this->uxonPattern, $wildCard);
        
        // If the rules is REQUIRED to apply and does, the check succeeds.
        if($this->mode === self::MODE_REQUIRE && $patternApplies) {
            return;
        }
        
        // If the rule is PROHIBITED and does not apply, the check succeeds.
        if($this->mode === self::MODE_PROHIBIT && !$patternApplies) {
            return;
        }
        
        // If the check failed, throw an error.
        throw new DataTypeValidationError(
            $this->jsonDataType,
            'UXON failed to pass validation rule "' . $this->alias . '".',
            $this->messageCode
        );
    }
    
    protected function matchesPattern(UxonObject $subject, UxonObject $pattern, string $wildCard) : bool
    {
        // TODO 2025-08-01 geb Use JSON Path?
        foreach ($pattern->getPropertiesAll() as $patternPropName => $patternProp) {
            // Wildcards have to be checked with OR.
            if(is_numeric($patternPropName) || $this->isWildCard($patternPropName, $wildCard)) {
                if($this->containsPattern($subject, $patternProp, $wildCard)) {
                    continue;
                } else {
                    return false;
                }
            }
            
            $matchedProp = $subject->getProperty($patternPropName);
            
            // Property not found in tested UXON, pattern does not match.
            if($matchedProp === null) {
                return false;
            }
            
            if($matchedProp instanceof UxonObject) {
                $match = $this->matchesPattern($matchedProp, $patternProp, $wildCard);
            } else {
                $match = $this->isWildCard($patternProp, $wildCard) || $patternProp === $matchedProp;
            }
            
            if(!$match){
                return false;
            }
        }
        
        return true;
    }
    
    protected function containsPattern(UxonObject $subject, UxonObject $pattern, string $wildCard) : bool
    {
        $result = false;

        foreach ($subject as $subjectPropName => $subjectProp) {
            if($subjectProp instanceof UxonObject) {
                $result = $this->matchesPattern($subjectProp, $pattern, $wildCard);
            } else {
                $patternProp = $pattern->getProperty($subjectPropName);
                if($patternProp !== null) {
                    $result = self::isWildCard($patternProp, $wildCard) || $pattern === $subjectProp;
                }
            }

            if($result) {
                break;
            }
        }
        
        return $result;
    }
    
    protected function isWildCard(mixed $property, string $wildCard) : bool
    {
        if(!is_string($property)) {
            return false;
        }
        
        $property = trim($property);
        return $property === $wildCard;
    }
}