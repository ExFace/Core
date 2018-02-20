<?php
namespace exface\Core\Interfaces\Api;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;

interface TaskResultInterface extends ExfaceClassInterface
{
    
    public function __construct(TaskInterface $task);

    /**
     * @return TaskInterface
     */
    public function getTask() : TaskInterface;    
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getData() : DataSheetInterface;
    
    /**
     * 
     * @return string
     */
    public function getMessage() : string;
    
    /**
     * 
     * @return bool
     */
    public function isUndoable() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return TaskResultInterface
     */
    public function setUndoable(bool $trueOrFalse) : TaskResultInterface;
    
    /**
     * Returns TRUE, if the action modifies data in a data source or FALSE otherwise.
     * By default all actions capable of modifying data return TRUE,
     * but the flag may change, if there had been no data actually modified while performing the action. Assuming TRUE if a data modification is
     * possible, makes sure, no modifications actually remains undiscovered because of developers forgetting to set the appropriate flag of an action.
     *
     * @return bool
     */
    public function isDataModified() : bool;
    
    /**
     *
     * @param bool $value
     * @return TaskResultInterface
     */
    public function setDataModified(bool $value) : TaskResultInterface;
    
    /**
     * Returns the widget, that triggered the task - typically a button.
     *
     * TODO update docs
     *
     * NOTE: if the action was not really called yet, this method returns the
     * widget, that instantiated the action: i.e. the first button on the page,
     * that will call this action.
     *
     * IDEA Returning NULL in certain cases does not feel right. We had to add
     * the called_by_widget() method to be able to determine the meta_object
     * of the dialog even if the action does not have an input data sheet yet
     * (when drawing the dialog in ajax templates). At that point, the action
     * does not know, what object it is going to be performed upon. I don't feel
     * comfortable with this solution though, since called_by_widget will be
     * null when performing the action via AJAX (or the entire page would need
     * to be instantiated).
     *
     * Here are the choices I had:
     *
     * - I could create the Dialog when the action is really called an import
     * the entire dialog via AJAX.
     *
     * - I could also pass the meta object as a separate parameter to the action:
     * $action->set_target_meta_object() - may be a good idea since an action
     * could also have a built it meta_object, which should not be overridden
     * or action->set_called_by_widget - enables the action to create widgets
     * with real parents, but produces overhead whe called via AJAX and is not
     * needed for actions within workflows (or is it?)
     *
     * @return WidgetInterface
     */
    public function getWidgetTriggeredBy() : WidgetInterface;
    
    /**
     * Sets the widget, that called the action: either taking an instantiated
     * widget object or a widget link (text, uxon or object)
     *
     * @param WidgetInterface||string $widget_or_widget_link
     * @return TaskInterface
     */
    public function setWidgetTriggeredBy($widget_or_widget_link) : TaskInterface;
}