<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveSidebar;
use exface\Core\Widgets\Sidebar;

/**
 * Trait to implement iHaveSidebar interface
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveSidebarTrait {
    
    private ?Sidebar $sidebar = null;

    /**
     * Gives the widget a sidebar for secondary content like an AI chat, comments or similar
     *
     * @uxon-property sidebar
     * @uxon-type \exface\Core\Widgets\Sidebar
     * @uxon-template {"widgets": [{"": ""}]}
     *
     * @param UxonObject|Sidebar $uxonOrWidget
     * @return iHaveSidebar
     * @throws WidgetConfigurationError
     */
    public function setSidebar(UxonObject|Sidebar $uxonOrWidget) : iHaveSidebar
    {
        if ($uxonOrWidget instanceof UxonObject) {
            $this->sidebar = WidgetFactory::createFromUxon($this->getPage(), $uxonOrWidget, $this, 'Sidebar');
        } elseif ($uxonOrWidget instanceof Sidebar) {
            $this->sidebar = $uxonOrWidget;
        } else {
            throw new WidgetConfigurationError($this, 'Invalid definiton of dialog sidebar given!');
        }
        return $this;
    }

    /**
     * @return Sidebar
     */
    public function getSidebar() : Sidebar
    {
        return $this->sidebar;
    }

    /**
     *
     * @return bool
     */
    public function hasSidebar() : bool
    {
        return $this->sidebar !== null;
    }
}