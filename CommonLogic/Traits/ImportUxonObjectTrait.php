<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UxonSnippetFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;

trait ImportUxonObjectTrait {

    /**
     * Imports all properties from the give UXON object by calling matching setter methods of $this.
     *
     * Matching setters are methods named exactly as the property and prefixed by "set": e.g. the
     * property widget_type would map to setWidgetType().
     *
     * NOTE: snake_case methods (setWidgetType() for the example above) are supported for backwards
     * compatibility but must not be used anymore! The fallback will be removed in future versions!
     *
     * @param UxonObject $uxon            
     * @param array $skip_property_names
     * @throws UxonMapError
     * @return void
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array())
    {
        if ($this instanceof WorkbenchDependantInterface) {
            $uxon->setSnippetResolver(UxonSnippetFactory::getSnippetResolver($this->getWorkbench()));
        }
        foreach ($uxon->getPropertiesAll() as $var => $val) {
            // Skip properties listed in the skip array
            foreach ($skip_property_names as $skip_name) {
                if (strcasecmp($var, $skip_name) === 0) {
                    continue 2;
                }
            }
            
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($var);
            if (method_exists($this, $setterCamelCased)) {
                call_user_func(array(
                    $this,
                    $setterCamelCased
                ), $val);
            } else {
                throw new UxonMapError(
                    $uxon,
                    'No setter method found for UXON property "' . $var . '" in prototype "' . get_class($this) . '"!',
                    null,
                    null,
                    $var
                );
            }
        }
    }

    /**
     * Takes all properties found in a given UXON, calls them as getters on this instance and returns an array 
     * with values for all properties that pointed to a valid getter.
     * 
     * @param UxonObject $uxon
     * @return array
     * ```
     *  [
     *      "prop_name_1" => value1,
     *      // ...
     *      "prop_name_x" => valueX
     *  ] 
     * ```
     */
    protected function callGettersViaUxon(UxonObject $uxon) : array
    {
        if ($this instanceof WorkbenchDependantInterface) {
            $uxon->setSnippetResolver(UxonSnippetFactory::getSnippetResolver($this->getWorkbench()));
        }
        
        $result = [];
        
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            $getterCamelCased = 'get' . StringDataType::convertCaseUnderscoreToPascal($prop);
            if (method_exists($this, $getterCamelCased)) {
                $result[$prop] = call_user_func(array(
                    $this,
                    $getterCamelCased
                ));
            }
        }
        
        return $result;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
}
