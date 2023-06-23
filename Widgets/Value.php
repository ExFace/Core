<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Widgets\iSupportAggregators;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Widgets\Traits\AttributeCaptionTrait;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Traits\PrefillValueTrait;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * The Value widget simply shows a raw (unformatted) value.
 *
 * The value can be set directly via "value" property (formulas and widget links are possible too!)
 * or fetched from prefill data referenced by the "attribute_alias" property.
 *
 * The Value widget will just show the raw value, optionally with a tooltip explaining it (depending
 * on the tempalte used). For formatted values (e.g. a Date in the correct locale format) use the
 * Display widget or it's derivatives. Display widgets will typically also include a title.
 *
 * To allow the user to edit the value, use Input widgets.
 *
 * @see Display
 * @see Input
 *
 * @author Andrej Kabachnik
 *
 */
class Value extends AbstractWidget implements iShowSingleAttribute, iHaveValue, iShowDataColumn, iSupportAggregators
{
    use AttributeCaptionTrait;
    
    use PrefillValueTrait;
    
    private $attribute_alias = null;
    
    private $data_type = null;
    
    private $aggregate_function = null;
    
    private $empty_text = null;
    
    private $data_column_name = null;
    
    private $value = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttributeAlias()
     */
    public function getAttributeAlias()
    {
        return $this->attribute_alias;
    }
    
    /**
     * Makes the widget show the value of the attribute specified by this alias relative to the widget's meta object.
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return \exface\Core\Widgets\Value
     */
    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
        $this->data_type = null;
        return $this;
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
        
        // FIXME how to prefill values, that were defined by a widget link???
        /*
         * if ($this->getValueExpression() && $this->getValueExpression()->isReference()){
         *  $ref_widget = $this->getValueExpression()->getWidgetLink()->getWidget();
         *  if ($ref_widget instanceof InputComboTable){
         *      $data_column = $ref_widget->getTable()->getColumn($this->getValueExpression()->getWidgetLink()->getColumnId());
         *      var_dump($data_column->getAttributeAlias());
         *  }
         * } else
         */ 
         if ($this->getValueExpression() && $this->getValueExpression()->isFormula()) {
             $prefillExpr = $this->getValueExpression();
             if ($prefillExpr !== null && ! $data_sheet->getColumns()->getByExpression($prefillExpr)) {
                 $columnName = null;
                 if ($this->isBoundToDataColumn()) {
                     $columnName = $this->getDataColumnName();
                 }
                 $data_sheet->getColumns()->addFromExpression($prefillExpr, $columnName, $this->isHidden());
             }
         } elseif ($this->isBoundToDataColumn() || $this->isBoundToAttribute()) {
             $prefillExpr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getAttributeAlias(), $this->getDataColumnName());
             if ($prefillExpr !== null && ! $data_sheet->getColumns()->getByExpression($prefillExpr)) {
                 $data_sheet->getColumns()->addFromExpression($prefillExpr, null, $this->isHidden());
             }
         }
         
         return $data_sheet;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        // Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return $data_sheet;
        }
        return $this->prepareDataSheetToRead($data_sheet);
    }
    
    /**
     * A text widget is prefillable if it does not have a value or it's value
     * is a reference (live reference formula).
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::isPrefillable()
     */
    public function isPrefillable()
    {
        if (! parent::isPrefillable()) {
            return false;
        }
        if ($this->getValueExpression() && $this->getValueExpression()->isFormula()) {
            return true;
        }
        if ($this->hasValue() === false) {
            return true;
        } else {
            $expr = $this->getValueExpression();
            switch (true) {
                case $expr->isReference():
                    return true;
            }
        }
        return false;
    }
    
    
    protected function isBoundByReference() : bool
    {
        return $this->hasValue() && $this->getValueExpression()->isReference();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        // To figure out, which attributes we need from the data sheet, we just run prepare_data_sheet_to_prefill()
        // Since an Input only needs one value, we take the first one from the returned array, fetch it from the data sheet
        // and set it as the value of our input.
        $prefill_columns = $this->prepareDataSheetToPrefill(DataSheetFactory::createFromObject($data_sheet->getMetaObject()))->getColumns();
        if (! $prefill_columns->isEmpty() && $col = $data_sheet->getColumns()->getByExpression($prefill_columns->getFirst()->getExpressionObj())) {
            $value = null;
            $valuePointer = null;
            if (count($col->getValues(false)) > 1) {
                if ($this->getAggregator()) {
                    $valuePointer = DataPointerFactory::createFromColumn($col);
                    $value = $col->aggregate($this->getAggregator());
                } 
            } else {
                $valuePointer = DataPointerFactory::createFromColumn($col, 0);
                $value = $valuePointer->getValue();
            }
            if ($valuePointer !== null) {
                // Ignore empty values because if value is a live-references the ref would get overwritten
                // even without a meaningfull prefill value
                if ($this->getValueExpression() && $this->getValueExpression()->isFormula()) {
                    $this->setValue($value, false);
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $valuePointer));
                } elseif ($this->isBoundByReference() === false || ($value !== null && $value != '')) {
                    $this->setValue($value, false);
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $valuePointer));
                }
            }
        }
        return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportAggregators::getAggregator()
     */
    public function getAggregator(): ?AggregatorInterface
    {
        if ($this->aggregate_function === null) {
            if ((Expression::detectFormula($this->getAttributeAlias()) === false) && $aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->getAttributeAlias())) {
                $this->setAggregator($aggr);
            }
        }
        return $this->aggregate_function;
    }
    
    public function hasAggregator() : bool
    {
        return $this->getAggregator() !== null;
    }
    
    /**
     * How to aggregate values if the widget receives multiple
     *
     * @uxon-property aggregator
     * @uxon-type metamodel:aggregator
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportAggregators::setAggregator()
     */
    public function setAggregator($aggregator_or_string)
    {
        if ($aggregator_or_string instanceof AggregatorInterface){
            $aggregator = $aggregator_or_string;
        } else {
            $aggregator = new Aggregator($this->getWorkbench(), $aggregator_or_string);
        }
        $this->aggregate_function = $aggregator;
        return $this;
    }
    
    /**
     * Returns TRUE if this widget references a meta attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function isBoundToAttribute() : bool
    {
        $alias = $this->getAttributeAlias();
        return $alias !== null
        && $alias !== '';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttribute()
     */
    public function getAttribute() : ?MetaAttributeInterface
    {
        if ($this->getAttributeAlias() === null || $this->getAttributeAlias() === '') {
            return null;
        }
        
        if (! $this->getMetaObject()->hasAttribute($this->getAttributeAlias())){
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $this->getAttributeAlias(), $this->getMetaObject());
            if ($expr->isFormula()) {
                return $this->getMetaObject()->getAttribute($expr->getRequiredAttributes()[0]);
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Attribute "' . $this->getAttributeAlias() . '" specified for widget ' . $this->getWidgetType() . ' not found for the widget\'s object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!');
            }
        }
        
        return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::isBoundToDataColumn()
     */
    public function isBoundToDataColumn() : bool
    {
        return $this->getDataColumnName() !== '';
    }
    
    /**
     * Returns the data type of the widget.
     *
     * The data type can either be set explicitly by UXON, or is derived from the shown meta attribute.
     * If there is neither an attribute bound to the column, nor an explicit data_type, the base data
     * type is returned.
     *
     * @return DataTypeInterface
     */
    public function getValueDataType()
    {
        if ($this->data_type === null) {
            $expr = $this->getValueExpression();
            switch (true) {
                case $expr && ! $expr->isEmpty() && ! $expr->isConstant() && ! $expr->isReference():
                    $this->data_type = $expr->getDataType();
                    break;
                case $this->isBoundToAttribute():
                    $this->data_type = ExpressionFactory::createFromString($this->getWorkbench(), $this->getAttributeAlias(), $this->getMetaObject())->getDataType();
                    break;
                case $expr && $expr->isReference():                    
                    $target = $expr->getWidgetLink($this)->getTargetWidget();
                    if ($target instanceof iHaveValue) {
                        $this->data_type = $target->getValueDataType();
                    } else {
                        $this->data_type = DataTypeFactory::createBaseDataType($this->getWorkbench());
                    }
                    break;
                case $expr:
                    $this->data_type = $expr->getDataType();
                    break;
                default:
                    $this->data_type = DataTypeFactory::createBaseDataType($this->getWorkbench());
                    break;
            }
        }
        return $this->data_type;
    }
    
    /**
     * Changes the data type of the value to one of the (MUST be defined AFTER value!)
     * 
     * CAUTION: if you set both `value_data_type` AND `value`, the former MUST be defined
     * after the `value` because setting the value will automatically recompute the data type.
     *
     * @uxon-property value_data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType|metamodel:datatype
     * @uxon-template {"alias": ""}
     *
     * @param UxonObject|DataTypeInterface|string $data_type_or_string
     * @throws WidgetConfigurationError
     * @return \exface\Core\Widgets\Value
     */
    public function setValueDataType($data_type_or_string)
    {
        switch (true) {
            case $data_type_or_string instanceof UxonObject:
                $this->data_type = DataTypeFactory::createFromUxon($this->getWorkbench(), $data_type_or_string);
                break;
            case $data_type_or_string instanceof DataTypeInterface:
                $this->data_type = $data_type_or_string;
                break;
            case is_string($data_type_or_string):
                $this->data_type = DataTypeFactory::createFromString($this->getWorkbench(), $data_type_or_string);
                break;
            default:
                throw new WidgetConfigurationError($this, 'Cannot set custom data type for widget ' . $this->getWidgetType() . ': invalid value "' . gettype($data_type_or_string) . '" given - expecting an instantiated data type or a string selector!');
        }
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        if ($this->hasValue()) {
            $uxon->setProperty('value', $this->getValueExpression()->toString());
        }
        if ($this->isBoundToAttribute()) {
            $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
        }
        if (! is_null($this->empty_text)) {
            $uxon->setProperty('empty_text', $this->empty_text);
        }
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWithDefaults()
     */
    public function getValueWithDefaults()
    {
        $expr = $this->getValueExpression();
        
        if ($expr !== null && ! $expr->isEmpty()) {
            switch (true) {
                case $expr->isReference():
                case $expr->isFormula() && ! $expr->isStatic():
                    return '';
                case $expr->isFormula() && $expr->isStatic():
                    return $expr->evaluate();
            }
        }        
        return $this->getValue();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getEmptyText()
     */
    public function getEmptyText()
    {
        if (is_null($this->empty_text)) {
            $this->empty_text = $this->translate('WIDGET.TEXT.EMPTY_TEXT');
        }
        return $this->empty_text;
    }
    
    /**
     * Defines the placeholder text to be used if the widget has no value.
     * Set to blank string to remove the placeholder.
     *
     * The default placeholder is defined by the core translation of WIDGET.TEXT.EMPTY_TEXT.
     *
     * @uxon-property empty_text
     * @uxon-type string|metamodel:formula
     * @uxon-translatable true
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setEmptyText()
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getDataColumnName()
    {
        if ($this->data_column_name === null) {
            if ($this->isBoundToAttribute()) {
                $this->data_column_name = DataColumn::sanitizeColumnName($this->getAttributeAlias());
            } elseif ($this->hasValue()) {
                $expr = $this->getValueExpression();
                if ($expr && ! $expr->isEmpty() && ! $expr->isReference() && ! ($expr->isString() && $expr->__toString() === '')) {
                    $this->data_column_name = DataColumn::sanitizeColumnName($expr->toString());
                }
            }
        }
        return $this->data_column_name ?? '';
    }
    
    /**
     * Set the name of the value data column explicitly - only needed for non-attribute widgets.
     *
     * Internally all data is stored in excel-like sheets, where every column has a unique name.
     * These data sheets are passed around between widgets, actions, data sources, etc.
     *
     * If a data sheet represents a meta object, it's columns are attributes. Column names are
     * simply attribute aliases in most cases: this is why specifying `attribute_alias` is mostly
     * enough for a value-widget.
     *
     * However, there are also cases, when the desired value does not represent an attribute: for
     * example, if you need an input for some action-parameter or a display for a calculated value.
     * If that value still needs to be handled by the server, a `data_column_name` must be set
     * explicitly, since there is no `attribute_alias`.
     *
     * @uxon-property data_column_name
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::setDataColumnName()
     */
    public function setDataColumnName($value)
    {
        $this->data_column_name = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue() : bool
    {
        return $this->getValue() === null ? false : true;
    }
    
    /**
     * Returns TRUE if the widget represents a cell in a data widget.
     *
     * This way, in-table editors and display widgets can be easily detected.
     *
     * @see Data::setCellWidget()
     *
     * @return bool
     */
    public function isInTable() : bool
    {
        // DataColumn namespace needed here because the DataSheet columns are used in this file too! 
        return $this->getParent() instanceof \exface\Core\Widgets\DataColumn;
    }
    
    /**
     * Explicitly sets the value of the widget.
     *
     * @uxon-property value
     * @uxon-type metamodel:expression
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setValue()
     */
    public function setValue($expressionOrString, bool $parseStringAsExpression = true)
    {
        if (is_string($expressionOrString) && $this->getValueDataType() instanceof EncryptedDataType && $this->getValueDataType()->isValueEncrypted($expressionOrString)) {
            $expressionOrString = EncryptedDataType::decrypt(EncryptedDataType::getSecret($this->getWorkbench()), $expressionOrString, $this->getValueDataType()->getEncryptionPrefix());
        }
        
        if ($expressionOrString instanceof ExpressionInterface) {
            $this->value = $expressionOrString;
        } else {
            // TODO #expression-syntax is still not 100% stable.
            //
            // 1) On the one hand, passing a string value should obviously result in the widget showing this
            // exact string as value - no matter if it included quotes or not.
            // 2) On the other hand, a non-quoted string would result in an Expression of UNKNOWN type, which
            // is not static, thus widgets would not show anything.
            // 3) Yet another source of values are doPrefill() calls in widgets, that, again, use non-quoted
            // static values.
            // 4) Finally, if the user sets the value in UXON, the general expression syntax suggests to use
            // quotes for strings, but these would show up in the UI, so most users omit the quotes.
            //
            // The current solution includes to control-flags: $parseStringAsExpression here and $treatUnknownAsString
            // in the constructor of the Expression:
            // - When the value is set from UXON $parseStringAsExpression=true, but $treatUnknownAsString=false
            // which leads to the possibility to use formulas and links, but unqoted strings are treated
            // as strings, not unknown expressions.
            // - When values are set in doPrefill(), $parseStringAsExpression=false forces the expression to
            // be number or string and turns off links and formulas (and unknown expressions) completely.
            //
            // An issue may arise if a widget with a value is converted to UXON and back - in this case, it
            // seams, that values starting with `=` will get treated as formulas regardless of whether it
            // was a static string previously or not. A
            if ($parseStringAsExpression === true) {
                $expr = ExpressionFactory::createFromString($this->getWorkbench(), $expressionOrString, $this->getMetaObject(), true);
            } else {
                $expr = ExpressionFactory::createAsScalar($this->getWorkbench(), $expressionOrString, $this->getMetaObject());
            }
            
            $this->value = $expr;
            // If the value is a widget link, call the getter to make sure the link is instantiated
            // thus firing OnWidgetLinkedEvent. If not done here, the event will be only fired
            // when some other code calls $expr->getWidgetLink(), which may happen too late for
            // possible event handlers!
            if ($expr->isReference()) {
                $this->getValueWidgetLink();
            }
        }
        
        // Reset cached data type to make sure it is recomputed with the new value expression.
        $this->data_type = null;
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValue()
     */
    public function getValue()
    {
        if ($expr = $this->getValueExpression()) {
            if ($expr->isStatic()) {
                return $expr->evaluate();
            } else {
                return $expr->toString();
            }
        }
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueExpression()
     */
    public function getValueExpression() : ?ExpressionInterface
    {
        return $this->value;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWidgetLink()
     */
    public function getValueWidgetLink() : ?WidgetLinkInterface
    {
        $link = null;
        $expr = $this->getValueExpression();
        if ($expr && $expr->isReference()) {
            $link = $expr->getWidgetLink($this);
        }
        return $link;
    }
}
?>