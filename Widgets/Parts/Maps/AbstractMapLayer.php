<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractMapLayer extends AbstractMapPart implements MapLayerInterface
{
    use iHaveCaptionTrait;
    
    /**
     * @uxon-property type
     * @uxon-type [DataLine,DataMarkers]
     * @uxon-required true
     * 
     * @return MapLayerInterface
     */
    protected function setType() : MapLayerInterface
    {
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getType() : string
    {
        $class = PhpClassDataType::findClassNameWithoutNamespace($this);
        return StringDataType::substringBefore($class, 'Layer', $class);
    }
}