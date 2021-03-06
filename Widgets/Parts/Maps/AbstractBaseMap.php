<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractBaseMap extends AbstractMapLayer implements BaseMapInterface
{
    private $attribution = null;
    
    /**
     *
     * @return string|NULL
     */
    public function getAttribution() : ?string
    {
        return $this->attribution;
    }
    
    /**
     * Changes the attribution shown on the map (accepts HTML).
     *
     * @uxon-property attribution
     * @uxon-type string
     *
     * @param string $value
     * @return BaseMapInterface
     */
    public function setAttribution(string $value) : BaseMapInterface
    {
        $this->attribution = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractMapPart::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }
}