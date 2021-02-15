<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class GenericUrlTiles extends AbstractBaseMap
{
    private $url = null;
    
    private $attribution = null;
    
    public function getUrl(string $default = '') : string
    {
        $url = $this->url ?? $default;
        if ($url === '') {
            throw new WidgetConfigurationError($this->getMap(), 'Invalid configuration for base map "' . $this->getCaption() . '": no URL to get tiles from provided!');
        }
        return $url;
    }
    
    /**
     * The URL to get the tiles from.
     * 
     * Accepts any format compatible with the facade used to render the map. OpenStreetMap-style 
     * tile URLs are very common: https://wiki.openstreetmap.org/wiki/Tiles#Base_maps.
     * 
     * @uxon-property url
     * @uxon-type url
     * 
     * @param string $value
     * @return GenericUrlTiles
     */
    protected function setUrl(string $value) : BaseMapInterface
    {
        $this->url = $value;
        return $this;
    }
    
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
     * @return GenericUrlTiles
     */
    public function setAttribution(string $value) : GenericUrlTiles
    {
        $this->attribution = $value;
        return $this;
    }
}