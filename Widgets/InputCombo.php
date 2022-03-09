<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading.
 * It also can optionally accept new values.
 * 
 * @see InputSelect
 *
 * @author Andrej Kabachnik
 */
class InputCombo extends InputSelect implements iSupportLazyLoading
{
    use iSupportLazyLoadingTrait {
        setLazyLoadingAction as setLazyLoadingActionViaTrait;
    }
    
    // FIXME move default value to facade config option WIDGET.INPUTCOMBO.MAX_SUGGESTION like PAGE_SIZE of tables
    private $max_suggestions = 20;

    private $allow_new_values = null;

    private $autoselect_single_suggestion = true;

    /**
     * Defines the alias of the action to be called by the autosuggest.
     * 
     * @uxon-property lazy_loading_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": "exface.Core.Autosuggest"}
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction(UxonObject $uxon) : iSupportLazyLoading
    {
        $this->setLazyLoadingActionViaTrait($uxon);
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Traits\iSupportLazyLoadingTrait::getLazyLoadingActionUxonDefault()
     */
    protected function getLazyLoadingActionUxonDefault() : UxonObject
    {
        return new UxonObject([
           "alias" => "exface.Core.AutoSuggest" 
        ]);
    }
    
    /**
     * Returns the relation, this widget represents or FALSE if the widget stands for a direct attribute.
     * This shortcut function is very handy because a InputCombo often stands for a relation.
     *
     * @return MetaRelationInterface|NULL
     */
    public function getRelation() : ?MetaRelationInterface
    {
        if ($this->isRelation()) {
            $relAlias = DataAggregation::stripAggregator($this->getAttributeAlias());
            return $this->getMetaObject()->getRelation($relAlias);
        }
        return null;
    }
    
    /**
     *
     * @return bool
     */
    public function isRelation() : bool
    {
        return $this->isBoundToAttribute() === true && $this->getAttribute()->isRelation() === true;
    }
    
    /**
     * If the widget is based on a relation attribute, the options object is automatically
     * the object that the relation points too. Otherwise the options object is determined
     * regularly: either being set directly or assumed to be the widgets object.
     * 
     * @see \exface\Core\Widgets\InputSelect::getOptionsObject()
     */
    public function getOptionsObject()
    {
        if (! $this->isOptionsObjectSpecified()) {
            if ($this->isRelation()) {
                $this->setOptionsObject($this->getRelation()->getRightObject());
            }
        }
        return parent::getOptionsObject();
    }

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getAllowNewValues() : bool
    {
        if ($this->allow_new_values === null) {
            return ! $this->isRelation();
        }
        return $this->allow_new_values;
    }

    /**
     * Set to TRUE to allow values not present in the autosuggest or FALSE to forbid.
     * 
     * By default, new values are allowed unless the widget is used for a relation
     * (i.e. for selecting foreign keys).
     *
     * @uxon-property allow_new_values
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAllowNewValues(bool $value) : InputCombo
    {
        $this->allow_new_values = $value;
        return $this;
    }

    public function getMaxSuggestions()
    {
        return $this->max_suggestions;
    }

    /**
     * Limits the number of suggestions loaded for every autosuggest.
     * 
     * The default value depends on the facade used.
     *
     * @uxon-property max_suggestions
     * @uxon-type integer
     *
     * @param integer $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setMaxSuggestions($value)
    {
        $this->max_suggestions = intval($value);
        return $this;
    }

    public function getAutoselectSingleSuggestion() : bool
    {
        return $this->autoselect_single_suggestion;
    }

    /**
     * Set to FALSE to disable automatic selection of the suggested value if only one suggestion found.
     *
     * @uxon-property autoselect_single_suggestion
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAutoselectSingleSuggestion($value)
    {
        $this->autoselect_single_suggestion = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithWidgetObject()
     */
    protected function doPrefillWithWidgetObject(DataSheetInterface $data_sheet)
    {
        if (! $this->getAttributeAlias() || ! $data_sheet->getColumns()->getByExpression($this->getAttributeAlias())){
            return;
        }
        
        // If the prefill data is based on the same object, as the widget and has a column matching
        // this widgets attribute_alias, simply look for all the required attributes in the prefill data.
        if ($col = $data_sheet->getColumns()->getByExpression($this->getAttributeAlias())) {
            $valuePointer = DataPointerFactory::createFromColumn($col, 0);
            $value = $valuePointer->getValue();
            
            // If it is a single-select but the prefill has multiple values (either explicitly or as a delimited list),
            // do not use the prefill data - we don't know which value to use!
            if ($this->getMultiSelect() === false && $value) {
                if ($data_sheet->countRows() > 1 || count(explode($this->getMultiSelectValueDelimiter(), $value)) > 1) {
                    return;
                }
            }
            
            $this->setValue($value, false);
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $valuePointer));
        }
        
        // Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
        // but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
        // set it here. In most facades, setting merely the value of the combo will make the facade load the
        // corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
        if ($this->getAttribute()->isRelation()) {
            // FIXME use $this->getTextAttributeAlias() here instead? But isn't that alias relative to the table's object?
            $text_column_expr = RelationPath::relationPathAdd($this->getAttribute()->getAliasWithRelationPath(), $this->getTextColumn()->getAttributeAlias());
            // If the column we would need is not there and it's the label column (which is very probable), it might just be named differently
            // Many DataSheets include relation__LABEL columns but may not inlcude a column with the alias of the label attribute. It's worth
            // trying this trick to prevent additional queries to the data source just to find the text for the combo value!
            if (! $data_sheet->getColumns()->getByExpression($text_column_expr) && $this->getTextColumn()->getAttribute()->isLabelForObject() === true) {
                // FIXME use $this->getTextAttributeAlias() here instead? But isn't that alias relative to the table's object?
                $text_column_expr = RelationPath::relationPathAdd($this->getAttribute()->getAliasWithRelationPath(), MetaAttributeInterface::OBJECT_LABEL_ALIAS);
            }
        } elseif ($this->getMetaObject()->isExactly($this->getOptionsObject())) {
            $text_column_expr = $this->getTextColumn()->getExpression()->toString();
        }
        
        if ($text_column_expr && $col = $data_sheet->getColumns()->getByExpression($text_column_expr)) {
            $textPointer = DataPointerFactory::createFromColumn($col, 0);
            $this->setValueText($textPointer->getValue());
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_text', $textPointer));
        }
        return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithOptionsObject()
     */
    protected function doPrefillWithOptionsObject(DataSheetInterface $data_sheet)
    {
        // If the sheet is based upon the object, that is being selected by this Combo, we can use the prefill sheet
        // values directly
        $rowNr = $this->getMultiSelect() !== true ? 0 : null;
        if ($col = $data_sheet->getColumns()->getByAttribute($this->getValueAttribute())) {
            $pointer = DataPointerFactory::createFromColumn($col, $rowNr);
            $value = $pointer->getValue();
            if ($this->getMultiSelect() && is_array($value)) {
                $value = $col->aggregate(AggregatorFunctionsDataType::LIST_ALL);
            }
            $this->setValue($value, false);
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $pointer));
        }
        if ($col = $data_sheet->getColumns()->getByAttribute($this->getTextAttribute())) {
            $pointer = DataPointerFactory::createFromColumn($col, $rowNr);
            $text = $pointer->getValue();
            if ($this->getMultiSelect() && is_array($text)) {
                $text = $col->aggregate(AggregatorFunctionsDataType::LIST_ALL);
            }
            $this->setValueText($text);
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_text', $pointer));
        }
        return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithRelationsInData()
     */
    protected function doPrefillWithRelationsInData(DataSheetInterface $data_sheet)
    {
        if (! $this->isRelation()){
            return;
        }
        
        // If it is not the object selected within the combo, than we still can look for columns in the sheet, that
        // contain selectors (UIDs) of that object. This means, we need to look for data columns showing relations
        // and see if their related object is the same as the related object of the relation represented by the combo.
        foreach ($data_sheet->getColumns()->getAll() as $column) {
            if (($colAttr = $column->getAttribute()) && $colAttr->isRelation()) {
                $colRel = $colAttr->getRelation();
                if ($colRel->getRightObject()->is($this->getRelation()->getRightObject())) {
                    $this->setValuesFromArray($column->getValues(false), false);
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', DataPointerFactory::createFromColumn($column)));
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'values', DataPointerFactory::createFromColumn($column)));
                    return;
                }
                /* TODO add other options to prefill from related data
                 if ($colRel->getLeftKeyAttribute()->isExactly($this->getAttribute())) {
                 $this->setValuesFromArray($column->getValues(false));
                 $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', DataPointerFactory::createFromColumn($column)));
                 $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'values', DataPointerFactory::createFromColumn($column)));
                 return;
                 }*/
            }
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        if ($data_sheet->isEmpty() === true) {
            return;
        }
        
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            $this->doPrefillWithWidgetObject($data_sheet);
        } else {
            // If the prefill data was loaded for another object, there are still multiple
            // possibilities to prefill
            if ($data_sheet->getMetaObject()->is($this->getOptionsObject())) {
                $this->doPrefillWithOptionsObject($data_sheet);
                return;
            } elseif ($this->isRelation()) {
                $this->doPrefillWithRelationsInData($data_sheet);
                return;
            }
        }
        
        return;
    }
    
    /**
     *
     * To prefill a combo, we need it's value and the corresponding text.
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        // Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return $data_sheet;
        }
        
        $sheetObj = $data_sheet->getMetaObject();
        $widgetObj = $this->getMetaObject();
        if ($sheetObj->is($widgetObj)) {
            $data_sheet->getColumns()->addFromExpression($this->getAttributeAlias());
            
            // Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
            // but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
            // set it here. In most facades, setting merely the value of the combo will make the facade load the
            // corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
            if ($this->getAttribute() && $this->getAttribute()->isRelation()) {
                // FIXME use $this->getTextAttributeAlias() here instead? But isn't that alias relative to the table's object?
                $text_column_expr = RelationPath::relationPathAdd($this->getAttribute()->getAliasWithRelationPath(), $this->getTextColumn()->getAttributeAlias());
                // When the text for a combo comes from another data source, reading it in advance
                // might have a serious performance impact. Since adding the text column to the prefill
                // is generally optional (see above), it is a good idea to check, if the text column
                // can be read with the same query, as the rest of the prefill da and, if not, exclude
                // it from the prefill.
                $sheetObj = $sheetObj;
                if ($sheetObj->isReadable() && $sheetObj->hasAttribute($text_column_expr)) {
                    $sheetQuery = QueryBuilderFactory::createForObject($sheetObj);
                    if (! $sheetQuery->canRead($text_column_expr)) {
                        unset($text_column_expr);
                    }
                }
            } elseif ($widgetObj->isExactly($this->getOptionsObject())) {
                $text_column_expr = $this->getTextColumn()->getExpression()->toString();
            }
            
            if ($text_column_expr) {
                $data_sheet->getColumns()->addFromExpression($text_column_expr);
            }
        } elseif ($this->isRelation() && $this->getRelation()->getRightObject()->is($sheetObj)) {
            $data_sheet->getColumns()->addFromAttribute($this->getRelation()->getRightKeyAttribute());
        } else {
            // If the prefill object is not the same as the widget object, try to find a relation
            // path from prefill to widget. If found, we can add the required column by prefixing
            // them with this relation. If the path contains reverse relations, the data will need
            // to be aggregated!
            if ($this->isBoundToAttribute() && $relPath = $this->findRelationPathFromObject($sheetObj)) {
                $isRevRel = $relPath->containsReverseRelations();
                $keyPrefillAlias = RelationPath::relationPathAdd($relPath->toString(), $this->getAttributeAlias());
                if ($isRevRel) {
                    $keyPrefillAlias = DataAggregation::addAggregatorToAlias(
                        $keyPrefillAlias,
                        new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::LIST_DISTINCT, [$this->getAttribute()->getValueListDelimiter()])
                        );
                }
                if (! $data_sheet->getColumns()->getByExpression($keyPrefillAlias)) {
                    $data_sheet->getColumns()->addFromExpression($keyPrefillAlias);
                }
                
                if ($this->isRelation()) {
                    $textPrefillAlias = RelationPath::relationPathAdd(DataAggregation::stripAggregator($keyPrefillAlias), $this->getTextAttributeAlias());
                    if ($isRevRel) {
                        $textPrefillAlias = DataAggregation::addAggregatorToAlias(
                            $textPrefillAlias,
                            new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::LIST_DISTINCT, [$this->getTextAttribute()->getValueListDelimiter()])
                            );
                    }
                    if (! $data_sheet->getColumns()->getByExpression($textPrefillAlias)) {
                        $data_sheet->getColumns()->addFromExpression($textPrefillAlias);
                    }
                }
            }
        }
        
        return $data_sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::setOptionsFromDataSheet()
     */
    protected function setOptionsFromDataSheet(DataSheetInterface $data_sheet, bool $readIfNotFresh = null)
    {
        if ($readIfNotFresh === null) {
            $readIfNotFresh = ! $this->getLazyLoading();
        }
        return parent::setOptionsFromDataSheet($data_sheet, $readIfNotFresh);
    }
}