<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Common interface for input widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iTakeInput extends iCanBeRequired, iCanBeDisabled
{

    /**
     * Returns TRUE if the widget is read only (= just showing something, but being ignored by most actions) and FALSE otherwise.
     *
     * @return boolean
     */
    public function isReadonly();

    /**
     * Makes the widget readonly when set to TRUE.
     * 
     * Similarly to disabled widgets, users cannot interact with read-only widgets directly. But while the value of a
     * diabled widget ist still passed to actions, read-only widgets are completely ignored when gathering data for 
     * action's input or prefills - similarly to widgets with display_only = true, but without any user interaction.
     *
     * @param boolean $true_or_false            
     * @return WidgetInterface
     */
    public function setReadonly($true_or_false);

    /**
     * Returns TRUE if the widget is display-only (= interactive, but being ignored by most actions) and FALSE otherwise.
     *
     * @return boolean
     */
    public function isDisplayOnly();

    /**
     * Makes the widget display-only if set to TRUE.
     *
     * @param boolean $true_or_false            
     * @return iTakeInput
     */
    public function setDisplayOnly($true_or_false);
}