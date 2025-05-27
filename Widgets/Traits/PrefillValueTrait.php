<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Widgets\iSupportAggregators;

/**
 * This trait provides ready-to-use methods to handle prefill expressions in widgets
 * 
 * @author Andrej Kabachnik
 *
 */
trait PrefillValueTrait
{
    
    /**
     * Transforms the given expression (e.g. attribute alias) into one, that can be used in the prefill data.
     *
     * Returns NULL if no transformation is possible.
     *
     * This method is hande in all sorts of prepareDataSheetToXXX() and doPrefill() methods - see
     * corresponding implementations in this class.
     *
     * @param DataSheetInterface $prefillData
     * @param MetaObjectInterface $widget_object
     * @param string|null $attributeAlias
     * @param string|null $dataColumnName
     * @param string|null $formula
     * @return string|null
     */
    protected function getPrefillExpression(DataSheetInterface $prefillData, MetaObjectInterface $widget_object, ?string $attributeAlias = null, ?string $dataColumnName = null, ?ExpressionInterface $formula = null) : ?string
    {
        switch (true) {
            case $attributeAlias !== null: $exprString = $attributeAlias; break;
            case $formula !== null: $exprString = $formula->__toString(); break;
            case $dataColumnName !== null: $exprString = $dataColumnName; break;
        }
        
        if ($exprString === null || $exprString === '') {
            return null;
        }
        
        $prefill_object = $prefillData->getMetaObject();
        
        // See if we are prefilling with the same object as the widget is based
        // on (or a derivative). E.g. if we are prefilling a widget based on FILE,
        // we can use FILE and PDF_FILE objects as both are "files", while a
        // widget based on PDF_FILE cannot be prefilled with simply FILE.
        // If it's a different object, than try to find some relation wetween them.
        if ($prefill_object->is($widget_object)) {
            // If we are looking for attributes of the object of this widget, then just return the attribute_alias
            return $exprString;
        } elseif ($attributeAlias !== null && $widget_object->hasAttribute($attributeAlias)) {
            $attribute = $this->getMetaObject()->getAttribute($attributeAlias);
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
                    $related_obj = $widget_object;
                    foreach ($rel_parts as $rel_nr => $rel_part) {
                        $related_obj = $related_obj->getRelatedObject($rel_part);
                        unset($rel_parts[$rel_nr]);
                        if ($related_obj->isExactly($prefill_object)) {
                            $attr_path = implode(RelationPath::getRelationSeparator(), $rel_parts);
                            // TODO add aggregator here
                            return RelationPath::join($attr_path, $attribute->getAlias());
                        }
                    }
                }
                // If the prefill object is not in the widget's relation path, try to find a relation from this widget's
                // object to the data sheet object and vice versa
                
            } elseif ($attribute->isRelation() && $prefill_object->is($attribute->getRelation()->getRightObject())) {
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
                    $rel_path = RelationPath::join($rel->getAliasWithModifier(), $attribute->getAlias());
                    if ($prefill_object->hasAttribute($rel_path)) {
                        return $prefill_object->getAttribute($rel_path)->getAliasWithRelationPath();
                    }
                }
            }     
        }
        
        return null;
    }
    
    /**
     * Prefills a given UXON property with data from the given data sheet and expression
     *
     * Similarly to getPrefillExpression() this method can be used to quickly perform
     * all the prefill logic for a given prefill expression if there is suitable data 
     * in the provided prefill sheet.
     * 
     * In particular, this method takes care of firing events with the correct data
     * pointers.
     * 
     * It can handle data sheets with single or multiple rows, expressions with aggregators,
     * etc.
     *
     * @param DataSheetInterface $data_sheet
     * @param ExpressionInterface|string $prefillExpr
     * @param string $propName
     * @param callable $propSetter
     * @param ExpressionInterface $staticValueExpr
     */
    protected function doPrefillForExpression(DataSheetInterface $data_sheet, $prefillExpr, string $propName, callable $propSetter, ExpressionInterface $staticValueExpr = null)
    {
        $col = $data_sheet->getColumns()->getByExpression($prefillExpr);
        if (! $col) {
            return;
        }
        
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
        
        // Use the provided setter to put the prefill into the widget and throw the
        // OnPrefillChangePropertyEvent to notify other code, that the prefill was applied.
        switch (true) {
            // If a value was set explicitly and that value is a formula, evaluate it and use for
            // prefill
            case $staticValueExpr !== null && $staticValueExpr->isFormula():
                $propSetter($value);
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, $propName, $valuePointer));
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
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, $propName, $valuePointer));
                break;
        }
        
        return;
    }
}