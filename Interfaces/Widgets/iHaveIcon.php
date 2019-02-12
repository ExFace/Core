<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveIcon extends WidgetInterface
{
    /**
     * Returs the name of the icon to be used
     *
     * @return string
     */
    public function getIcon() : ?string;

    /**
     * The icon name to be displayed.
     * 
     * Refer to the documentation of the template for supported icon names. Most
     * templates will support font awesome icons and some poprietary icon set
     * additionally.
     * 
     * @uxon-property icon
     * @uxon-type icon|string
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
     * The default depends on the template used.
     * 
     * @uxon-property show_icon
     * @uxon-type boolean 
     * 
     * @param bool $value
     * @return iHaveIcon
     */
    public function setShowIcon(bool $value) : iHaveIcon;
}