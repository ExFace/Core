<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;

/**
 * The text widget simply shows text with an optional title created from the caption of the widget
 *
 * @author Andrej Kabachnik
 *        
 */
class Text extends AbstractWidget implements iShowSingleAttribute, iHaveValue, iShowText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    private $text = NULL;

    private $attribute_alias = null;

    private $data_type = null;

    private $size = null;

    private $style = null;

    private $aggregate_function = null;

    private $empty_text = null;

    public function getText()
    {
        if (is_null($this->text)) {
            return $this->getValue();
        }
        return $this->text;
    }

    public function setText($value)
    {
        $this->text = $value;
        return $this;
    }

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
         *  if ($ref_widget instanceof ComboTable){
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
                                $attr_path = implode(RelationPath::RELATION_SEPARATOR, $rel_parts);
                                $attr = RelationPath::relationPathAdd($attr_path, $this->getAttribute()->getAlias());
                                $data_sheet->getColumns()->addFromExpression($attr);
                            }
                        }
                    }
                    // If the prefill object is not in the widget's relation path, try to find a relation from this widget's
                    // object to the data sheet object and vice versa
                } elseif ($this->getAttribute()->isRelation() && $prefill_object->is($this->getAttribute()->getRelation()->getRelatedObject())) {
                    // If this widget represents the direct relation attribute, the attribute to display would be the UID of the
                    // of the related object (e.g. trying to fill the order positions attribute "ORDER" relative to the object
                    // "ORDER" should result in the attribute UID of ORDER because it holds the same value)
                        
                    $data_sheet->getColumns()->addFromExpression($this->getAttribute()->getRelation()->getRelatedObjectKeyAlias());
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
                    foreach ($prefill_object->findRelations($widget_object->getId(), Relation::RELATION_TYPE_FORWARD) as $rel) {
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
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
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
    protected function isPrefillable()
    {
        return ! ($this->getValue() && ! $this->getValueExpression()->isReference());
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
            if (count($data_sheet->getColumnValues($col->getName(false))) > 1 && $this->getAggregateFunction()) {
                $value = \exface\Core\CommonLogic\DataSheets\DataColumn::aggregateValues($data_sheet->getColumnValues($col->getName(false)), $this->getAggregateFunction());
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

    public function getAggregateFunction()
    {
        return $this->aggregate_function;
    }

    public function setAggregateFunction($value)
    {
        $this->aggregate_function = $value;
        return $this;
    }

    public function getCaption()
    {
        if (! parent::getCaption()) {
            if ($attr = $this->getAttribute()) {
                $this->setCaption($attr->getName());
            }
        }
        return parent::getCaption();
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
            throw new WidgetPropertyInvalidValueError($this, 'Attribute "' . $this->getAttributeAlias() . '" specified for Text widget not found for the widget\'s object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!');
        }
        
        return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::getSize()
     */
    public function getSize()
    {
        return $this->size;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::setSize()
     */
    public function setSize($value)
    {
        $this->size = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::getStyle()
     */
    public function getStyle()
    {
        return $this->style;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::setStyle()
     */
    public function setStyle($value)
    {
        $this->style = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::getAlign()
     */
    public function getAlign()
    {
        if (! $this->isAlignSet()) {
            if ($this->getDataType()->is(EXF_DATA_TYPE_NUMBER) || $this->getDataType()->is(EXF_DATA_TYPE_PRICE)) {
                $this->setAlign(EXF_ALIGN_OPPOSITE);
            } elseif ($this->getDataType()->is(EXF_DATA_TYPE_BOOLEAN)) {
                $this->setAlign(EXF_ALIGN_CENTER);
            } else {
                $this->setAlign(EXF_ALIGN_DEFAULT);
            }
        }
        return $this->getAlignDefault();
    }

    /**
     * Returns the data type of the column as a constant (e.g.
     * EXF_DATA_TYPE_NUMBER). The column's
     * data_type can either be set explicitly by UXON, or is derived from the shown meta attribute.
     * If there is neither an attribute bound to the column, nor an explicit data_type EXF_DATA_TYPE_STRING
     * is returned.
     *
     * @return AbstractDataType
     */
    public function getDataType()
    {
        if ($this->data_type) {
            return $this->data_type;
        } elseif ($attr = $this->getAttribute()) {
            return $attr->getDataType();
        } else {
            $exface = $this->getWorkbench();
            return DataTypeFactory::createFromAlias($exface, EXF_DATA_TYPE_STRING);
        }
    }

    /**
     * Returns the placeholder text to be used by templates if the widget has no value.
     *
     * @return string
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
     * @param string $value            
     * @return \exface\Core\Widgets\Text
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (! is_null($this->empty_text)) {
            $uxon->setProperty('empty_text', $this->empty_text);
        }
        if (! is_null($this->size)) {
            $uxon->setProperty('size', $this->size);
        }
        if (! is_null($this->style)) {
            $uxon->setProperty('style', $this->style);
        }
        if (! is_null($this->align)) {
            $uxon->setProperty('align', $this->align);
        }
        if (! is_null($this->text)) {
            $uxon->setProperty('text', $this->text);
        }
        if (! is_null($this->getAttributeAlias())) {
            $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
        }
        return $uxon;
    }
}
?>