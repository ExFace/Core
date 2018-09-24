<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Widgets\iSupportAggregators;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\DataTypes\RelationTypeDataType;

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
    private $attribute_alias = null;

    private $data_type = null;

    private $aggregate_function = null;

    private $empty_text = null;
    
    private $data_column_name = null;
    
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
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Value
     */
    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
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
        $widget_object = $this->getMetaObject();
        $prefill_object = $data_sheet->getMetaObject();
        
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
         
        // See if we are prefilling with the same object as the widget is based
        // on (or a derivative). E.g. if we are prefilling a widget based on FILE, 
        // we can use FILE and PDF_FILE objects as both are "files", while a
        // widget based on PDF_FILE cannot be prefilled with simply FILE.
        // If it's a different object, than try to find some relation wetween them.
        if ($prefill_object->is($widget_object)) {
            // If we are looking for attributes of the object of this widget, then just return the attribute_alias
            $data_sheet->getColumns()->addFromExpression($this->getAttributeAlias());
        } else {
            // If not, we are dealing with a prefill with data of another object. It only makes sense to try to prefill here,
            // if the widgets shows an attribute, because then we have a chance to find a relation between the widget's object
            // and the prefill object
            if ($this->getAttribute()) {
                // If the widget shows an attribute with a relation path, try to rebase that attribute relative to the
                // prefill object (this is possible, if the prefill object sits somewhere along the relation path. So,
                // traverse up this path to see if it includes the prefill object. If so, add a column to the prefill
                // sheet, that contains the widget's attribute with a relation path relative to the prefill object.
                if ($rel_path = $this->getAttribute()->getRelationPath()->toString()) {
                    $rel_parts = RelationPath::relationPathParse($rel_path);
                    if (is_array($rel_parts)) {
                        $related_obj = $widget_object;
                        foreach ($rel_parts as $rel_nr => $rel_part) {
                            $related_obj = $related_obj->getRelatedObject($rel_part);
                            unset($rel_parts[$rel_nr]);
                            if ($related_obj->isExactly($prefill_object)) {
                                $attr_path = implode(RelationPath::getRelationSeparator(), $rel_parts);
                                $attr = RelationPath::relationPathAdd($attr_path, $this->getAttribute()->getAlias());
                                $data_sheet->getColumns()->addFromExpression($attr);
                            }
                        }
                    }
                    // If the prefill object is not in the widget's relation path, try to find a relation from this widget's
                    // object to the data sheet object and vice versa
                    
                } elseif ($this->getAttribute()->isRelation() && $prefill_object->is($this->getAttribute()->getRelation()->getRightObject())) {
                    // If this widget represents the relation from the sheet object to the prefill object, the prefill value would be the 
                    // right key of the relation (e.g. trying to prefill the order positions attribute "ORDER" relative to the object
                    // "ORDER" should result in the attribute UID of ORDER because it is the right key and must have a value matching the 
                    // left key). 
                    $data_sheet->getColumns()->addFromAttribute($this->getAttribute()->getRelation()->getRightKeyAttribute(true));
                } else {
                    // If the attribute is not a relation itself, we still can use it for prefills if we find a relation to access
                    // it from the $data_sheet's object. In order to do this, we need to find relations from the prefill object to
                    // the object of this widget. However, it does not make sense to use reverse relations because the corresponding
                    // values would need to get aggregated in the prefill sheet in most cases and we don't have a meaningfull
                    // aggregator at hand at this time. Direct (not inherited) relations should be preffered. That is, a relation from
                    // the prefill object to an object, this widget's object extends, can still be used in most cases, but a direct
                    // relation is safer. Not sure, if inherited relations will work if the extending object has a different data address...
                    
                    // Iterate over all forward relations
                    $inherited_rel = null;
                    $direct_rel = null;
                    foreach ($prefill_object->findRelations($widget_object->getId(), RelationTypeDataType::REGULAR) as $rel) {
                        if ($rel->isInherited() && ! $inherited_rel) {
                            // Remember the first inherited relation in case there will be no direct relations
                            $inherited_rel = $rel;
                        } else {
                            // Break on the first direct relation
                            $direct_rel = $rel;
                        }
                    }
                    // If there is no direct relation, but an inherited one, use the latter
                    if (! $direct_rel && $inherited_rel) {
                        $direct_rel = $inherited_rel;
                    }
                    // If we found a relation to use, add the attribute prefixed with it's relation path to the data sheet
                    if ($direct_rel) {
                        $rel_path = RelationPath::relationPathAdd($rel->getAlias(), $this->getAttribute()->getAlias());
                        if ($prefill_object->hasAttribute($rel_path)) {
                            $data_sheet->getColumns()->addFromAttribute($prefill_object->getAttribute($rel_path));
                        }
                    }
                }
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
        return parent::isPrefillable() && ! ($this->hasValue() && ! $this->getValueExpression()->isReference());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill()
     */
    protected function doPrefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        // To figure out, which attributes we need from the data sheet, we just run prepare_data_sheet_to_prefill()
        // Since an Input only needs one value, we take the first one from the returned array, fetch it from the data sheet
        // and set it as the value of our input.
        $prefill_columns = $this->prepareDataSheetToPrefill(DataSheetFactory::createFromObject($data_sheet->getMetaObject()))->getColumns();
        if ($col = $prefill_columns->getFirst()) {
            if (count($data_sheet->getColumnValues($col->getName(false))) > 1 && $this->getAggregator()) {
                $value = \exface\Core\CommonLogic\DataSheets\DataColumn::aggregateValues($data_sheet->getColumnValues($col->getName(false)), $this->getAggregator());
            } else {
                $value = $data_sheet->getCellValue($col->getName(), 0);
            }
            // Ignore empty values because otherwise live-references would get overwritten even without a meaningfull prefill value
            if (! is_null($value) && $value != '') {
                $this->setValue($value);
            }
        }
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportAggregators::getAggregator()
     */
    public function getAggregator()
    {
        if (is_null($this->aggregate_function)) {
            if ($aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->getAttributeAlias())) {
                $this->setAggregator($aggr);
            }
        }
        return $this->aggregate_function;
    }
    
    public function hasAggregator()
    {
        return is_null($this->getAggregator()) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        if (! parent::getCaption()) {
            if ($this->hasAttributeReference()) {
                $this->setCaption($this->getAttribute()->getName());
            }
        }
        return parent::getCaption();
    }
    
    /**
     * Returns TRUE if this widget references a meta attribute and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasAttributeReference()
    {
        return $this->getAttributeAlias() ? true : false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttribute()
     */
    public function getAttribute()
    {
        if (! $this->getAttributeAlias()) {
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
        if (is_null($this->data_type)) {
            $expr = $this->getValueExpression();
            if (! $expr || $expr->isEmpty() || ($expr->isConstant() && $this->hasAttributeReference())) {
                $expr = ExpressionFactory::createFromString($this->getWorkbench(), $this->getAttributeAlias(), $this->getMetaObject());
            }
            $this->data_type = $expr->getDataType();
        }
        return $this->data_type;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setDataType()
     */
    public function setValueDataType($data_type_or_string)
    {
        if ($data_type_or_string instanceof DataTypeInterface) {
            $this->data_type = $data_type_or_string;
        } elseif (is_string($data_type_or_string)) {
            $this->data_type = DataTypeFactory::createFromString($this->getWorkbench(), $data_type_or_string);
        } else {
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
        if ($this->hasAttributeReference()) {
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
        if ($this->getValueExpression() && $this->getValueExpression()->isReference()) {
            $value = '';
        } else {
            $value = $this->getValue();
        }
        if (is_null($value) || $value === '') {
            $value = $this->getDefaultValue();
        }
        return $value;
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
     * @uxon-type boolean
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
        if (is_null($this->data_column_name)) {
            $this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getAttributeAlias());
        }
        return $this->data_column_name;
    }
    
    /**
     * 
     * {@inheritDoc}
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
    public function hasValue()
    {
        return is_null($this->getValue()) ? false : true;
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
        return $this->getParent() instanceof DataColumn;
    }
}
?>