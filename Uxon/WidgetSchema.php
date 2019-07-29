<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\WidgetSelector;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\AbstractWidget;

/**
 * UXON-schema class for widgets.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $name = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'widget_type') === 0) {
                $selector = new WidgetSelector($this->getWorkbench(), $value);
                if ($selector->isCoreWidget() === true) {
                    // This is faster, than instantiating a page and a widget,
                    // but it only works for core widgets!
                    $w = $this->getPrototypeClassFromWidgetType($value);
                } else {
                    $w = get_class(WidgetFactory::create(UiPageFactory::createEmpty($this->getWorkbench()), $value));
                }
                if ($this->validatePrototypeClass($w) === true) {
                    $name = $w;
                }
                break;
            }
        }
        
        if (count($path) > 1) {
            return parent::getPrototypeClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * 
     * @param string $widgetType
     * @return string
     */
    protected function getPrototypeClassFromWidgetType(string $widgetType) : string
    {
        return WidgetFactory::getWidgetClassFromType($widgetType);
    }
    
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractWidget::class;
    }
}