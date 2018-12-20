<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\DataSheetFactory;

/**
 * UXON-schema class for widgets.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonWidgetSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\UxonSchema::getEntityClass()
     */
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass = '\exface\Core\Widgets\AbstractWidget') : string
    {
        $name = $rootEntityClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'widget_type') === 0) {
                $w = $this->getEntityClassFromWidgetType($value);
                if ($this->validateEntityClass($w) === true) {
                    $name = $w;
                }
            }
        }
        
        if (count($path) > 1) {
            return parent::getEntityClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * 
     * @param string $widgetType
     * @return string
     */
    protected function getEntityClassFromWidgetType(string $widgetType) : string
    {
        return WidgetFactory::getWidgetClassFromType($widgetType);
    }
}