<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveIcon;

trait iHaveIconTrait {
    
    private $icon = null;
    
    private $showIcon = null;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIcon()
     */
    public function getIcon() : ?string
    {
        return $this->icon;
    }

    /**
     * The icon name to be displayed.
     * 
     * Refer to the documentation of the facade for supported icon names. Most
     * facades will support font awesome icons and some poprietary icon set
     * additionally.
     * 
     * @uxon-property icon
     * @uxon-type icon|string
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIcon()
     */
    public function setIcon(string $value) : iHaveIcon
    {
        $this->icon = $value;
        return $this;
    }    
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getShowIcon()
     */
    public function getShowIcon(bool $default = null) : ?bool
    {
        return $this->showIcon ?? $default;
    }
    
    /**
     * Force the icon to show (TRUE) or hide (FALSE)
     * 
     * The default depends on the facade used.
     * 
     * @uxon-property show_icon
     * @uxon-type boolean 
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setShowIcon()
     */
    public function setShowIcon(bool $value) : iHaveIcon
    {
        $this->showIcon = $value;
        return $this;
    }
}