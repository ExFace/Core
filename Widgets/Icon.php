<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\NumberEnumDataType;

/**
 * This widget shows an icon - either a static one or derived from an attribute's value.
 * 
 * ## Examples
 * 
 * A static icon:
 * 
 * ```
 * {
 *  "widget_type": "Icon",
 *  "icon": "battery-full"
 * }
 * 
 * ```
 * 
 * An icon for an attribute, that contains it's name (i.e. our object has the attribute
 * `icon_name`):
 * 
 * ```
 * {
 *  "widget_type": "Icon",
 *  "attribute_alias": "icon_name"
 * }
 * 
 * ```
 * 
 * An icon derived from attribute values through a mapping.
 * 
 * ```
 * {
 *  "widget_type": "Icon",
 *  "attribute_alias": "battery_percentage",
 *  "icon_map": {
 *      0: "battery-empty",
 *      25: "battery-quater",
 *      50: "battery-half",
 *      100: "battery-full"
 *  }
 * }
 * 
 * ```
 * 
 * A display widget with an icon (user name with a user icon)
 * 
 * ```
 * {
 *  "widget_type": "Icon",
 *  "icon": "user",
 *  "value_widget": {
 *      "attribute_alias": "user__name"
 *  }
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class Icon extends Display implements iHaveColor
{
    private $iconSize = null;
    
    private $iconPosition = EXF_ALIGN_LEFT;
    
    private $iconsMap = null;

    private $color = null;
    
    private $valueWidget = null;
    
    private $valueWidgetUxon = null;
    /**
     * 
     * @return string|NULL
     */
    public function getIconSize() : ?string
    {
        return $this->iconSize;
    }
    
    /**
     * Sets the size of the icon (SMALL, NORMAL, BIG)
     * 
     * @uxon-property icon_size
     * @uxon-type [small,normal,big]
     * @uxon-default normal
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Icon
     */
    public function setIconSize($value) : Icon
    {
        $this->iconSize = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getIconPosition() : string
    {
        return $this->iconPosition;
    }
    
    /**
     * Sets the position of the icon relativ to the caption/value (LEFT, RIGHT, CENTER).
     * 
     * The default setting depends on the facade used.
     * 
     * @uxon-property icon_position
     * @uxon-type [left,right,center]
     * 
     * @param string $value
     * @return Icon
     */
    public function setIconPosition(string $value) : Icon
    {
        $this->iconPosition = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (! is_null($this->iconSize)) {
            $uxon->setProperty('icon_size', $this->iconSize);
        }
        if (! is_null($this->iconAlignment)) {
            $uxon->setProperty('icon_alignment', $this->iconAlignment);
        }
        if (! is_null($this->icon)) {
            $uxon->setProperty('icon', $this->icon);
        }
        if (! is_null($this->color)) {
            $uxon->setProperty('color', $this->color);
        }
        return $uxon;
    }
    
    /**
     * Returns the color of the text or NULL if no color explicitly defined.
     * 
     * {@inheritdoc}
     * @see iHaveColor::getColor()
     */
    public function getColor()
    {
        return $this->color;
    }
    
    /**
     * Sets a specific color for the text - if not set, facades will use their own color scheme.
     *
     * HTML color names are supported by default. Additionally any color selector supported by
     * the current facade can be used. Most HTML facades will support css colors.
     *
     * @link https://www.w3schools.com/colors/colors_groups.asp
     *
     * @uxon-property color
     * @uxon-type color|string
     *
     * {@inheritdoc}
     * @see iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->color = $color;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getIcon() : ?string
    {
        return $this->getValue();
    }
    
    /**
     * The name of the icon to be displayed.
     * 
     * Refer to the documentation of the facade for supported icon names. Most
     * facades will support font awesome icons and some poprietary icon set
     * additionally.
     * 
     * @uxon-property icon
     * @uxon-type icon|string
     * 
     * @param string $value
     * @return Icon
     */
    public function setIcon(string $value) : Icon
    {
        return $this->setValue($value);
    }
    
    /**
     *
     * @return Value
     */
    public function getValueWidget() : Value
    {
        if ($this->valueWidget === null) {
            if ($this->valueWidgetUxon !== null) {
                $this->valueWidget = WidgetFactory::createFromUxonInParent($this, $this->valueWidgetUxon, 'Display');
            } else {
                throw new WidgetChildNotFoundError($this, 'Value widget not specified for "' . $this->getWidgetType() . '"!');
            }
        }
        return $this->valueWidget;
    }
    
    /**
     * Display a value widget next to the icon.
     * 
     * @uxon-property value_widget
     * @uxon-type \exface\Core\Widgets\Value
     * @uxon-template {"widget_type": ""}
     * 
     * @param Value $value
     * @return Icon
     */
    public function setValueWidget(UxonObject $uxon) : Icon
    {
        $this->valueWidgetUxon = $uxon;
        $this->valueWidget = null;
        return $this;
    }
    
    
    public function hasValueWidget() : bool
    {
        return $this->valueWidget !== null || $this->valueWidgetUxon !== null;
    }
    
    /**
     *
     * @return array
     */
    public function getIconsMap() : array
    {
        return $this->iconsMap;
    }
    
    /**
     * Map values (e.g. numbers or strings) to icon names.
     * 
     * For example, a battery icon filled depending on a percentage attribute.
     * 
     * ```
     * {
     *  "widget_type": "Icon",
     *  "attribute_alias": "battery_percentage",
     *  "icon_map": {
     *      0: "battery-empty",
     *      25: "battery-quater",
     *      50: "battery-half",
     *      100: "battery-full"
     *  }
     * }
     * 
     * ```
     * 
     * @uxon-property icons_map
     * @uxon-type array
     * @uxon-template {"0": "battery-empty", "50": "battery-half", "100": "battery-full"}
     * 
     * @param UxonObject $valueIconPairs
     * @return Icon
     */
    public function setIconsMap(UxonObject $valueIconPairs) : Icon
    {
        $this->iconsMap = $valueIconPairs->toArray();
        return $this;
    }
    
}