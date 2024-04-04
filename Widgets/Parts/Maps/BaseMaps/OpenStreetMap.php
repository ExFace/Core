<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class OpenStreetMap extends GenericUrlTiles
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\BaseMaps\GenericUrlTiles::getUrl()
     */
    public function getUrl(string $default = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png') : string
    {
        return parent::getUrl($default);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\BaseMaps\GenericUrlTiles::getAttribution()
     */
    public function getAttribution() : ?string
    {
        return parent::getAttribution() ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        return parent::getCaption() ?? 'OpenStreetMap';
    }
}