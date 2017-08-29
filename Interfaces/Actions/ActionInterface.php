<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;

interface ActionInterface extends ExfaceClassInterface, AliasInterface, iCanBeCopied
{

    /**
     *
     * @return string
     */
    public function getId();

    /**
     *
     * @return AppInterface
     */
    public function getApp();

    /**
     *
     * @return string
     */
    public function getIconName();

    /**
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setIconName($value);

    /**
     * Sets the alias of the action.
     * The alias must be unique within the app!
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setAlias($value);

    /**
     * Returns the widget, that called the action (typically a button) or the
     * widget that will call the action if it was not called yet.
     * 
     * May return null if the calling widget is not known.
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
     * @return WidgetInterface|null 
     */
    public function getCalledByWidget();

    /**
     * Sets the widget, that called the action: either taking an instantiated 
     * widget object or a widget link (text, uxon or object)
     *
     * @param
     *            AbstractWidget || WidgetLink || string $widget_or_widget_link
     * @return ActionInterface
     */
    public function setCalledByWidget($widget_or_widget_link);

    /**
     *
     * @return ActionInterface[]
     */
    public function getFollowupActions();

    /**
     *
     * @param ActionInterface[] $actions_array            
     */
    public function setFollowupActions(array $actions_array);

    /**
     *
     * @param ActionInterface $action            
     * @return ActionInputInterface
     */
    public function addFollowupAction(ActionInterface $action);

    /**
     * Returns the resulting data sheet.
     * Performs the action if it had not been performed yet.
     *
     * @return DataSheetInterface
     */
    public function getResultDataSheet();

    /**
     * Returns the result of the action - whatever it is.
     * What data type is returned depends on the specific action implementation.
     * In any case, get_result() makes sure, the action is performed.
     * In contrast to get_result_data_sheet(), the get_result() methods can return anything. While get_result_data_sheet() is important
     * for concatennation of actions and actually performing them, the output is whatever the user actually sees and, perhaps even more importantly,
     * whatever is compared to there reference when testing actions.
     *
     * @return mixed
     */
    public function getResult();

    /**
     * Returns a string representing the result object of the action (= a string version of get_result())
     *
     * @return string
     */
    public function getResultStringified();

    /**
     * Returns a printable version of the result: HTML or text if the result is a widget, UXON for data sheets, etc.
     * By default, it's the UXON of the result data sheet
     *
     * @return string
     */
    public function getResultOutput();

    /**
     * Returns a human readable message, describing, what the action has done.
     *
     * @return string
     */
    public function getResultMessage();

    /**
     * Sets the data sheet, the action is supposed to be performed upon.
     *
     * @param DataSheetInterface||UxonObject||string $data_sheet_or_uxon
     * 
     * @throws ActionInputError if the passed input data is of an unsupported type
     * 
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setInputDataSheet($data_sheet_or_uxon);

    /**
     * Returns a copy of the data sheet, the action is performed upon.
     * 
     * This is what the action logic uses as input. By default input mappers are
     * automatically applied - to get the raw input data, that was originally
     * passed to the action, set the parameter $apply_mappers to FALSE.
     *
     * @param boolean $apply_mappers
     * 
     * @return DataSheetInterface
     */
    public function getInputDataSheet($apply_mappers = true);
    
    /**
     * @return DataSheetMapperInterface[]
     */
    public function getInputMappers();
    
    /**
     *
     * @param DataSheetMapperInterface[]|UxonObject[] $data_sheet_mappers_or_uxon_objects
     * @return ActionInterface
     */
    public function setInputMappers(array $data_sheet_mappers_or_uxon_objects);
    
    /**
     * 
     * @param DataSheetMapperInterface $mapper
     * @return ActionInterface
     */
    public function addInputMapper(DataSheetMapperInterface $mapper);

    /**
     * Returns the minimum number of rows the action expects in the input data sheet.
     *
     * @return integer
     */
    public function getInputRowsMin();

    /**
     *
     * @param integer $value            
     * @return ActionInterface
     */
    public function setInputRowsMin($value);

    /**
     * Returns the maximum number of rows the action expects in the input data sheet.
     *
     * @return integer
     */
    public function getInputRowsMax();

    /**
     *
     * @param integer $value            
     * @return ActionInterface
     */
    public function setInputRowsMax($value);

    /**
     * Returns the meta object, the action is performed upon.
     * The meta object is generally determined
     * from the input data sheet, because this is the data the action is performed with. If not input
     * data is set, the meta object of the calling widget is used because it is most likely, that it's
     * data will be the input (i.e. after an ajax-request).
     *
     * @throws ActionObjectNotSpecifiedError if neither input data nor calling widget are known
     * @return Object
     */
    public function getMetaObject();

    /**
     *
     * @param Object $object            
     * @return ActionInterface
     */
    public function setMetaObject(Object $object);

    /**
     *
     * @param string $qualified_alias            
     * @return ActionInputInterface
     */
    public function setObjectAlias($qualified_alias);

    /**
     *
     * @param string $interface            
     * @return boolean
     */
    public function implementsInterface($interface);

    /**
     * Returns TRUE, if the action can be undone or FALSE otherwise.
     * An action may override this method for a more complex algorithm
     * to determine, if it can be undone. In particular, the result may vary depending on the current application state: a complex action
     * may become not undoable after performing som checks on the actual data, while it would be undoable by default.
     *
     * @return boolean
     */
    public function isUndoable();

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setUndoable($value);

    /**
     * Returns TRUE, if the action modifies data in a data source or FALSE otherwise.
     * By default all actions capable of modifying data return TRUE,
     * but the flag may change, if there had been no data actually modified while performing the action. Assuming TRUE if a data modification is
     * possible, makes sure, no modifications actually remains undiscovered because of developers forgetting to set the appropriate flag of an action.
     *
     * @return boolean
     */
    public function isDataModified();

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setDataModified($value);

    /**
     *
     * @param array $behavior_aliases            
     */
    public function setDisabledBehaviors(array $behavior_aliases);

    /**
     *
     * @return array
     */
    public function getDisabledBehaviors();

    /**
     *
     * @return UiPageInterface
     */
    public function getCalledOnUiPage();

    /**
     *
     * @return TemplateInterface
     */
    public function getTemplate();

    /**
     *
     * @return string
     */
    public function getTemplateAlias();

    /**
     *
     * @param string $value            
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setTemplateAlias($value);

    /**
     * Returns the default name of the action translated to the currently used locale.
     *
     * @return string
     */
    public function getName();

    /**
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setName($value);

    /**
     * Returns TRUE if the action has a name or a name translation key translatable in the current language and FALSE otherwise
     *
     * @return boolean
     */
    public function hasName();

    /**
     * Returns the data transaction, the action runs in.
     * Most action should run in a single transactions, so it is a good
     * practice to use the action's transaction for all data source operations. If not transaction was set explicitly
     * via set_transaction(), a new transaction will be started automatically.
     *
     * @return DataTransactionInterface
     */
    public function getTransaction();

    /**
     * Sets the main data transaction used in this action.
     *
     * @param DataTransactionInterface $transaction            
     * @return ActionInterface
     */
    public function setTransaction(DataTransactionInterface $transaction);

    /**
     * Returns TRUE if the action will perform a commit on it's data transaction after it was performed and if data was modified.
     *
     * @return boolean
     */
    public function getAutocommit();

    /**
     * Set to FALSE to force the action not to perform a commit on it's data transaction after the action had been performed.
     * By default, the action will commit the transaction automatically.
     *
     * @param boolean $true_or_false            
     * @return ActionInterface
     */
    public function setAutocommit($true_or_false);

    /**
     * Returns the text for the result message if one was set in the UXON description of the action and NULL otherwise.
     *
     * @return string
     */
    public function getResultMessageText();

    /**
     * Overrides the auto-generated result message with the given text.
     * The text can contain placeholders.
     *
     * Placeholders can be used for any column in the result data sheet of this action: e.g. for a CreateObject action
     * a the follwoing text could be used: "Object [#LABEL#] with id [#UID#] created". If the result sheet contains
     * multiple rows, the message text will be repeated for every row with the placeholders being replaced from that
     * row.
     *
     * @uxon-property result_message_text
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\CommonLogic\AbstractAction
     */
    public function setResultMessageText($value);
    
    /**
     * Returns TRUE if this action matches the given alias and FALSE otherwise.
     * 
     * @return boolean
     */
    public function is($action_or_alias);
}

?>
