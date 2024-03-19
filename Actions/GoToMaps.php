<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;

/**
 * Opens the local mapping application for the given coordinates.
 *
 * @author Ralf Mulansky
 *        
 */
class GoToMaps extends GoToUrl
{
    const URL_TYPE_GOOGLE = 'google';
    
    const URL_TYPE_GEO = 'geo';
    
    const URL_TYPE_MAPS = 'maps';
    
    private $latitude = null;
    
    private $longitude = null;
    
    private $urlType = self::URL_TYPE_GEO;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::MAP_O);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\GoToUrl::buildUrl()
     */
    protected function buildUrl() : ?string
    {
        if (null !== $url = parent::buildUrl()) {
            return $url;
        }
        
        switch ($this->getUrlType()) {
            case self::URL_TYPE_GEO:
                return "geo:{$this->getLatitude()},{$this->getLongitude()}";
            case self::URL_TYPE_GOOGLE:
                return "https://www.google.com/maps/search/?api=1&query={$this->getLatitude()}%2C{$this->getLongitude()}";
            case self::URL_TYPE_MAPS:
                return "maps://{$this->getLatitude()},{$this->getLongitude()}";
        }
    }
    
    protected function getLatitude() : string
    {
        return $this->latitude;
    }
    
    /**
     * The latitude of the map center: number or [#placeholder#]
     * 
     * @uxon-property latitude
     * @uxon-type string
     * 
     * @param string $value
     * @return GoToMaps
     */
    public function setLatitude(string $value) : GoToMaps
    {
        $this->latitude = $value;
        return $this;
    }
    
    protected function getLongitude() : string
    {
        return $this->longitude;
    }
    
     /**
     * The longitude of the map center: number or [#placeholder#]
     * 
     * @uxon-property longitude
     * @uxon-type string
     * 
     * @param string $value
     * @return GoToMaps
     */
    public function setLongitude(string $value) : GoToMaps
    {
        $this->longitude = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return true;
    }
    
    protected function getUrlType() : string
    {
        return $this->urlType;
    }
    
    /**
     * What type of URL to generate: `google`, `geo` or `maps`.
     * 
     * Depending on the client different types of maps URLs may work. 
     * 
     * @uxon-property url_type
     * @uxon-type [geo,google,maps]
     * @uxon-default geo
     * 
     * @param string $value
     * @return GoToMaps
     */
    public function setUrlType(string $value) : GoToMaps
    {
        $this->urlType = $value;
        return $this;
    }
}