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
     * @param array      $skip_property_names
     *
     * @return void
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array())
    {
        $this->importUxonObjectWithOrder($uxon, $skip_property_names);
    }

    /**
     * Same as importUxonObject(), but allows to force certain properties to be set first or last.
     * 
     * @param UxonObject $uxon
     * @param array      $skip_property_names
     * @param array      $propertiesToSetFirst
     * A list of property names that you wish to set BEFORE all other properties.
     * @param array      $propertiesToSetLast
     * A list of property names that you wish to set AFTER all other properties.
     * 
     * @return void
     */
    public function importUxonObjectWithOrder(
        UxonObject $uxon,
        array $skip_property_names = array(),
        array $propertiesToSetFirst = [],
        array $propertiesToSetLast = []
    )
    {
        if ($this instanceof WorkbenchDependantInterface) {
            $uxon->setSnippetResolver(UxonSnippetFactory::getSnippetResolver($this->getWorkbench()));
        }
        
        $propsRegular = [];
        $propsLast = [];
        
        foreach ($uxon->getPropertiesAll() as $var => $val) {
            // Skip properties listed in the skip array.
            foreach ($skip_property_names as $propName) {
                if (strcasecmp($var, $propName) === 0) {
                    continue 2;
                }
            }
            
            // Set early properties immediately.
            foreach ($propertiesToSetFirst as $propName) {
                if (strcasecmp($var, $propName) === 0) {
                    $this->setProperty($var, $val, $uxon);
                    continue 2;
                }
            }

            // Mark late properties.
            foreach ($propertiesToSetLast as $propName) {
                if (strcasecmp($var, $propName) === 0) {
                    $propsLast[$var] = $val;
                    continue 2;
                }
            }
            
            // If its neither early, nor late, mark it as regular.
            $propsRegular[$var] = $val;
        }
        
        // Set regular properties.
        foreach ($propsRegular as $var => $val) {
            $this->setProperty($var, $val, $uxon);
        }

        // Set late properties.
        foreach ($propsLast as $var => $val) {
            $this->setProperty($var, $val, $uxon);
        }
    }
    
    private function setProperty(string $propertyName, mixed $value, UxonObject $uxon)
    {
        $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($propertyName);
        if (method_exists($this, $setterCamelCased)) {
            call_user_func(array(
                $this,
                $setterCamelCased
            ), $value);
        } else {
            throw new UxonMapError(
                $uxon,
                'No setter method found for UXON property "' . $propertyName . '" in prototype "' . get_class($this) . '"!',
                null,
                null,
                $value
            );
        }
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