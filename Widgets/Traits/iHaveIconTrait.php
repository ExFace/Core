<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\DataTypes\StringDataType;

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
     * The name of the icon to be displayed.
     * 
     * Refer to the documentation of the facade for supported icon names. Most
     * facades will support font awesome icons and some poprietary icon set
     * additionally (like UI5 icons in the UI5Facade).
     * 
     * You can search for icons here:
     * 
     * - [Font Awesome 4 icons](https://fontawesome.com/v4/icons/)
     * - [UI5 icon explorer (only for UI5!)](https://sapui5.hana.ondemand.com/sdk/test-resources/sap/m/demokit/iconExplorer/webapp/index.html#/overview/SAP-icons/?tab=grid)
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
        if ($this->iconSet === null && $this->icon !== null) {
            $icon = $this->getIcon();
            if (StringDataType::startsWith($icon, '<svg')) {
                return iHaveIcon::ICON_SET_SVG;
            }
            /* IDEA Not sure, if the core should determine font awesome. SVG is pretty
             * obvious, but FA might be subject to facade individuality.
            $firstThree = mb_strtolower(mb_substr($icon, 0, 3));  
            switch ($firstThree) {
                case 'fa ':
                case 'fa-':
                    return iHaveIcon::ICON_SET_FONT_AWESOME;
            }*/
        }
        return $this->iconSet;
    }
    
    /**
     * Which icon set to use (if not set, the facade's default will be used)
     *
     * @uxon-property icon_set
     * @uxon-type string
     * @uxon-default fa
     *
     * @see iHaveIcon::setIconSet()
     */
    public function setIconSet(string $iconSetCode) : iHaveIcon
    {
        $this->iconSet = $iconSetCode;
        return $this;
    }
}