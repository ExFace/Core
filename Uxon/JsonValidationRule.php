<?php

namespace exface\Core\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Exceptions\InvalidArgumentException;
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
    const PATTERN_IS_RECURSIVE = '#';
    
    private JsonDataType $jsonDataType;
    private string $alias;
    private string $mode;
    private array $jsonPaths = [];
    private bool $isCritical = false;
    private string $message;
    private string $appliesToClass;

    public function __construct(
        JsonDataType $dataType, 
        string       $appliesToClass,
        string       $alias = '',
        string       $mode = JsonValidationRule::MODE_PROHIBIT, 
        array        $jsonPaths = [], 
        string       $message = ''
    )
    {
        if(!class_exists($appliesToClass)) {
            throw new InvalidArgumentException('Invalid value for "applies_to": ' . $appliesToClass . '" is not a valid class!');
        }

        $this->appliesToClass = $appliesToClass;
        $this->jsonDataType = $dataType;
        $this->alias = $alias;
        $this->mode = $mode;
        $this->jsonPaths = $jsonPaths;
        $this->message = $message;
    }
    
    public static function fromUxon(JsonDataType $dataType, string $appliesToClass, UxonObject $uxon) : JsonValidationRule
    {
        $rule = new JsonValidationRule($dataType, $appliesToClass);
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
        throw new UxonValidationError(
            $matches,
            'UXON failed to pass validation rule "' . $this->alias . '". ' . $this->message,
        );
    }
    
    protected function findMatches(UxonObject $uxon) : array
    {
        $results = [];

        foreach ($this->jsonPaths as $path) {
            $results = array_merge($results, $this->traverseJsonPath($path, $uxon->toArray(), []));
        }
        
        return $results;
    }
    
    // TODO This is probably expensive. Maybe try to merge related paths.
    protected function traverseJsonPath(array $jsonPath, array $data, array $uxonPath) : array
    {
        $jsonObject = new JsonObject($data);
        
        $recursionPath = $jsonPath;
        $pattern = array_shift($jsonPath);
        $patternIsRecursive = str_starts_with($pattern, self::PATTERN_IS_RECURSIVE);
        if($patternIsRecursive) {
            $pattern = substr($pattern, 1);
        }

        try {
            $matchTag = $this->getMatchTag($data);
            if(!empty($jsonObject->get($pattern))) {
                // Mark all matches with the matched tag.
                $matches = ($jsonObject->set($pattern, $matchTag))->getValue();
            } elseif ($patternIsRecursive) {
                $matches = $data;
            } else {
                return [];
            }
        } catch (\Throwable $exception) {
            // We treat any exception here as a miss.
            return [];
        }

        return $this->processQueryResults(
            $jsonPath, 
            $data, 
            $matchTag, 
            $matches, 
            $uxonPath, 
            $patternIsRecursive,
            $recursionPath
        );
    }
    
    protected function processQueryResults(
        array $jsonPath, 
        array $data, 
        string $matchTag, 
        array $queryResult,
        array $uxonPath,
        bool $patternIsRecursive,
        array $recursionPath,
        bool $final = false
    ) : array
    {
        $results = [];
        $isDestination = empty($jsonPath);

        foreach ($queryResult as $key => $value) {
            $propValue = $data[$key];
            $isArray = is_array($propValue);
            $path = $uxonPath;
            $path[] = $key;

            // If we hit a match, process it.
            if($value === $matchTag) {
                if($isDestination) {
                    $results[] = $path;
                    continue;
                }

                if($isArray) {
                    $results = array_merge($results, $this->traverseJsonPath($jsonPath, $propValue, $path));
                }
                
                continue;
            }

            // We are now processing a miss.
            // If a miss is not an array, we move on.
            if(!$isArray) {
                continue;
            }

            if($final) {
                continue;
            }

            // Search one layer deeper, because filter queries can match the children of an object.
            $nestedResults = $this->processQueryResults(
                $jsonPath,
                $data,
                $matchTag,
                $value,
                $path,
                false,
                $recursionPath,
                true
            );

            $results = array_merge(
                $results,
                $nestedResults
            );
            
            // Lastly, if the pattern is recursive, we have to go deeper either way.
            if($patternIsRecursive) {
                $results = array_merge(
                    $results,
                    $this->traverseJsonPath($recursionPath, $propValue, $path)
                );
            }
        }

        return $results;
    }
    
    protected function getMatchTag(array $data) : string
    {
        $matchedTag = '~/MATCHED';
        
        foreach ($data as $value) {
            if($matchedTag === $value) {
                $matchedTag . random_int(1000, 9999);
            }
        }
        
        return $matchedTag;
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
        $this->jsonPaths = [];
        
        foreach ($jsonPaths->toArray() as $jsonPath) {
            $this->jsonPaths[] = $this->splitJsonPath($jsonPath);
        }
        
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
                $root = self::PATTERN_IS_RECURSIVE . $root;
                $buildRecursion = false;
            }
            
            $result[] = $root . $component;
        }
        
        return  $result;
    }

    public function getAppliesToClass() : string
    {
        return $this->appliesToClass;
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
}