<?php

namespace exface\Core\Interfaces\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Menu;

/**
 * This interface must be implemented by widgets that have a menu: e.g.
 * a context menu, a dropdown-menu, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iHaveMenu extends iHaveChildren
{

    /**
     * Returns the menu widget
     *
     * @return Menu
     */
    public function getMenu();

    /**
     * Replaces the menu widget by the given instantiated widget or UXON description object
     *
     * @param UxonObject|UxonObject[]|Menu $value            
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\MenuButton
     */
    public function setMenu($menu_widget_or_uxon_or_array);
}