<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\CommonLogic\Model\Expression;

/**
 * The DiffText widget compares two texts and shows a report highlighting the changes.
 * 
 * The base ("old") text is defined by `attribute_alias` or `value` just like in any other value widget,
 * and the text to compare to (the "new" text) is set by `attribute_alias_to_compare` or `value_to_compare`
 * respectively.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DiffText extends Value
{
    private $compareToAttributeAlias = null;

    private $compareToValue = null;
    
    private $comparetToExpr = null;
    
    /**
     *
     * @return string
     */
    public function getAttributeAliasToCompare() : ?string
    {
        return $this->compareToAttributeAlias;
    }
    
    /**
     *
     * @return bool
     */
    public function isValueToCompareBoundToAttribute() : bool
    {
        return $this->compareToAttributeAlias !== null;
    }
    
    /**
     *
     * @return bool
     */
    public function isValueToCompareBoundByReference() : bool
    {
        return ! $this->isValueToCompareBoundToAttribute() && $this->getValueToCompareExpression() && $this->getValueToCompareExpression()->isReference();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getValueToCompareDataColumnName()
    {
        return $this->isValueToCompareBoundToAttribute() ? DataColumn::sanitizeColumnName($this->getAttributeAliasToCompare()) : $this->getDataColumnName();
    }
    
    /**
     * Alias of the attribute containing the configuration for the form to be rendered
     *
     * @uxon-property attribute_alias_to_compare
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return DiffText
     */
    public function setAttributeAliasToCompare(string $value) : DiffText
    {
        $this->compareToAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getValueToCompare() : ?string
    {
        return $this->compareToValue;
    }
    
    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getValueToCompareExpression() : ?ExpressionInterface
    {
        if ($this->compareToExpr === null) {
            if ($this->isValueToCompareBoundToAttribute()) {
                $this->compareToExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->getAttributeAliasToCompare());
            }
            if ($this->compareToValue !== null && Expression::detectCalculation($this->compareToValue)) {
                $this->compareToExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->compareToValue);
            }
        }
        return $this->compareToExpr;
    }
    
    /**
     * Widget link or static value for the form configuration
     *
     * @uxon-property value_to_compare
     * @uxon-type metamodel:widget_link|string
     *
     * @param string $value
     * @return DiffText
     */
    public function setValueToCompare(string $value) : DiffText
    {
        $this->compareToValue = $value;
        $this->compareToExpr = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill($data_sheet)
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        
        if ($this->isValueToCompareBoundToAttribute() === true) {
            $expr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getAttributeAliasToCompare());
            if ($expr !== null && $col = $data_sheet->getColumns()->getByExpression($expr)) {
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
                if ($this->isValueToCompareBoundByReference() === false || ($value !== null && $value != '')) {
                    $this->setValueToCompare($value ?? '');
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_to_compare', $valuePointer));
                }
            }
        }
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        if ($this->isValueToCompareBoundToAttribute() === true) {
            $expr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getAttributeAliasToCompare());
            if ($expr!== null) {
                $data_sheet->getColumns()->addFromExpression($expr);
            }
        }
        
        return $data_sheet;
    }
    /*
    public function getLeftValue()
    {
        return $this->left_value;
    }
    
    public function setLeftValue($value)
    {
        $this->left_value = $value;
        return $this;
    }
    
    public function getRightValue()
    {
        return $this->right_value;
    }
    
    public function setRightValue($value)
    {
        $this->right_value = $value;
        return $this;
    }
    
    protected function setLeftAttributeAlias($value)
    {
        return $this->setAttributeAlias($value);
    }
    
    protected function setRightAttributeAlias($value)
    {
        return $this->setAttributeAliasToCompare($value);
    }
    */
}