<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iSupportAggregators;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Allows to bind a widget property to an attribute, a data column or an expression in general.
 * 
 * This class allows developers to quickly bind widget properties like `value` or `color` to data,
 * formulas or any other types of expressions.
 * 
 * @author Andrej Kabachnik
 * 
 */
class WidgetPropertyBinding implements WidgetPropertyBindingInterface
{
    use ImportUxonObjectTrait;
    
    const BINDING_TYPE_ATTRIBUTE = 'attribute';
    
    const BINDING_TYPE_COLUMN = 'column';

    const BINDING_TYPE_CALCULATION = 'calculation';
    
    const BINDING_TYPE_NONE = 'none';
    
    private $widget = null;
    
    private $workbench = null;
    
    private $propertyName = null;
    
    private $dataBindingType = null;
    
    private $attributeAlias = null;

    private $attribtueRelationPathString = null;
    
    private $dataColumnName = null;
    
    private $valueExprString = null;
    
    private $valueExpr = null;

    private $aggregateFunc = null;
    
    /**
     * 
     * @param \exface\Core\Interfaces\WidgetInterface $widget
     * @param string $propertyName
     * @param \exface\Core\CommonLogic\UxonObject|null $uxon
     */
    public function __construct(WidgetInterface $widget, string $propertyName, UxonObject $uxon = null)
    {
        $this->widget = $widget;
        $this->workbench = $widget->getWorkbench();
        $this->propertyName = $propertyName;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getPropertyName()
     */
    public function getPropertyName() : string
    {
        return $this->propertyName;
    }
    
    /**
     * Bind this property to a metamodel attribute
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface
     */
    protected function setAttributeAlias(string $alias) : WidgetPropertyBindingInterface
    {
        if ($this->attribtueRelationPathString !== null && ! StringDataType::startsWith($this->attributeAlias, $this->attribtueRelationPathString)) {
            $this->attributeAlias = $this->attribtueRelationPathString . RelationPath::getRelationSeparator() . $alias;
        } else {
            $this->attributeAlias = $alias;
        }
        $this->dataBindingType = null;
        return $this;
    }

    /**
     * Force all attribute references in this binding to use a common relation path
     * 
     * This is helpful if you have multiple widget properties bound to different attributes of a single
     * related object. 
     * 
     * **WARNING:** This will make the binding resolve ALL attributes relatively to the object at the
     * end of this relation path! Use with care!
     * 
     * @uxon-property attribute_relation_path
     * @uxon-type metamodel:relation
     * 
     * @param string $path
     * @return WidgetPropertyBindingInterface
     */
    protected function setAttributeRelationPath(string $path) : WidgetPropertyBindingInterface
    {
        $relPathPrefix = $path. RelationPath::RELATION_SEPARATOR;
        if ($this->attributeAlias !== null) {
            if (! StringDataType::startsWith($this->attributeAlias, $relPathPrefix)) {
                $this->getAttributeAlias = $relPathPrefix . $this->attributeAlias;
            }
        }
        return $this;
    }
    
    /**
     * Bind this property to a data column name (even one, that does not refer an attribute)
     * 
     * @uxon-property data_column_name
     * @uxon-type string
     * 
     * @param string $name
     * @return \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface
     */
    protected function setDataColumnName(string $name) : WidgetPropertyBindingInterface
    {
        if ($this->getMetaObject()->hasAttribute($name)) {
            return $this->setAttributeAlias($name);
        }
        $this->dataColumnName = DataColumn::sanitizeColumnName($name);
        $this->dataBindingType = null;
        return $this;
    }

    /**
     * Bind this property to a calculation formula
     * 
     * @uxon-property calculation
     * @uxon-type metamodel:formula
     * 
     * @param string $formula
     * @return \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface
     */
    protected function setCalculation(string $formula) : WidgetPropertyBindingInterface
    {
        return $this->setValue($formula);
    }
    
    /**
     * 
     * @return string
     */
    protected function getBindingType() : string
    {
        if ($this->dataBindingType === null) {
            switch (true) {
                case $this->attributeAlias !== null:
                    $this->dataBindingType = self::BINDING_TYPE_ATTRIBUTE;
                    break;
                case $this->hasValue() === true && $this->getValueExpression()->isFormula() === true:
                    $this->dataBindingType = self::BINDING_TYPE_CALCULATION;
                    break;
                case $this->dataColumnName !== null:
                    $this->dataBindingType = self::BINDING_TYPE_COLUMN;
                    break;
                default:
                    $this->dataBindingType = self::BINDING_TYPE_NONE;
            }
        }
        return $this->dataBindingType;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::isBoundToAttribute()
     */
    public function isBoundToAttribute() : bool
    {
        return $this->getBindingType() === self::BINDING_TYPE_ATTRIBUTE;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::isBoundToDataColumn()
     */
    public function isBoundToDataColumn() : bool
    {
        return $this->getDataColumnName() !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getAttributeAlias()
     */
    public function getAttributeAlias() : ?string
    {
        return $this->isBoundToAttribute() ? $this->attributeAlias : null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $dataSheet)
    {
        if ($this->isBoundToDataColumn() === true || $this->isBoundToDataColumn() === true) {
            $valuePrefillExpr = $this->getPrefillExpression($dataSheet);
            if ($valuePrefillExpr !== null && ! $dataSheet->getColumns()->getByExpression($valuePrefillExpr)) {
                $dataSheet->getColumns()->addFromExpression($valuePrefillExpr);
            }
        }
        return $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $dataSheet)
    {
        return $this->prepareDataSheetToRead($dataSheet);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::prefill()
     */
    public function prefill(DataSheetInterface $dataSheet)
    {
        if ($this->isBoundToAttribute() === true) {
            // TODO
            if (null !== $expr = $this->getPrefillExpression($dataSheet)) {
                $this->doPrefillForExpression(
                    $dataSheet,
                    $expr,
                    function($value){
                        // TODO
                        $this->setValue($value ?? '');
                    }
                );
            }
        }
        return;
    }
    
    protected function doPrefillForExpression(DataSheetInterface $data_sheet, $prefillExpr, callable $propSetter)
    {
        switch (true) {
            case $this->isBoundToAttribute():
                $col = $data_sheet->getColumns()->getByAttribute($this->getAttribute());
                break;
            case $this->isBoundToDatacolumn():
                $col = $data_sheet->getColumns()->get($this->getDataColumnName());
                break;
            default:
                return;
                
        }
        
        if (! $col) {
            return;
        }
        
        $staticValueExpr = $this->getValueExpression();
        $propName = $this->getPropertyName();
        $value = null;
        $valuePointer = null;
        
        if (count($col->getValues(false)) > 1) {
            if (($this instanceof iSupportAggregators) && null !== $aggr = $this->getAggregator()) {
                $valuePointer = DataPointerFactory::createFromColumn($col);
                $value = $col->aggregate($aggr);
            }
        } else {
            $valuePointer = DataPointerFactory::createFromColumn($col, 0);
            $value = $valuePointer->getValue();
        }
        
        // Ignore empty values because if value is a live-reference, the ref address would get overwritten
        // even without a meaningfull prefill value
        if ($valuePointer === null) {
            return;
        }

        $eventMgr = $this->getWorkbench()->eventManager();
        
        // Use the provided setter to put the prefill into the widget and throw the
        // OnPrefillChangePropertyEvent to notify other code, that the prefill was applied.
        switch (true) {
            // If a value was set explicitly and that value is a formula, evaluate it and use for
            // prefill
            case $staticValueExpr !== null && $staticValueExpr->isFormula():
                $propSetter($value);
                $eventMgr->dispatch(new OnPrefillChangePropertyEvent($this->getWidget(), $propName, $valuePointer));
                // FIXME now, that there is a separate `calculation` property, wouldn't it be better
                // to skip the prefill for widget with live-refs in general and not only for non-empty
                // values?
                break;
                // If the value from the prefill sheet is NOT empty, use it for prefill
            case $value !== null && $value != '':
                // If the value IS empty, assume a prefill too, but only if the property is not bound
                // by reference
            case $staticValueExpr === null:
            case $staticValueExpr->isReference() === false:
                $propSetter($value);
                $eventMgr->dispatch(new OnPrefillChangePropertyEvent($this->getWidget(), $propName, $valuePointer));
                break;
        }
        
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getDataColumnName()
     */
    public function getDataColumnName()
    {
        if ($this->dataColumnName === null) {   
            switch ($this->getBindingType()) {
                case self::BINDING_TYPE_ATTRIBUTE:
                    $this->dataColumnName = DataColumn::sanitizeColumnName($this->getAttributeAlias());
                    break;
                case self::BINDING_TYPE_CALCULATION:
                    $this->dataColumnName = DataColumn::sanitizeColumnName($this->getValueExpression()->__toString());
                    break;
                // case self::BINDING_TYPE_COLUMN - won't happen as the name is already there    
            }
        }
        return $this->dataColumnName;
    }
    
    /**
     *
     * @return MetaAttributeInterface
     */
    public function getAttribute() : MetaAttributeInterface
    {
        if ($this->isBoundToAttribute() === true) {
            return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
        }
        return $this->getAttribute();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::setValue()
     */
    public function setValue($value) : WidgetPropertyBindingInterface
    {
        switch (true) {
            case $value instanceof ExpressionInterface:
                $this->valueExprString = $value->__toString();
                $this->valueExpr = $value;
                break;
            case is_scalar($value):
                $this->valueExprString = $value;
                $this->valueExpr = null;
                break;
            default:
                throw new InvalidArgumentException('Invalid type of value for a widget property binding: expecting an expression as string or object, "' . get_class($value) . '" received.');
        }
        $this->dataBindingType = null;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getValue()
     */
    public function getValue() : ?string
    {
        return $this->valueExprString;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getValueExpression()
     */
    public function getValueExpression() : ?ExpressionInterface
    {
        if ($this->valueExpr === null && $this->valueExprString !== null) {
            $this->valueExpr = ExpressionFactory::createFromString($this->getWorkbench(), $this->valueExprString, $this->getMetaObject());
        }
        return $this->valueExpr;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::hasValue()
     */
    public function hasValue() : bool
    {
        return $this->valueExprString !== null || $this->valueExpr !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return $this->hasValue() === false && $this->isBoundToDataColumn() === false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getMetaObject()
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->getWidget()->getMetaObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getDataType()
     */
    public function getDataType() : ?DataTypeInterface
    {
        switch ($this->getBindingType()) {
            case self::BINDING_TYPE_ATTRIBUTE:
                return $this->getAttribute()->getDataType();
            // TODO what about data columns?
        } 
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::getAggregator()
     */
    public function getAggregator(): ?AggregatorInterface
    {
        if ($this->aggregateFunc === false) {
            return null;
        }
        if ($this->aggregateFunc === null) {
            if ((Expression::detectFormula($this->getAttributeAlias()) === false) && $aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->getAttributeAlias())) {
                $this->aggregateFunc = $aggr;
            }
        }
        return $this->aggregateFunc;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::hasAggregator()
     */
    public function hasAggregator() : bool
    {
        return $this->getAggregator() !== null;
    }
    
    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $prefillData
     * @return string|null
     */
    protected function getPrefillExpression(DataSheetInterface $prefillData) : ?string
    {
        
        $prefillObj = $prefillData->getMetaObject();
        $widgetObj = $this->getMetaObject();

        switch ($this->getBindingType()) {
            case self::BINDING_TYPE_ATTRIBUTE:
                $expression = $this->getAttributeAlias();
                break;
            case self::BINDING_TYPE_CALCULATION:
                $expression = $this->getValueExpression()->__toString();
                break;
            case self::BINDING_TYPE_COLUMN && $prefillObj->is($widgetObj):
                return $this->getDataColumnName();
            default:
                $expression = null;
        }
        
        if ($expression === null || $expression === '') {
            return null;
        }
        
        // See if we are prefilling with the same object as the widget is based
        // on (or a derivative). E.g. if we are prefilling a widget based on FILE,
        // we can use FILE and PDF_FILE objects as both are "files", while a
        // widget based on PDF_FILE cannot be prefilled with simply FILE.
        // If it's a different object, than try to find some relation wetween them.
        if ($prefillObj->is($widgetObj)) {
            // If we are looking for attributes of the object of this widget, then just return the attribute_alias
            return $expression;
        } elseif ($expression !== null && $widgetObj->hasAttribute($expression)) {
            $attribute = $this->getMetaObject()->getAttribute($expression);
            // If not, we are dealing with a prefill with data of another object. It only makes sense to try to prefill here,
            // if the widgets shows an attribute, because then we have a chance to find a relation between the widget's object
            // and the prefill object
            
            // If the widget shows an attribute with a relation path, try to rebase that attribute relative to the
            // prefill object (this is possible, if the prefill object sits somewhere along the relation path. So,
            // traverse up this path to see if it includes the prefill object. If so, add a column to the prefill
            // sheet, that contains the widget's attribute with a relation path relative to the prefill object.
            if ($rel_path = $attribute->getRelationPath()->toString()) {
                $rel_parts = RelationPath::relationPathParse($rel_path);
                if (is_array($rel_parts)) {
                    $related_obj = $widgetObj;
                    foreach ($rel_parts as $rel_nr => $rel_part) {
                        $related_obj = $related_obj->getRelatedObject($rel_part);
                        unset($rel_parts[$rel_nr]);
                        if ($related_obj->isExactly($prefillObj)) {
                            $attr_path = implode(RelationPath::getRelationSeparator(), $rel_parts);
                            // TODO add aggregator here
                            return RelationPath::join($attr_path, $attribute->getAlias());
                        }
                    }
                }
                // If the prefill object is not in the widget's relation path, try to find a relation from this widget's
                // object to the data sheet object and vice versa
                
            } elseif ($attribute->isRelation() && $prefillObj->is($attribute->getRelation()->getRightObject())) {
                // If this widget represents the relation from the sheet object to the prefill object, the prefill value would be the
                // right key of the relation (e.g. trying to prefill the order positions attribute "ORDER" relative to the object
                // "ORDER" should result in the attribute UID of ORDER because it is the right key and must have a value matching the
                // left key).
                return $attribute->getRelation()->getRightKeyAttribute()->getAliasWithRelationPath();
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
                foreach ($prefillObj->findRelations($widgetObj->getId(), RelationTypeDataType::REGULAR) as $rel) {
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
                    $rel_path = RelationPath::join($rel->getAliasWithModifier(), $attribute->getAlias());
                    if ($prefillObj->hasAttribute($rel_path)) {
                        return $prefillObj->getAttribute($rel_path)->getAliasWithRelationPath();
                    }
                }
            }     
        }
        
        return null;
    }
}