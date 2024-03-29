<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\ConditionalProperty;

interface iCanBeDisabled extends WidgetInterface
{

    /**
     * Returns TRUE if the widget is disabled (= no user interaction) and FALSE otherwise
     *
     * @return boolean
     */
    public function isDisabled() : ?bool;

    /**
     * Disables the widget when set to TRUE and enables it with FALSE.
     * Users cannot interact with disabled widgets,
     * but other widgets can. Disabled widgets also deliver data to actions. To prevent this, make the widget
     * readonly.
     *
     * @param bool|NULL $trueOrFalseOrNull   
     * @param string $reason
     *          
     * @return WidgetInterface
     */
    public function setDisabled(?bool $trueOrFalseOrNull, string $reason = null) : WidgetInterface;
    
    /**
     * 
     * @param UxonObject $uxon
     * @return WidgetInterface
     */
    public function setDisabledIf(UxonObject $uxon) : WidgetInterface;
    
    /**
     * 
     * @return ConditionalProperty|NULL
     */
    public function getDisabledIf() : ?ConditionalProperty;
}