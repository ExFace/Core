<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\DataTypes\NumberDataType;

/**
 * Configuration for state-indicators in elements of data widgets - e.g. in calendar items.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataItemIndicator implements WidgetPartInterface, iHaveColor, iHaveColorScale
{
    use DataWidgetPartTrait;
    
    use iHaveColorScaleTrait {
        getColorScale as getColorScaleViaTrait;
        hasColorScale as hasColorScaleViaTrait;
    }
    
    private $colorExpr = null;
    
    private $colorColumn = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        $uxon = new UxonObject();
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor(): ?string
    {
        if ($this->colorExpr === null || ! $this->colorExpr->isStatic()) {
            return null;
        }
        return $this->colorExpr->evaluate();
    }

    /**
     * The color of each appointment can be set to an attribute alias, a `=Formula()` or a CSS color value.
     * 
     * @uxon-property color
     * @uxon-type metamodel:expression|color 
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->colorExpr = null;
        $this->colorColumn = null;
        $this->colorExpr = ExpressionFactory::createFromString($this->getWorkbench(), $color, $this->getMetaObject());
        if (! $this->colorExpr->isStatic()) {
            $this->colorColumn = $this->addDataColumn($color);
        }
        
        return $this;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getColorColumn() : ?DataColumn
    {
        return $this->colorColumn;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased(): bool
    {
        return $this->colorExpr->getDataType() instanceof NumberDataType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::getColorScale()
     */
    public function getColorScale() : array
    {
        $scale = $this->getColorScaleViaTrait();
        if (empty($scale) && null !== $colorCol = $this->getColorColumn()) {
            $colWidget = $colorCol->getCellWidget();
            if ($colWidget instanceof iHaveColorScale) {
                return $colWidget->getColorScale();
            }
        }
        return $scale;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveColorScale::hasColorScale()
     */
    public function hasColorScale() : bool
    {
        $value = $this->hasColorScaleViaTrait();
        if ($value === false && null !== $colorCol = $this->getColorColumn()){
            $colWidget = $colorCol->getCellWidget();
            if ($colWidget instanceof iHaveColorScale) {
                return $colWidget->hasColorScale();
            }
        }
        return $value;
    }
}