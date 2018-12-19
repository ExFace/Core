<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\WidgetFactory;

class UxonWidgetSchema extends UxonSchema
{
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass = '\exface\Core\Widgets\AbstractWidget') : string
    {
        $name = $rootEntityClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'widget_type') === 0) {
                $name = $this->getEntityClassFromWidgetType($value);
            }
        }
        
        if (count($path) > 1) {
            return parent::getEntityClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    protected function getEntityClassFromWidgetType(string $widgetType) : string
    {
        return WidgetFactory::getWidgetClassFromType($widgetType);
    }
}