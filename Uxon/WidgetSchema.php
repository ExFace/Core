<?php
namespace exface\Core\Uxon;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\WidgetSelector;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\Widgets\Container;
use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Widgets\Tab;

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
        try {
            $obj = $this->getMetaObject($uxon, $path);
        } catch (MetaObjectNotFoundError $e) {
            return $presets;
        }
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
                $editableWigets[$attr->getDefaultDisplayOrder() ?? '' . $attr->getAlias()] = ['attribute_alias' => $attr->getAlias()];
            }
            if (! $attr->isHidden()) {
                $visibleWidgets[$attr->getDefaultDisplayOrder() ?? '' . $attr->getAlias()] = ['attribute_alias' => $attr->getAlias()];
            }
            if ($attr->getDefaultDisplayOrder() !== null) {
                $defaultDisplayWidgets[$attr->getDefaultDisplayOrder()] = ['attribute_alias' => $attr->getAlias()];
            }
            if ($attr->isRequired()) {
                $requiredWidgets[$attr->getDefaultDisplayOrder() ?? '' . $attr->getAlias()] = ['attribute_alias' => $attr->getAlias()];
            }
        }
        ksort($editableWigets);
        $editableWigets = array_values($editableWigets);
        ksort($visibleWidgets);
        $visibleWidgets = array_values($visibleWidgets);
        ksort($defaultDisplayWidgets);
        $defaultDisplayWidgets = array_values($defaultDisplayWidgets);
        ksort($requiredWidgets);
        $requiredWidgets = array_values($requiredWidgets);
        ksort($allWidgets);
        $allWidgets = array_values($allWidgets);
        
        if (empty($allWidgets)) {
            return $presets;
        }
        
        $containerPrototype = str_replace('\\', '/', Container::class) . '.php';
        $tabPrototype = str_replace('\\', '/', Tab::class) . '.php';
        
        $presets[] = [
            'UID' => '',
            'NAME' => 'Container with all attributes',
            'PROTOTYPE__LABEL' => 'Container',
            'DESCRIPTION' => '',
            'PROTOTYPE' => $containerPrototype,
            'UXON' => (new UxonObject([
                'widgets' => $allWidgets
            ]))->toJson()
        ];
        
        if (! empty($editableWigets)) {
            $presets[] = [
                'UID' => '',
                'NAME' => 'Container with all editable attributes',
                'PROTOTYPE__LABEL' => 'Container',
                'DESCRIPTION' => '',
                'PROTOTYPE' => $containerPrototype,
                'UXON' => (new UxonObject([
                    'widgets' => $editableWigets
                ]))->toJson()
            ];
            
            $presets[] = [
                'UID' => '',
                'NAME' => 'Tab with all editable attributes',
                'PROTOTYPE__LABEL' => 'Tab',
                'DESCRIPTION' => '',
                'PROTOTYPE' => $tabPrototype,
                'UXON' => (new UxonObject([
                    'caption' => '',
                    'widgets' => $editableWigets
                ]))->toJson()
            ];
        }
        
        if (! empty($visibleWidgets)) {
            $presets[] = [
                'UID' => '',
                'NAME' => 'Container with all visible attributes',
                'PROTOTYPE__LABEL' => 'Container',
                'DESCRIPTION' => '',
                'PROTOTYPE' => $containerPrototype,
                'UXON' => (new UxonObject([
                    'widgets' => $visibleWidgets
                ]))->toJson()
            ];
        }
        
        if (! empty($defaultDisplayWidgets)) {
            $presets[] = [
                'UID' => '',
                'NAME' => 'Container with default display editable attributes',
                'PROTOTYPE__LABEL' => 'Container',
                'DESCRIPTION' => '',
                'PROTOTYPE' => $containerPrototype,
                'UXON' => (new UxonObject([
                    'widgets' => $defaultDisplayWidgets
                ]))->toJson()
            ];
        }
        
        if (! empty($requiredWidgets)) {
            $presets[] = [
                'UID' => '',
                'NAME' => 'Container with all required attributes',
                'PROTOTYPE__LABEL' => 'Container',
                'DESCRIPTION' => '',
                'PROTOTYPE' => $containerPrototype,
                'UXON' => (new UxonObject([
                    'widgets' => $requiredWidgets
                ]))->toJson()
            ];
        }
        
        $sorter = new RowDataArraySorter();
        $sorter->addCriteria('PROTOTYPE', SORT_ASC);
        $sorter->addCriteria('NAME', SORT_ASC);
        $presets = $sorter->sort($presets);
        
        return $presets;
    }
}