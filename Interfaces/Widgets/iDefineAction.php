<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface iDefineAction extends WidgetInterface
{
    /**
     * Sets the action of the button.
     * Accepts either instantiated actions or respective UXON description objects.
     * Passing ready made actions comes in handy, when creating an action in the code, while passing UXON objects
     * is an elegant solutions when defining a complex button in UXON:
     *  { 
     *      widget_type: Button,
     *      action: {
     *          alias: ...,
     *          other_params: ...
     *      }
     *  }
     *
     * @param ActionInterface|UxonObject $action_object_or_uxon_description            
     * @throws WidgetPropertyInvalidValueError
     */
    public function setAction($action_or_uxon_description);
}