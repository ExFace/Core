<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;

/**
 * This trait adds the tooltip_attribute_alias property
 * 
 * @author Andrej Kabachnik
 *
 */
trait DataTooltipLayerTrait
{
    private $tooltipAttribtueAlias = null;

    private $tooltipColumn = null;

    /**
     *
     * @return string|NULL
     */
    public function getTooltipAttributeAlias() : ?string
    {
        return $this->tooltipAttribtueAlias;
    }

    /**
     * Alias of the attribute containing the data to show in the tooltip of a marker
     *
     * @uxon-property tooltip_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setTooltipAttributeAlias(string $value) : MapLayerInterface
    {
        $this->tooltipAttribtueAlias = $value;
        return $this;
    }

    /**
     *
     * @return bool
     */
    public function hasTooltip() : bool
    {
        return $this->getTooltipAttributeAlias() !== null;
    }

    /**
     *
     * @return DataColumn|NULL
     */
    public function getTooltipColumn() : ?DataColumn
    {
        return $this->tooltipColumn;
    }

    /**
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidgetTooltip(iShowData $widget) : iShowData
    {
        if (null !== $alias = $this->getShapesAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->shapeColumn = $col;
        }
        return $widget;
    }
}