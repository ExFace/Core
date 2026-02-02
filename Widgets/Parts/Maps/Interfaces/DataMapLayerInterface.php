<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;

/**
 * A map widget layer plotting data on the map
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataMapLayerInterface extends MapLayerInterface, iUseData
{
    /**
     * @return iShowData
     */
    public function getDataWidget() : iShowData;

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface;
}