<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\CommonLogic\DataSheets\DataAggregation;

/**
 * This trait provides a getCaption() for widgets, that can be bound to metamodel attributes.
 * 
 * If no caption is specified explicitly, the caption will be automatically
 * derived from
 * - the attribute name, if the widget is bound to a regular attribute
 * - the name of the last relation in the path, if the widget is bound to
 * a related label attribute (e.g. OBJECT__LABEL in a widget based on ATTRIBUTE).
 * 
 * @author Andrej Kabachnik
 *
 */
trait AttributeCaptionTrait
{
    
    
    /**
     * Depending on the content of the column, it will get a different default caption:
     *
     * - If the column shows a regular attribute, the name of the that attribute will be used
     * - If the column shows a __LABEL attribute of a related object, the name of the last relation will be used
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        $caption = parent::getCaption();
        if ($caption === null || $caption === '') {
            if ($attr = $this->getAttribute()) {
                if ($this->hasAggregator()) {
                    $aggr = ' (' . $this->getAggregator()->getFunction()->getLabelOfValue() . ')';
                }
                
                // FIXME isLabelForObject works instable, as MetaObject->getLabelAlias() will yield LABEL or the Label of the underlying attribute pretty unpredictabely
                if (/*$attr->isLabelForObject() === true && */$attr->getRelationPath()->isEmpty() === false && $this->isBoundToLabelAttribute() === true) {
                    $this->setCaption($attr->getRelationPath()->getRelationLast()->getName() . $aggr);
                } else {
                    $this->setCaption($attr->getName() . $aggr);
                }
            }
        }
        return parent::getCaption();
    }
    
    
    
    /**
     * Returns TRUE if this column has an attribute alias ending with __LABEL and FALSE otherwise.
     *
     * @return bool
     */
    protected function isBoundToLabelAttribute() : bool
    {
        $alias = $this->getAttributeAlias();
        
        if ($alias === null || $alias === '') {
            return false;
        }
        
        if ($this->getAttribute()) {
            $labelRelPathEnding = RelationPath::RELATION_SEPARATOR . $this->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS');
            if (StringDataType::endsWith($alias, $labelRelPathEnding, false) === true) {
                return true;
            } else {
                $alias = DataAggregation::stripAggregator($alias);
                if (StringDataType::endsWith($alias, $labelRelPathEnding, false) === true) {
                    return true;
                }
            }
        }
        
        return false;
    }
}