<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\DataTypes\StringDataType;

trait iHaveIconTrait {
    
    private $icon = null;
    
    private $iconSet = null;
    
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
     * The name of the icon to be displayed in buttons, menus, etc.
     * 
     * Refer to the documentation of the facade for supported icon names. Most
     * facades will support font awesome icons, SVG icons and some poprietary icon 
     * sets additionally (like UI5 icons in the UI5Facade). 
     * 
     * Please set the correct `icon_set` if using icons other than the default Font Awesome.
     * 
     * You can search for icons here:
     * 
     * - [Font Awesome 4 icons](https://fontawesome.com/v4/icons/)
     * - [SVG Material Design icons](https://pictogrammers.com/library/mdi/)
     * - [UI5 icon explorer (only for UI5!)](https://sapui5.hana.ondemand.com/sdk/test-resources/sap/m/demokit/iconExplorer/webapp/index.html#/overview/SAP-icons/?tab=grid)
     * 
     * The ability to use SVG icons offers a lot of flexibility as most icon sets include
     * SVGs. Make sure to use icons from a single icon set or at least similarly looking
     * sets to ensure a consistent look of your app.
     * 
     * Here is an example for an SVG icon:
     * 
     * ```
     *  {
     *      "icon_set": "svg",
     *      "icon": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><title>file-tree</title><path d=\"M3,3H9V7H3V3M15,10H21V14H15V10M15,17H21V21H15V17M13,13H7V18H13V20H7L5,20V9H7V11H13V13Z\" /></svg>"
     *  }
     *  
     * ```
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