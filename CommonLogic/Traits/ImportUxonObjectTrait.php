<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\DataTypes\StringDataType;

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
     * @param array $skip_properties            
     * @throws UxonMapError
     * @return void
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array())
    {
        if (! $uxon)
            return;
        foreach ($uxon->getPropertiesAll() as $var => $val) {
            // Skip properties listed in the skip array
            $skip = false;
            foreach ($skip_property_names as $skip_name) {
                if (strcasecmp($var, $skip_name) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip)
                continue;
            
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($var);
            if (method_exists($this, $setterCamelCased)) {
                call_user_func(array(
                    $this,
                    $setterCamelCased
                ), $val);
            } elseif (method_exists($this, 'set_' . $var)) {
                call_user_func(array(
                    $this,
                    'set_' . $var
                ), $val);
            } else {
                throw new UxonMapError($uxon, 'No setter method found for UXON property "' . $var . '" in "' . get_class($this) . '"!');
            }
        }
        return;
    }
}
