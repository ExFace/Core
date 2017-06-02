<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

interface iShowWidget extends iNavigate, iUsePrefillData
{

    /**
     *
     * @return WidgetInterface
     */
    public function getWidget();

    /**
     *
     * @param WidgetInterface|UxonObject|string $any_widget_source            
     */
    public function setWidget($any_widget_source);

    /**
     * The output of an action showing a widget is the widget instance
     *
     * @return WidgetInterface
     */
    public function getResult();
    
    /**
     * Returns TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise
     *
     * @return boolean
     */
    public function getPrefillWithInputData();
    
    /**
     * Set to TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise.
     *
     * @param boolean $value
     * @return iShowWidget
     */
    public function setPrefillWithInputData($true_or_false);
    
    /**
     * Returns FALSE, if the values of the currently registered context filters should be used to attempt to prefill the widget
     *
     * @return boolean
     */
    public function getPrefillWithFilterContext();
    
    /**
     * If set to TRUE, the values of the filters registered in the window context scope will be used to prefill the widget (if possible)
     *
     * @param boolean $value
     * @return iShowWidget
     */
    public function setPrefillWithFilterContext($true_or_false);
    
    /**
     * Disables the prefill for this action entirely if TRUE is passed.
     *
     * @return iShowWidget
     */
    public function setDoNotPrefill($value);
}