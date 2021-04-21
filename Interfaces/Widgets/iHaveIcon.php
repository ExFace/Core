<?php
namespace exface\Core\Interfaces\Widgets;

interface iHaveIcon
{
    /**
     * Returs the name of the icon to be used
     *
     * @return string
     */
    public function getIcon() : ?string;

    /**
     * The name of the icon to be displayed.
     * 
     * Refer to the documentation of the facade for supported icon names. Most
     * facades will support font awesome icons and some poprietary icon set
     * additionally.
     *
     * @param string $value            
     * @return boolean
     */
    public function setIcon(string $value) : iHaveIcon;
    
    /**
     * 
     * @param bool|NULL $default
     * @return bool|NULL
     */
    public function getShowIcon(bool $default = null) : ?bool;
    
    /**
     * Force the icon to show (TRUE) or hide (FALSE)
     * 
     * The default depends on the facade used.
     * 
     * @param bool $value
     * @return iHaveIcon
     */
    public function setShowIcon(bool $value) : iHaveIcon;
    
    /**
     * 
     * @return string|NULL
     */
    public function getIconSet() : ?string;
    
    /**
     * Which icon set to use (font awesome "fa" by default)
     * 
     * @param string $iconSetCode
     * @return iHaveIcon
     */
    public function setIconSet(string $iconSetCode) : iHaveIcon;
}