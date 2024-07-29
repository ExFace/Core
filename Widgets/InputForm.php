<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\Model\Expression;

/**
 * Configurable form to collect structured data and save it in singe attribute
 * 
 * @author Andrej Kabachnik
 *
 */
class InputForm extends InputFormDesigner
{
    private $formConfigAttributeAlias = null;
    
    private $formConfigValue = null;
    
    private $formConfigExpr = null;
    
    private $formWidgets = [];
    
    /**
     * 
     * @return string
     */
    public function getFormConfigAttributeAlias() : ?string
    {
        return $this->formConfigAttributeAlias;
    }
    
    /**
     * 
     * @return bool
     */
    public function isFormConfigBoundToAttribute() : bool
    {
        return $this->formConfigAttributeAlias !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function isFormConfigBoundByReference() : bool
    {
        return ! $this->isFormConfigBoundToAttribute() && $this->getFormConfigExpression() && $this->getFormConfigExpression()->isReference();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getFormConfigDataColumnName()
    {
        return $this->isFormConfigBoundToAttribute() ? DataColumn::sanitizeColumnName($this->getFormConfigAttributeAlias()) : $this->getDataColumnName();
    }
    
    /**
     * Alias of the attribute containing the configuration for the form to be rendered
     *
     * @uxon-property form_config_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return InputForm
     */
    public function setFormConfigAttributeAlias(string $value) : InputForm
    {
        $this->formConfigAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFormConfig() : ?string
    {
        return $this->formConfigValue;
    }
    
    /**
     * 
     * @return ExpressionInterface|NULL
     */
    public function getFormConfigExpression() : ?ExpressionInterface
    {
        if ($this->formConfigExpr === null) {
            if ($this->isFormConfigBoundToAttribute()) {
                $this->formConfigExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->getFormConfigAttributeAlias());
            }
            if ($this->formConfigValue !== null && Expression::detectCalculation($this->formConfigValue)) {
                $this->formConfigExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->formConfigValue);
            }
        }
        return $this->formConfigExpr;
    }
    
    /**
     * Widget link or static value for the form configuration
     * 
     * @uxon-property form_config
     * @uxon-type metamodel:widget_link|string
     * 
     * @param string $value
     * @return InputForm
     */
    public function setFormConfig(string $value) : InputForm
    {
        $this->formConfigValue = $value;
        $this->formConfigExpr = null;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        if ($this->isFormConfigBoundToAttribute() === true) {
            $formConfigPrefillExpr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getFormConfigAttributeAlias());
            if ($formConfigPrefillExpr!== null) {
                $data_sheet->getColumns()->addFromExpression($formConfigPrefillExpr);
            }
        }
        return $data_sheet;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        if ($this->isFormConfigBoundToAttribute() === true) {
            $formConfigPrefillExpression = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getFormConfigAttributeAlias());
            if ($formConfigPrefillExpression !== null && $col = $data_sheet->getColumns()->getByExpression($formConfigPrefillExpression)) {
                if (count($col->getValues(false)) > 1 && $this->getAggregator()) {
                    // TODO #OnPrefillChangeProperty
                    $valuePointer = DataPointerFactory::createFromColumn($col);
                    $value = $col->aggregate($this->getAggregator());
                } else {
                    $valuePointer = DataPointerFactory::createFromColumn($col, 0);
                    $value = $valuePointer->getValue();
                }
                // Ignore empty values because if value is a live-reference, the ref address would get overwritten
                // even without a meaningfull prefill value
                if ($this->isFormConfigBoundByReference() === false || ($value !== null && $value != '')) {
                    $this->setFormConfig($value ?? '');
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'form_config', $valuePointer));
                }
            }
        }
        return;
    }
    
    /**
     * 
     * @return WidgetInterface[]
     */
    public function getFormWidgets() : array
    {
        return $this->formWidgets;
    }
    
    /**
     * 
     * @param WidgetInterface $widget
     * @return InputForm
     */
    protected function addFormWidget(WidgetInterface $widget) : InputForm
    {
        $this->formWidgets = $widget;
        return $this;
    }
    
    /**
     * Custom widgets like InputSelect or InputComboTable, that should be available as reusable form elements
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"widget_type": ""}]
     *
     * @param WidgetInterface[]|UxonObject $widgetOrUxonArray
     * @return InputForm
     */
    public function setFormWidgets($widgetOrUxonArray) : InputForm
    {
        foreach ($widgetOrUxonArray as $w) {
            if ($w instanceof WidgetInterface) {
                $this->addFormWidget($w);
            } else {
                $widget = WidgetFactory::createFromUxonInParent($this, $w);
                $this->addFormWidget($widget);
            }
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach ($this->getFormWidgets() as $child) {
            yield $child;
        }
    }
}