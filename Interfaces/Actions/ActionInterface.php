<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\TaskHandlerInterface;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Widgets\iHaveIcon;

/**
 * Common interface for all actions.
 * 
 * Actions represent, as the name suggests, anything the user can request the plattfrom to do.
 * Actions handle tasks. Once an action is performed upon a task, it must return a TaksResult.
 * 
 * Actions can pass tasks to other actions, thus forming chains or entire workflows - from this
 * point of view, they are comparable to a command bus, where actions would be command handlers
 * tasks would be the commands with the important difference, that a task can represent a 
 * command as well as a query (in CQRS) and allways causes the handler to return something.
 * 
 * Actions are part of the metamodel, but they are based on coded prototypes (PHP classes), so
 * the model of an action actually configures it's prototype. Action models can either be placed
 * within an object's model (object actions) or inside the UI model as properties of the
 * corresponding trigger widget (e.g. Button). Of course, action models can also be created
 * programmatically via UXON or using the action's interfaces in OOP style directly.
 * 
 * @see TaskInterface
 * @see ResultTextContentInterface
 * 
 * @author Andrej Kabachnik
 *
 */
interface ActionInterface extends 
    WorkbenchDependantInterface, 
    AliasInterface, 
    iCanBeCopied, 
    iCanBeConvertedToUxon, 
    TaskHandlerInterface, 
    iCanGenerateDebugWidgets,
    iHaveIcon
{
    
    /**
     * 
     * @param TaskInterface $task
     * @param DataTransactionInterface $transaction
     * 
     * @triggers \exface\Core\Events\Action\OnBeforeActionPerformedEvent
     * @triggers \exface\Core\Events\Action\OnActionPerformedEvent
     * 
     * @return ResultInterface
     */
    public function handle(TaskInterface $task, DataTransactionInterface $transaction = null) : ResultInterface;

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
     * Sets the alias of the action.
     * The alias must be unique within the app!
     *
     * @param string $value            
     * @return ActionInterface
     */
    public function setAlias($value);

    /**
     * Returns the widget, where this action was instantiated or throws WidgetNotFound exception.
     * 
     * Use isDefinedInWidget() to check, if the action was defined in a widget without raising
     * exceptions.
     * 
     * @see isDefinedInWidget()
     *
     * @throws WidgetNotFoundError
     * 
     * @return WidgetInterface 
     */
    public function getWidgetDefinedIn() : WidgetInterface;

    /**
     * Sets the widget defining the action.
     * 
     * @see isDefinedInWidget()
     * 
     * @param WidgetInterface
     * @return ActionInterface
     */
    public function setWidgetDefinedIn(WidgetInterface $widget) : ActionInterface;
    
    /**
     * Returns TRUE if the action is instantiated within a widget and FALSE otherwise
     * (e.g. actions originating from API calls or being instantiated programmatically).
     * 
     * NOTE: the widget, that instantiated the action is not neccesarily the one that 
     * actually called it - it's the widget, that configured it. In other words, the one, 
     * that defines the action's model. Other widgets may than call the action by using
     * widget links, etc. 
     * 
     * All actions, that originate from the UI, are instantiated as part of the 
     * definition of Buttons, Menus and other widgets, that can trigger actions. 
     * These actions "know" their parent widgets and have access to them even before 
     * they are not actually called. This enables actions to inherit various properties
     * of their defining widget - e.g. the action ShowObjectEditDialog knows what object it
     * is going to be editing even if it is not explicitly defined in the action's UXON
     * because it simply inherits it from it's widget.
     * 
     * NOTE: Use TaskInterface::getWidgetTriggeredBy() to get the widget, that actually called
     * the action. In contrast to the defining widget, the actuall trigger widget is only
     * known at the moment when the action has actually being performed. 
     * 
     * There are also cases, when actions are called without a widget even being 
     * involved (e.g. via API). In this case, the action does not have a trigger widget and 
     * even the task being handled might not have an origin widget.
     * 
     * @return bool
     */
    public function isDefinedInWidget() : bool;

    /**
     * Sets preset input data for the action.
     * 
     * The preset will be merged with the task input data when the action is performed
     * or used as input data if the task will not provide any data.
     *
     * @param DataSheetInterface $dataSheet
     * 
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setInputDataPreset(DataSheetInterface $dataSheet) : ActionInterface;
    
    /**
     * Sets preset input data for the action.
     * 
     * Technically the same as setInputDataPreset(), but takes a UXON model of
     * a data sheet as input. Additionally this method provides a better
     * understandable UXON property input_data_sheet to use in UXON models.
     * 
     * @param UxonObject $uxon
     * @return ActionInterface
     */
    public function setInputDataSheet(UxonObject $uxon) : ActionInterface;

    /**
     * Returns a copy of the data sheet, the action is performed upon.
     * 
     * This is what the action logic uses as input. By default input mappers are
     * automatically applied - to get the raw input data, that was originally
     * passed to the action, set the parameter $apply_mappers to FALSE.
     *
     * @return DataSheetInterface
     */
    public function getInputDataPreset() : DataSheetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasInputDataPreset() : bool;
    
    /**
     * 
     * @return DataSheetMapperInterface[]
     */
    public function getInputMappers() : array;
    
    /**
     * Returns the mapper, that will be used for the given input object or NULL if no mapper is defined.
     * 
     * @param MetaObjectInterface $fromObject
     * @return DataSheetMapperInterface|NULL
     */
    public function getInputMapper(MetaObjectInterface $fromObject) : ?DataSheetMapperInterface;
    
    /**
     * Returns TRUE if there is at least one input mapper defined for this action and FALSE otherwise.
     * 
     * @return bool
     */
    public function hasInputMappers() : bool;
    
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
     * Force input data to be based on given meta object or a derivative.
     * 
     * Attempting to perform the action upon data of another object will result in an error.
     * You can use `input_mappers` to map input data to the correct object.
     * 
     * By default, an action accepts data of any object and attempts to deal with it.
     * Many of the core actions are actually agnostic to objects.
     * 
     * @param string $aliasWithNamespace
     * @return ActionInterface
     */
    public function setInputObjectAlias(string $aliasWithNamespace) : ActionInterface;
    
    /**
     * Force the result of the action to be based on given meta object or a derivative.
     * 
     * If performing the action results in another object, it will produce an error.
     * 
     * By default, an action does not check the result object.
     * 
     * @param string $aliasWithNamespace
     * @return ActionInterface
     */
    public function setResultObjectAlias(string $aliasWithNamespace) : ActionInterface;

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
     * @return ActionInterface
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
     * 
     * @return string|NULL
     */
    public function getHint() : ?string;
    
    /**
     * 
     * @param string $text
     * @return ActionInterface
     */
    public function setHint(string $text) : ActionInterface;

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
     * @param ActionInterface|ActionSelectorInterface|string $actionOrSelectorOrString
     * @throws UnexpectedValueException
     * @return bool
     */
    public function is($actionOrSelectorOrString) : bool;
    
    /**
     * Returns TRUE if this action matches the given alias and FALSE otherwise.
     * 
     * @param ActionInterface|ActionSelectorInterface|string $actionOrSelectorOrString
     * @throws UnexpectedValueException
     * @return bool
     */
    public function isExactly($actionOrSelectorOrString) : bool;

    /**
     * Returns the text for the result message if one was set in the UXON description of the action and NULL otherwise.
     *
     * @return string|NULL
     */
    public function getResultMessageText() : ?string;
    
    /**
     * Overrides the auto-generated result message with the given text.
     * The text can contain placeholders.
     *
     * Placeholders can be used for any column in the result data sheet of this action: 
     * e.g. for a CreateObject action a the follwoing text could be used: "Object [#LABEL#] 
     * with id [#UID#] created". If the result sheet contains multiple rows, the message text 
     * will be repeated for every row with the placeholders being replaced from that row.
     *
     * @param string $value
     * @return \exface\Core\CommonLogic\AbstractAction
     */
    public function setResultMessageText($value);
    
    /**
     * Returns the selector for this action.
     * 
     * @return ActionSelectorInterface
     */
    public function getSelector() : ActionSelectorInterface;
    
    /**
     * Returns TRUE if the action is allowed for the given (or currently loggen on) user and FALSE otherwise.
     * 
     * @param UserImpersonationInterface $userOrToken
     * @return bool
     */
    public function isAuthorized(UserImpersonationInterface $userOrToken = null) : bool;
    
    /**
     * Returns TRUE or FALSE if the action requires a trigger widget in the task or not; NULL if not explicitly defined.
     * 
     * Depending on their internal logic action may or may not require a trigger widget.
     * Some actions like `exface.Core.ShowWidget` will require a trigger only on certain
     * occasions - e.g. if no widget is explicitly defined within the action in our case.
     * 
     * Implementing this method gives the developer the possibility to control this
     * behavior.
     * 
     * @return bool|NULL
     */
    public function isTriggerWidgetRequired() : ?bool;
    
    /**
     * Allows to change `isTriggerWidgetRequired()` at runtime. 
     * 
     * This is only possible if the action's method `isTriggerWidgetRequired()` would return
     * NULL or the same value. In other words, you can only change the setting at runtime if
     * it was not hardcoded previously. You can also only chage it once! This is understandable
     * as otherwise the action's internal logic could be overridden from outside.
     * 
     * Requiring (or not) a trigger at runtime is usefull whenever multiple action depend on
     * each-other - e.g. in action chains, workflows or behavoirs like `iCallAction`.
     * 
     * @param bool $trueOrFalse
     * @throws ActionRuntimeError
     * @return ActionInterface
     */
    public function setInputTriggerWidgetRequired(bool $trueOrFalse) : ActionInterface;
    
    /**
     * Returns an array with all effects of this action
     * 
     * @return ActionEffectInterface[]
     */
    public function getEffects() : array;
    
    /**
     * Returns TRUE if the action has effect on the given meta object and FALSE otherwise.
     * 
     * @param MetaObjectInterface $object
     * @return bool
     */
    public function hasEffectOn(MetaObjectInterface $object) : bool;
    
    /**
     * Returns all effects on the given object (or its derivatives).
     * 
     * Returns an empty array if the action has no effect on the given object.
     * 
     * @param MetaObjectInterface $object
     * @return ActionEffectInterface[]
     */
    public function getEffectsOn(MetaObjectInterface $object) : array;
    
    /**
     * Adds and effect to the action.
     * 
     * @param MetaObjectInterface $effectedObject
     * @param string $name
     * @param MetaRelationPathInterface $relationPathFromActionObject
     * @return ActionInterface
     */
    public function addEffect(MetaObjectInterface $effectedObject, string $name = null, MetaRelationPathInterface $relationPathFromActionObject = null) : ActionInterface;
    
    /**
     * 
     * @return ActionDataCheckListInterface
     */
    public function getInputChecks() : ActionDataCheckListInterface;
    
    
    
    /**
     *
     * @return string|NULL
     */
    public function getOfflineStrategy() : ?string;
    
    /**
     *
     * @param string $value
     * @return ActionInterface
     */
    public function setOfflineStrategy(string $value) : ActionInterface;

    /**
     * 
     * @return ActionConfirmationListInterface|\exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface[]
     */
    public function getConfirmations() : ActionConfirmationListInterface;
}