<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\DataColumn;

/**
 * Layers, that use a data column holding a geo shape definition (e.g. GeoJSON)
 *  
 * @author Andrej Kabachnik
 *
 */
interface ShapeDataColumnMapLayerInterface extends MapLayerInterface
{
    /**
     *
     * @return string
     */
    public function getShapesAttributeAlias() : string;

    /**
     *
     * @return DataColumn
     */
    public function getShapesColumn() : DataColumn;
}