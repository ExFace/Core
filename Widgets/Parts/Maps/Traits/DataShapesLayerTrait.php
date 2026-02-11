<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;

/**
 * This trait adds the shape_attribute_alias property
 * 
 * @author Andrej Kabachnik
 *
 */
trait DataShapesLayerTrait
{

    private $shapeAttributeAlias = null;

    private $shapeColumn = null;


    /**
     *
     * @return string
     */
    public function getShapesAttributeAlias() : string
    {
        return $this->shapeAttributeAlias;
    }

    /**
     * Alias of the attribtue that will contain the shape of a marker
     *
     * @uxon-property shapes_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setShapesAttributeAlias(string $value) : MapLayerInterface
    {
        $this->shapeAttributeAlias = $value;
        return $this;
    }

    /**
     *
     * @return DataColumn
     */
    public function getShapesColumn() : DataColumn
    {
        return $this->shapeColumn;
    }

    /**
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidgetShapeColumn(iShowData $widget) : iShowData
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