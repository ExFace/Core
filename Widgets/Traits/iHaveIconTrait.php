<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveIcon;

trait iHaveIconTrait {
    
    private $icon = null;
    
    private $iconSet = null;
    
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
        // If show_icon is not set explicitly, set it to true when specifying an icon.
        // Indeed, if the user specifies and icon, it is expected to be show, isn't it?
        if ($this->showIcon === null) {
            $this->showIcon = true;
        }
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
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveIcon::getIconSet()
     */
    public function getIconSet() : ?string
    {
        return $this->iconSet;
    }
    
    /**
     * Which icon set to use (if not set, the facade's default will be used)
     *
     * @uxon-property icon-set
     * @uxon-type string
     * @uxon-default fa
     *
     * @see iHaveIcon::setIconSet()
     */
    public function setIconSet(string $iconSetCode) : iHaveIcon
    {
        return $this->iconSet;
    }
}