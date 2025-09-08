<?php

namespace exface\Core\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use JsonPath\JsonObject;

/**
 * PREVIEW
 */
class JsonValidationRule
{
    use ImportUxonObjectTrait;
    
    const JSON_PATH_SPLIT = '/\.(?![^\[\]]*\])(?=(?:[^\(\)]*\([^\(\)]*\))*[^\(\)]*$)/';
    const MODE_REQUIRE = 'require';
    const MODE_PROHIBIT = 'prohibit';
    
    private JsonDataType $jsonDataType;
    private string $alias;
    private string $mode;
    private array $jsonPaths = [];
    private bool $isCritical = false;
    private string $message;

    public function __construct(
        JsonDataType $dataType, 
        string $alias = '',
        string $mode = JsonValidationRule::MODE_PROHIBIT, 
        array $jsonPaths = [], 
        string $message = ''
    )
    {
        $this->jsonDataType = $dataType;
        $this->alias = $alias;
        $this->mode = $mode;
        $this->jsonPaths = $jsonPaths;
        $this->message = $message;
    }
    
    public static function fromUxon(JsonDataType $dataType, UxonObject $uxon) : JsonValidationRule
    {
        $rule = new JsonValidationRule($dataType);
        $rule->importUxonObject($uxon);
        
        return $rule;
    }
    
    public function check(UxonObject $uxon) : void
    {
        // TODO component wise, to retrieve path
        $matches = $this->findMatches($uxon);
        $patternApplies = !empty($matches);
        
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
            'UXON failed to pass validation rule "' . $this->alias . '". ' . $this->message,
        );
    }
    
    protected function findMatches(UxonObject $uxon, bool $stopOnHit = false) : array
    {
        $data = $uxon->toArray();
        $jsonObject = new JsonObject($data);
        $results = [];

        // TODO Traverse splits instead
        foreach ($this->jsonPaths as $path) {
            try {
                $matches = $jsonObject->getJsonObjects($path);
                //$jsonObject->set($path, 'TEST');
            } catch (\Throwable $exception) {
                continue;
            }

            // If this path did not produce any matches, the rule as a whole does not apply.
            if(!$matches) {
                $results = [];
                break;
            }

            $results = array_merge($results, $matches);
        }
        
        return $results;
    }
    
    public function getMessage() : string
    {
        return $this->message;
    }
    
    public function setMessage(string $message) : JsonValidationRule
    {
        $this->message = $message;
        return $this;
    }
    
    public function setAlias(string $alias) : JsonValidationRule
    {
        $this->alias = $alias;
        return $this;
    }
    
    public function setMode(string $mode) : JsonValidationRule
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * @uxon-property json_paths
     * @uxon-type string[]
     *
     * @param UxonObject $jsonPaths
     * @return $this
     */
    public function setJsonPaths(UxonObject $jsonPaths) : JsonValidationRule
    {
        // TODO Assign splits
        foreach ($jsonPaths->toArray() as $jsonPath) {
            $split = $this->splitJsonPath($jsonPath);
        }
        
        $this->jsonPaths = $jsonPaths->toArray();
        return $this;
    }
    
    protected function splitJsonPath(string $jsonPath) : array
    {
        $result = [];
        
        $buildRecursion = false;
        foreach (preg_split(self::JSON_PATH_SPLIT, $jsonPath) as $component) {
            if($component === '$') {
                continue;
            }
            
            if($component === '') {
                $buildRecursion = true;
                continue;
            }
            
            $root = '$.';
            
            if($buildRecursion) {
                $root = '~' . $root;
                $buildRecursion = false;
            }
            
            $result[] = $root . $component;
        }
        
        if($buildRecursion) {
            $result[] = '$..*';
        }
        
        return  $result;
    }
}