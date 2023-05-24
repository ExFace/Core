<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\WidgetSelector;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\Widgets\Container;

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
     * @return string
     */
    public static function getSchemaName() : string
    {
        return UxonSchemaNameDataType::WIDGET;
    }
    
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractWidget::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPresets()
     */
    public function getPresets(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $presets = parent::getPresets($uxon, $path, $rootPrototypeClass);
        $obj = $this->getMetaObject($uxon, $path);
        if ($obj === null) {
            return $presets;
        }
        
        $editableWigets = [];
        $visibleWidgets = [];
        $defaultDisplayWidgets = [];
        $requiredWidgets = [];
        $allWidgets = [];
        foreach ($obj->getAttributes() as $attr) {
            $allWidgets[] = ['attribute_alias' => $attr->getAlias()];
            if ($attr->isEditable()) {
                $editableWigets[] = ['attribute_alias' => $attr->getAlias()];
            }
            if (! $attr->isHidden()) {
                $visibleWidgets[] = ['attribute_alias' => $attr->getAlias()];
            }
            if ($attr->getDefaultDisplayOrder() !== null) {
                $defaultDisplayWidgets[] = ['attribute_alias' => $attr->getAlias()];
            }
            if ($attr->isRequired()) {
                $requiredWidgets[] = ['attribute_alias' => $attr->getAlias()];
            }
        }
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with all attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => Container::class,
            'UXON' => (new UxonObject([
                'widgets' => $allWidgets
            ]))->toJson()
        ];
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with all editable attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => Container::class,
            'UXON' => (new UxonObject([
                'widgets' => $editableWigets
            ]))->toJson()
        ];
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with all visible attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => Container::class,
            'UXON' => (new UxonObject([
                'widgets' => $visibleWidgets
            ]))->toJson()
        ];
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with default display editable attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => Container::class,
            'UXON' => (new UxonObject([
                'widgets' => $defaultDisplayWidgets
            ]))->toJson()
        ];
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with all required attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => Container::class,
            'UXON' => (new UxonObject([
                'widgets' => $requiredWidgets
            ]))->toJson()
        ];
        
        return $presets;
    }
}