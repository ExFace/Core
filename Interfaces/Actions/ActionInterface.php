<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

interface ActionInterface extends ExfaceClassInterface, AliasInterface, iCanBeCopied
{
    
    /**
     * 
     * @param TaskInterface $task
     * @return TaskResultInterface
     */
    public function handle(TaskInterface $task) : TaskResultInterface;

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
    public function getIcon();

    /**
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setIcon($value);

    /**
     * Sets the alias of the action.
     * The alias must be unique within the app!
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setAlias($value);

    /**
     * Returns the widget, in which the action is defined - typically a button.
     * 
     * TODO update docs to show difference with TaskInterface::getWidgetTriggeredBy()
     *
     * @return WidgetInterface|null 
     */
    public function getWidgetDefinedIn();

    /**
     * Sets the widget, that called the action: either taking an instantiated 
     * widget object or a widget link (text, uxon or object)
     *
     * @param WidgetInterface||WidgetLinkInterface||string $widget_or_widget_link
     * @return ActionInterface
     */
    public function setWidgetDefinedIn($widget_or_widget_link);

    /**
     * Sets the data sheet, the action is supposed to be performed upon.
     *
     * @param DataSheetInterface||UxonObject||string $data_sheet_or_uxon
     * 
     * @throws ActionInputError if the passed input data is of an unsupported type
     * 
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setInputDataSheetPreset($data_sheet_or_uxon);

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
    public function getInputDataSheetPreset($apply_mappers = true);
    
    /**
     * @return DataSheetMapperInterface[]
     */
    public function getInputMappers();
    
    /**
     *
     * @param UxonObject $uxon
     * @return ActionInterface
     */
    public function setInputMappers(UxonObject $uxon);
    
    /**
     *
     * @param UxonObject $uxon
     * @return ActionInterface
     */
    public function setInputMapper(UxonObject $uxon);
    
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
     * @return MetaObjectInterface
     */
    public function getMetaObject();

    /**
     *
     * @param MetaObjectInterface $object            
     * @return ActionInterface
     */
    public function setMetaObject(MetaObjectInterface $object);

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
     *
     * @param array $behavior_aliases            
     */
    public function setDisabledBehaviors(UxonObject $behavior_aliases);

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
     * Returns TRUE if the action will perform a commit on a task's data transaction after it was performed and if data was modified.
     *
     * @return boolean
     */
    public function getAutocommit();

    /**
     * Set to FALSE to force the action not to perform a commit on a taks's data transaction after the action had been performed.
     * By default, the action will commit the transaction automatically.
     *
     * @param boolean $true_or_false            
     * @return ActionInterface
     */
    public function setAutocommit($true_or_false);
    
    /**
     * Returns TRUE if this action matches the given alias or inherits for the action identified by it.
     * 
     * @param ActionInterface|string $action_or_alias
     * @return boolean
     */
    public function is($action_or_alias);
    
    /**
     * Returns TRUE if this action matches the given alias and FALSE otherwise.
     * 
     * @param ActionInterface|string $action_or_alias
     * @return boolean
     */
    public function isExactly($action_or_alias);
}

?>
