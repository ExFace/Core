<?php
namespace exface\Core\CommonLogic;

use exface\Core\Actions\ShowLookupDialog;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\iCallWidgetFunction;
use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\Interfaces\Actions\iRefreshInputWidget;
use exface\Core\Interfaces\Actions\iResetWidgets;
use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\IAffectMetaObjectsInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Uxon\ActionSchema;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\CommonLogic\Security\Authorization\ActionAuthorizationPoint;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Events\Action\OnActionInputValidatedEvent;
use exface\Core\Events\Action\OnActionFailedEvent;
use exface\Core\Interfaces\Actions\ActionEffectInterface;
use exface\Core\Widgets\Button;
use exface\Core\CommonLogic\Actions\ActionEffect;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Factories\ActionEffectFactory;
use exface\Core\CommonLogic\Tasks\ResultData;
use exface\Core\CommonLogic\Actions\ActionDataCheckList;
use exface\Core\Interfaces\Actions\ActionDataCheckListInterface;
use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Events\Action\OnBeforeActionInputValidatedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\ActionLogBook;
use exface\Core\Widgets\DebugMessage;
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Debug\LogBookInterface;
use function Sabre\Event\Loop\instance;

/**
 * The abstract action is a generic implementation of the ActionInterface, that simplifies 
 * the creation of custom actions.
 * 
 * To implement a specific action one atually only needs to implement the abstract perform() 
 * method. All core actions are made like this.
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractAction implements ActionInterface
{    
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}
	
	use iHaveIconTrait;

    private $id = null;

    private $alias = null;

    private $name = null;
    
    private $hint = null;

    private $exface = null;

    private $app = null;

    /** @var WidgetInterface widget, that called this action */
    private $widget_defined_in = null;

    private $result_message_text = null;

    private $is_undoable = null;

    /**
     * @var DataSheetInterface
     */
    private $input_data_preset = null;
    
    private $input_data_uxon = null;
    
    private $input_mappers = [];
    
    private $input_mappers_used = [];
    
    private $output_mappers = [];
    
    private $input_checks = null;

    /**
     * @var string
     */
    private $icon = null;

    /**
     * @var integer
     */
    private $input_rows_min = 0;

    /**
     * @var integer
     */
    private $input_rows_max = null;

    /**
     * @var array
     */
    private $disabled_behaviors = array();

    /**
     * @var string
     */
    private $meta_object = null;

    private $autocommit = true;
    
    private $input_object_alias = null;
    
    private $result_object_alias = null;
    
    private $triggerWidgetRequired = null;
    
    private $customEffects = [];
    
    private $logBooks = [];
    
    private $offlineStrategy = null;

    private $confirmationForAction = null;

    private $confirmationForUnsavedData = null;

    /**
     *
     * @deprecated use ActionFactory instead
     * @param AppInterface $app            
     * @param WidgetInterface $trigger_widget            
     */
    public function __construct(AppInterface $app, WidgetInterface $trigger_widget = null)
    {
        $this->app = $app;
        $this->exface = $app->getWorkbench();
        if ($trigger_widget) {
            $this->setWidgetDefinedIn($trigger_widget);
        }
        $this->input_checks = new ActionDataCheckList($this->getWorkbench(), $this);
        $this->init();
    }

    protected function init()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        if (is_null($this->alias)) {
            $class = explode('\\', get_class($this));
            $this->alias = end($class);
        }
        return $this->alias;
    }

    /**
     * The qualified alias of the action to be called (e.g. exface.Core.ShowDialog).
     * 
     * @uxon-property alias
     * @uxon-type metamodel:action
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setAlias()
     */
    public function setAlias($value)
    {
        $this->alias = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getApp()->getAliasWithNamespace();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getId()
     */
    public function getId()
    {
        if (is_null($this->id)) {
            $this->id = uniqid();
        }
        return $this->id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getApp()
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Loads data from a standard UXON object into any action using setter functions.
     * E.g. calls $this->setId($source->id) for every property of the source object. Thus the behaviour of this
     * function like error handling, input checks, etc. can easily be customized by programming good
     * setters.
     *
     * @param UxonObject $source            
     */
    public function importUxonObject(UxonObject $uxon)
    {
        // Set the object first as other properties might rely on it (e.g. the input_mappers)
        if ($uxon->hasProperty('object_alias')) {
            $this->setObjectAlias($uxon->getProperty('object_alias'));
        }
        // Skip alias property if found because it was processed already to instantiate the right action class.
        // Setting the alias after instantiation is currently not possible beacuase it would mean recreating
        // the entire action.
        return $this->importUxonObjectDefault($uxon, [
            'alias',
            'object_alias'
        ]);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getWidgetDefinedIn()
     */
    public function getWidgetDefinedIn() : WidgetInterface
    {
        return $this->widget_defined_in;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setWidgetDefinedIn()
     */
    public function setWidgetDefinedIn(WidgetInterface $widget) : ActionInterface
    {
        $this->widget_defined_in = $widget;
        return $this;
    }

    /**
     * {@inheritDoc}
     * 
     * This method actually only takes care of the infrastructure (events, etc.) while actual logic 
     * of the action sits in the perform() method that, on the other hand should not be called
     * from external sources because the developer of a specific action might not have taken care
     * of contexts, events etc. This is why handle() is final.
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::handle()
     */
    public final function handle(TaskInterface $task, DataTransactionInterface $transaction = null) : ResultInterface
    {        
        $logbook = $this->getLogBook($task);
        $logbook->startLogginEvents();

        // Start a new transaction if none passed
        if (is_null($transaction)) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeActionPerformedEvent($this, $task, $transaction, function() use ($task) {
            return $this->getInputDataSheet($task);
        }));
        
        // Call the action's logic
        try {
            $result = $this->perform($task, $transaction);
        } catch (\Throwable $e) {
            $this->getWorkbench()->eventManager()->dispatch(new OnActionFailedEvent($this, $task, $e, $transaction, function() use ($task) {
                return $this->getInputDataSheet($task);
            }));
            $logbook->stopLoggingEvents();
            $this->getWorkbench()->getLogger()->warning('Action "' . $this->getAliasWithNamespace() . '" failed', [], $this->getLogBook($task));
            throw $e;
        }
        
        $logbook->addSection('Output data');
        $logbook->setIndentActive(1);
        if ($result instanceof ResultData) {
            $resultData = $result->getData();
            $logbook->addDataSheet('Result data', $resultData);
            $logbook->addLine("Action result contains data with {$resultData->countRows()} rows of **{$resultData->getMetaObject()->__toString()}**");
            if ($this->hasOutputMappers() && $mapper = $this->getOutputMapper($result->getData()->getMetaObject())) {
                $result->setData($mapper->map($result->getData(), null, $logbook));
                $logbook->addDataSheet('Output data (mapped)', $result->getData());
            } else {
                $logbook->addLine('No output mapper found for object ' . $result->getData()->getMetaObject()->__toString());
            }
        } else {
            $logbook->addLine('Result has no data.');
        }
        $logbook->setIndentActive(0);
        
        // Do finalizing stuff like dispatching the OnAfterActionEvent, autocommit, etc.
        $this->performAfter($result, $transaction);
        
        $logbook->stopLoggingEvents();
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResultMessageText()
     */
    public function getResultMessageText() : ?string
    {
        return $this->result_message_text;
    }

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
     * @uxon-translatable true
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setResultMessageText()
     */
    public function setResultMessageText($value)
    {
        $this->result_message_text = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputDataPreset()
     */
    public function setInputDataPreset(DataSheetInterface $data_sheet) : ActionInterface
    {
        $this->input_data_uxon = null;
        $this->input_data_preset = $data_sheet;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputDataPreset()
     */
    public function getInputDataPreset() : DataSheetInterface
    {        
        if ($this->input_data_preset === null && $this->input_data_uxon !== null) {
            // Don't pass $this->getMetaObject() to the factory here as it may in-turn call getInputDataPreset()!
            $this->input_data_preset = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->input_data_uxon);
        }
        return $this->input_data_preset;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasInputDataPreset()
     */
    public function hasInputDataPreset() : bool
    {
        return $this->input_data_uxon !== null || $this->input_data_preset !== null;
    }
    
    /**
     * A static preset for the input data of the action.
     * 
     * This can be used to give the action input data even if there is no input widget
     * or that widget does not provide any data.
     * 
     * If regular input data is present too, the preset will be merged with the task 
     * data when the action is performed.
     * 
     * @uxon-property input_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias":"", "formula": "="}]}
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputDataSheet($uxon)
     */
    public function setInputDataSheet(UxonObject $uxon) : ActionInterface
    {
        $this->input_data_preset = null;
        $this->input_data_uxon = $uxon;
        return $this;
    }

    /**
     * Performs the action.
     * Should be implemented in every action. Does not actually return anything, instead the result_data_sheet,
     * the result message and (if needed) a separate result object should be set within the specific implementation via
     * set_result_data_sheet(), set_result_message() and set_result() respectively!
     *
     * This method is protected because only get_result...() methods are intended to be used by external objects. In addition to performing
     * the action they also take care of saving it to the current context, etc., while perform() ist totally depending on the specific
     * action implementation and holds only the actual logic without all the overhead.
     *
     * @return void
     */
    protected abstract function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface;
    
    /**
     * A convenience-method, that contains all the things to be performed immediately after the 
     * result was otained from the action.
     * 
     * Do not override this method unless you really need to. If you do, make sure to perform
     * the things below or provide suitable replacements.
     * 
     * @param ResultInterface $result
     * @param DataTransactionInterface $transaction
     * 
     * @triggers \exface\Core\Events\Action\OnActionPerformedEvent
     * 
     * @return ActionInterface
     */
    protected function performAfter(ResultInterface $result, DataTransactionInterface $transaction) : ActionInterface
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnActionPerformedEvent($this, $result, $transaction, function() use ($result) {
            return $this->getInputDataSheet($result->getTask());
        }));
        
        $this->getWorkbench()->getLogger()->notice('Action "' . $this->getAliasWithNamespace() . '" performed', [], $this->getLogBook($result->getTask()));
        
        // Register the action in the action context of the window. Since it is passed by reference, we can
        // safely do it here, befor perform(). On the other hand, this gives all kinds of action event handlers
        // the possibility to access the current action and it's current state
        // FIXME re-enable action context: maybe make it work with events?
        // $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getActionContext()->addAction($this);
        
        // Commit the transaction if autocommit is on and the action COULD have modified data
        // We cannot rely on $result->isDataModified() at this point as it is not allways possible 
        // to determine it within the action (some data source simply do not give relieable feedback).
        if ($this->getAutocommit() && (($this instanceof iModifyData) || $result->isDataModified())) {
            $transaction->commit();
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputRowsMin()
     */
    public function getInputRowsMin()
    {
        return $this->input_rows_min;
    }

    /**
     * Sets the minimum number of rows the input data sheet must have for this action.
     *
     * @uxon-property input_rows_min
     * @uxon-type integer
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputRowsMin()
     */
    public function setInputRowsMin($value)
    {
        $this->input_rows_min = $value;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputRowsMax()
     */
    public function getInputRowsMax()
    {
        return $this->input_rows_max;
    }

    /**
     * Sets the maximum number of rows the input data sheet must have for this action.
     *
     * @uxon-property input_rows_max
     * @uxon-type integer
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputRowsMax()
     */
    public function setInputRowsMax($value)
    {
        $this->input_rows_max = $value;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        if (is_null($this->meta_object)) {
            if ($this->hasInputDataPreset()) {
                $this->meta_object = $this->getInputDataPreset()->getMetaObject();
            } elseif ($this->isDefinedInWidget()) {
                $this->meta_object = $this->getWidgetDefinedIn()->getMetaObject();
            } else {
                throw new ActionObjectNotSpecifiedError($this, 'Cannot determine the meta object, the action is performed upon! An action must either have an input data sheet or a reference to the widget, that called it, or an explicitly specified object_alias option to determine the meta object.');
            }
        }
        return $this->meta_object;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setMetaObject()
     */
    public function setMetaObject(MetaObjectInterface $object)
    {
        $this->meta_object = $object;
        return $this;
    }

    /**
     * Defines the object, that this action is to be performed upon (alias with namespace).
     * 
     * If not explicitly defined, the object of the widget calling the action (e.g. a button)
     * will be used automatically.
     *
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setObjectAlias()
     */
    public function setObjectAlias($qualified_alias)
    {
        if ($object = $this->getWorkbench()->model()->getObject($qualified_alias)) {
            $this->setMetaObject($object);
        } else {
            throw new MetaObjectNotFoundError('Cannot load object "' . $qualified_alias . '" for action "' . $this->getAliasWithNamespace() . '"!', '6T5DJPP');
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::implementsInterface()
     */
    public function implementsInterface($interface)
    {
        if (! interface_exists($interface)){
            $interface = '\\exface\\Core\\Interfaces\\Actions\\' . $interface;
        }
        if ($this instanceof $interface) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isUndoable()
     */
    public function isUndoable() : bool
    {
        if (is_null($this->is_undoable)) {
            if ($this instanceof iCanBeUndone) {
                return $this->is_undoable = true;
            } else {
                return $this->is_undoable = false;
            }
        }
        return $this->is_undoable;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setUndoable()
     */
    public function setUndoable($value)
    {
        $this->is_undoable = $value;
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see iCanBeUndone::getUndoAction()
     */
    public function getUndoAction() : ActionInterface
    {
        if ($this->isUndoable()) {
            return ActionFactory::createFromString($this->exface, 'exface.Core.UndoAction', $this->getWidgetDefinedIn());
        }
    }

    /**
     * 
     * {@inheritdoc}
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('alias', $this->getAliasWithNamespace());
        /*if ($this->isDefinedInWidget()) {
            $uxon->setProperty('trigger_widget', $this->getWidgetDefinedIn()->getId());
        }*/
        if ($this->hasInputDataPreset()) {
            $uxon->setProperty('input_data_sheet',  $this->getInputDataPreset()->exportUxonObject());
        }
        $uxon->setProperty('disabled_behaviors', UxonObject::fromArray($this->getDisabledBehaviors()));
        
        if (! empty($this->getInputMappers())){
            $inner_uxon = new UxonObject();
            foreach ($this->getInputMappers() as $nr => $check){
                $inner_uxon->setProperty($nr, $check->exportUxonObject());
            }
            $uxon->setProperty('input_mappers', $inner_uxon);
        }
        
        if (! empty($this->getOutputMappers())){
            $inner_uxon = new UxonObject();
            foreach ($this->getOutputMappers() as $nr => $check){
                $inner_uxon->setProperty($nr, $check->exportUxonObject());
            }
            $uxon->setProperty('output_mappers', $inner_uxon);
        }
        
        if (! empty($this->getInputChecks())){
            $inner_uxon = new UxonObject();
            foreach ($this->getInputChecks() as $nr => $check){
                $inner_uxon->setProperty($nr, $check->exportUxonObject());
            }
            $uxon->setProperty('input_checks', $inner_uxon);
        }
        
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setDisabledBehaviors()
     */
    public function setDisabledBehaviors(UxonObject $behavior_aliases)
    {
        $this->disabled_behaviors = $behavior_aliases->toArray();
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getDisabledBehaviors()
     */
    public function getDisabledBehaviors()
    {
        return $this->disabled_behaviors;
    }

    /**
     * Returns the translation string for the given message id.
     *
     * This is a shortcut for calling $this->getApp()->getTranslator()->translate(). Additionally it will automatically append an
     * action prefix to the given id: e.g. $action->translate('SOME_MESSAGE') will result in
     * $action->getApp()->getTranslator()->translate('ACTION.ALIAS.SOME_MESSAGE')
     *
     * @see Translation::translate()
     *
     * @param string $message_id            
     * @param array $placeholders            
     * @param float $number_for_plurification            
     * @return string
     */
    public function translate($message_id, array $placeholders = null, $number_for_plurification = null)
    {
        $message_id = trim($message_id);
        $key_prefix = 'ACTION.' . mb_strtoupper($this->getAlias()) . '.';
        if (mb_strpos($message_id, $key_prefix) !== 0) {
            $message_id = $key_prefix . $message_id;
        }
        return $this->getApp()->getTranslator()->translate($message_id, $placeholders, $number_for_plurification);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getName()
     */
    public function getName()
    {
        if (is_null($this->name)) {
            $this->name = $this->translate('NAME');
        }
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasName()
     */
    public function hasName()
    {
        return ! $this->name || substr($this->name, - 5) == '.NAME' ? false : true;
    }

    /**
     * The name of the action; also used as default caption for buttons
     * 
     * @uxon-property name
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setName()
     */
    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy() : self
    {
        $copy = clone $this;
        $copy->input_mappers_used = [];
        $copy->logBooks = [];
        return $copy;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getAutocommit()
     */
    public function getAutocommit()
    {
        return $this->autocommit;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setAutocommit()
     */
    public function setAutocommit($true_or_false)
    {
        $this->autocommit = $true_or_false ? true : false;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isExactly()
     */
    public function isExactly($actionOrSelectorOrString) : bool
    {
        if ($actionOrSelectorOrString instanceof ActionInterface) {
            return strcasecmp($this->getAliasWithNamespace(), $actionOrSelectorOrString->getAliasWithNamespace()) === 0;
        } else {
            $selector = $actionOrSelectorOrString instanceof ActionSelectorInterface ? $actionOrSelectorOrString : SelectorFactory::createActionSelector($this->getWorkbench(), $actionOrSelectorOrString);
            switch (true) {
                case $selector->isFilepath():
                    $selectorClassPath = StringDataType::substringBefore($selector->toString(), '.' . FileSelectorInterface::PHP_FILE_EXTENSION);
                    $actionClassPath = FilePathDataType::normalize(get_class($this));
                    return strcasecmp($selectorClassPath, $actionClassPath) === 0;
                case $selector->isClassname():
                    return ltrim(get_class($this), "\\") === ltrim($selector->toString(), "\\");
                case $selector->isAlias():
                    return strcasecmp($this->getAliasWithNamespace(), $selector->toString()) === 0;
            }
            
        }
        
        throw new UnexpectedValueException('Cannot compare action ' . $this->getAliasWithNamespace() . ' to "' . $actionOrSelectorOrString . '": only instantiated actions or valid selectors allowed!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::is()
     */
    public function is($actionOrSelectorOrString) : bool
    {
        switch (true) {
            case $actionOrSelectorOrString instanceof ActionInterface:
                $class = get_class($actionOrSelectorOrString);
                return $this instanceof $class;
            case is_string($actionOrSelectorOrString):
            case $actionOrSelectorOrString instanceof ActionSelectorInterface:
                if ($actionOrSelectorOrString instanceof ActionSelectorInterface) {
                    $selector = $actionOrSelectorOrString;
                } else {
                    $selector = new ActionSelector($this->getWorkbench(), $actionOrSelectorOrString);
                }
                if ($this->isExactly($selector)) {
                    return true;
                }
                if ($selector->isClassname()) {
                    $class_name = $selector->toString();
                } else {
                    $class_name = get_class(ActionFactory::create($selector));
                }
                return $this instanceof $class_name;
            default:
                throw new UnexpectedValueException('Invalid value "' . gettype($actionOrSelectorOrString) .'" passed to "ActionInterface::is()": instantiated action or action alias with namespace expected!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputMappers()
     */
    public function getInputMappers() : array
    {
        return $this->input_mappers;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputMapper()
     */
    public function getInputMapper(MetaObjectInterface $fromObject) : ?DataSheetMapperInterface
    {
        foreach ($this->getInputMappers() as $mapper){
            if ($fromObject->is($mapper->getFromMetaObject()) === true){
                return $mapper;
            }
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasInputMappers()
     */
    public function hasInputMappers() : bool
    {
        return ! empty($this->getInputMappers());
    }
    
    /**
     * Defines transformation rules for input datasheets if they are not based on the object of the action.
     * 
     * Input mappers can be used to perform an action on an object, that it was
     * not explicitly made for - even if the objects are not related in any way.
     * 
     * You can define as many mappers as you like - each containing mappings (rules) 
     * to map data of its form-object to its to-object. These rules basically
     * define simple mappings from one expression to another. Each mapper can
     * have as many mappings as you need. You can even have mappings of different 
     * types:
     * 
     * - `column_to_column_mappings` - see example below
     * - `column_to_filter_mappings` - see example in property `input_mapper`
     * 
     * For example, if you want to have an action, that will create a support
     * ticket for a selected purchase order, you will probably use a the
     * action ShowObjectCreateDialog (or a derivative) based on the ticket object.
     * Now, you can use input mappers to prefill it with data from the (totally
     * unrelated) purchase order object:
     * 
     * ```
     * {
     *  "input_mappers": [
     *      {
     *          "from_object_alias": "my.App.PurchaseOrder",
     *          "column_to_column_mappings": [
     *              {
     *                  "from": "LABEL",
     *                  "to": "TITLE"
     *              },
     *              {
     *                  "from": "CUSTOMER__PRIORITY__LEVEL",
     *                  "to": "PRIORITY__LEVEL"
     *              }
     *          ]
     *      }
     *  ]
     * }
     * 
     * ```
     * 
     * In this example we map the label-attribute of the purchase order to the
     * title of the ticket. This will probably prefill our title field with
     * the order number and date (or whatever is set as label). We also map
     * the priority of the customer of the order to the ticket priority.
     * Assuming both attributes have identical numeric levels (probably 1, 2, 3),
     * this will result in high priority tickets for high priority customers.
     * 
     * You can now create an action in the model of your purchase orers, so
     * users can create tickets from every page showing orders. 
     * 
     * Alternatively you could create an action in the model of your tickets
     * with multiple mappers from different business objects: every time
     * the ticket-dialog opens, the system would see, if there is a suitable
     * mapper for the current input object and use it.
     * 
     * @uxon-property input_mappers
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper[]
     * @uxon-template [{"from_object_alias": "", "column_to_column_mappings": [{"from": "", "to": ""}]}]
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputMappers()
     */
    public function setInputMappers(UxonObject $uxon)
    {
        foreach ($uxon as $instance){
            $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $instance, null, $this->getMetaObject());         
            $this->addInputMapper($mapper);
        }
    }
    
    /**
     * Defines transformation rules for input data coming from the calling widget of this action.
     * 
     * This is a shortcut to specifying `input_mappers`, where an array needs to be created and
     * every mapper must have a `from_object_alias` defined. In contrast to `input_mappers`, you
     * can only define one mapper here and it will be automatically used to map from the meta 
     * object of the input widget to the object of this action.
     * 
     * Here is an example to use values from the selected data rows as filter values in the
     * for the input data of the action (rows and input data being the same object). This type
     * of mapping can be used for drill-downs in hierarchical structures, where the selected
     * row is used as parent-filter in the next hierarchy level.
     * 
     * ```
     * {
     *  "input_mapper": {
     *      "column_to_filter_mappings": [
     *          {
     *              "from": "id",
     *              "to": "parent_id"
     *          }
     *      ]
     *  }
     * }
     * 
     * ```
     * 
     * See description of the `input_mappers` property for more details. 
     * 
     * @uxon-property input_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"from_object_alias": "", "to_object_alias": "", "column_to_column_mappings": [{"from": "", "to": ""}]}
     * 
     * @see setInputMappers()
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputMapper()
     */
    public function setInputMapper(UxonObject $uxon)
    {
        if ($uxon->hasProperty('from_object_alias')) {
            $from_object = MetaObjectFactory::createFromString($this->getWorkbench(), $uxon->getProperty('from_object_alias'));
        } else {
            // The short notation for mappers allows to omit the from-object. In this case, the best-guess
            // will be assumed. If the action is defined in a widget, we will probably map from that
            // widgets object. Otherwise we can simply assume, that we map from the actions object.
            if ($this->isDefinedInWidget() && $calling_widget = $this->getWidgetDefinedIn()) {
                if ($calling_widget instanceof iUseInputWidget) {
                    $from_object = $calling_widget->getInputWidget()->getMetaObject();
                } else {
                    $from_object = $calling_widget->getMetaObject();
                }
            } else {
                $from_object = $this->getMetaObject();
            }
        }
        $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $uxon, $from_object, $this->getMetaObject());
        return $this->addInputMapper($mapper);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::addInputMapper()
     */
    public function addInputMapper(DataSheetMapperInterface $mapper)
    {
        $this->input_mappers[] = $mapper;
        return $this;
    }
    
    /**
     * Returns TRUE if an input DataSheet can be determined for this action and FALSE otherwise.
     * 
     * This is basically a shortcut for a try-catch block on getInputDataSheet().
     * 
     * @see getInputDataSheet()
     * @param TaskInterface $task
     * @return bool
     */
    protected function hasInputData(TaskInterface $task) : bool
    {
        try {
            $this->getInputDataSheet($task);
        } catch (ActionInputMissingError $e) {
            return false;
        }
        return true;
    }
    
    /**
     * Gets the input data by merging the preset data with the task data and applying
     * appropriate input mappers.
     * 
     * NOTE: this can be a resource consuming task, so it is a good idea
     * to call this method only once!
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError if neither input data nor object-binding found in task or the action itself
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function getInputDataSheet(TaskInterface $task) : DataSheetInterface
    {
        $logbook = $this->getLogBook($task);
        $diagram = 'flowchart LR';
        // Get the current input data
        if ($task->hasInputData()) {
            // If the task has some, use it
            $sheet = $task->getInputData();
            $logbook->addDataSheet('Task data', $sheet);
            // Merge it with the preset if it exists
            if ($this->hasInputDataPreset()) {
                $logbook->addDataSheet('Input preset', $this->getInputDataPreset());
                $sheet = $this->getInputDataPreset()->importRows($sheet);
                $diagram .= "\n\t Task(Task) -->|" . DataLogBook::buildMermaidTitleForData($sheet) . "| Task";
            } 
            $diagram .= "\n\t Task(Task) -->|" . DataLogBook::buildMermaidTitleForData($sheet) . "|";
        } elseif ($this->hasInputDataPreset()) {
            // If the task has no data, use the preset data
            $sheet = $this->getInputDataPreset();
            $logbook->addDataSheet('Input preset', $sheet);
            $diagram .= "\n\t InputPreset[Input Preset] -->|" . DataLogBook::buildMermaidTitleForData($sheet) . "|";
        } elseif ($task->hasMetaObject(true)) {
            // If there is neither task nor preset data, create a new data sheet
            $sheet = DataSheetFactory::createFromObject($task->getMetaObject());
            $diagram .= "\n\t Task(Task) -->|" .  DataLogBook::buildMermaidTitleForData($sheet) . "|";
        } else {
            throw new ActionInputMissingError($this, 'No input data found for action "' . $this->getAliasWithNamespace() . '"!');
        }
        
        // Replace the `Input data` section of the logbook
        // Make sure to restore the previously active section afterwards as very action might have
        // already started working with the logbook before calling `getInputDataSheet()`. This will
        // make sure, all the input calculation stuff is not in the middle of something else
        // Similarly, replacing the section prevents it from appearing as many times as 
        // `getInputDataSheet()` is called
        $prevSection = $logbook->getSectionActive();
        $logbook->removeSection('Input data');
        $logbook->addSection('Input data');
        $logbook->setIndentActive(0);
        $logbook->addCodeBlock('[#input_diagram#]', 'mermaid');
        $logbook->addLine('Looking for input mappers from object ' . $sheet->getMetaObject()->__toString());

        // Apply the input mappers
        if ($mapper = $this->getInputMapper($sheet->getMetaObject())){
            try {
                $inputData = $mapper->map($sheet, null, $logbook);
                $this->input_mappers_used[] = [$inputData, $mapper];
            } catch (\Throwable $e) {
                throw new ActionInputError($this, $e->getMessage(), null, $e);
            } finally {
                $diagram .= " InputMapping";
                $diagram .= "\n\t subgraph InputMapping[input_mapper]";
                $mapperDiagrams = $logbook->getCodeBlocksInSection();
                // Use the last mapper diagram as subgraph: e.g. remove it from the regular
                // mapper output and place it into the main diagram.
                // Take just the very last diagram - if there are nested mappers (e.g. subsheet mappers),
                // they can produce interesting 
                if (count($mapperDiagrams) >= 2) {
                    $diagram .= str_replace(['flowchart LR', '```mermaid', '```'], '', $mapperDiagrams[array_key_last($mapperDiagrams)]);
                    $logbook->removeLine(null, array_key_last($mapperDiagrams));
                }
                $diagram .= "\n\t end";
                $logbook->addPlaceholderValue('input_diagram', $diagram);
            }
            $diagram .= "\n\t InputMapping -->|" . DataLogBook::buildMermaidTitleForData($inputData) . "|";
        } else {
            $inputData = $sheet;
            $logbook->addLine('No input mapper found for object ' . $sheet->getMetaObject()->__toString());
        }
        
        $logbook->addDataSheet('Final input data', $inputData);
        
        if ($prevSection !== null && $prevSection !== 'Input data') {
            $logbook->setSectionActive($prevSection);
        } else {
            $logbook->setIndentActive(0);
        }
        
        // Validate the input data and dispatch events for event-based validation
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeActionInputValidatedEvent($this, $task, $inputData));
        $diagram .= " InputValidation[Input Validation]";
        $diagram .= "\n\t InputValidation --> Action[\"Action: {$this->getName()}\"]";
        $logbook->addPlaceholderValue('input_diagram', $diagram);
        $inputData = $this->validateInputData($inputData, $logbook);
        $this->getWorkbench()->eventManager()->dispatch(new OnActionInputValidatedEvent($this, $task, $inputData));
        
        return $inputData;
    }
    
    /**
     * If the given $inputData was processed by an input mapper, returns that mapper.
     * 
     * This method allows action code to determine if the result of `getInputDataSheet()` was actually
     * processed by a mapper and even to get the specific mapper instance.
     * 
     * @param DataSheetInterface $inputData
     * @return DataSheetMapperInterface|NULL
     */
    protected function getInputMapperUsed(DataSheetInterface $inputData) : ?DataSheetMapperInterface
    {
        foreach ($this->input_mappers_used as $pair) {
            if ($pair[0] === $inputData) {
                return $pair[1];
            }
        }
        return null;
    }
    
    /**
     * Throws exceptions if the input data does not meet the action's criteria.
     * 
     * Override and extend this method to add your own validation criteria other than those
     * built into `AbstractAction` (e.g. `input_rows_min/max`, 'input_object_alias', etc.)
     * 
     * @param DataSheetInterface $sheet
     * @throws ActionInputError
     * @throws ActionInputInvalidObjectError
     * @return DataSheetInterface
     */
    protected function validateInputData(DataSheetInterface $sheet, LogBookInterface $logbook) : DataSheetInterface
    {
        // Check if, there are restrictions on input data.
        if ($sheet->countRows() < $this->getInputRowsMin()) {
            throw new ActionInputError($this, 'Too few rows of input data for action ' . $this->getAliasWithNamespace() . ': need at least ' . $this->getInputRowsMin() . ', received ' . $sheet->countRows() . ' instead.');
        }
        if ($this->getInputRowsMax() !== null && $sheet->countRows() > $this->getInputRowsMax()) {
            throw new ActionInputError($this, 'Too many rows of input data for action ' . $this->getAliasWithNamespace() . ': max. ' . $this->getInputRowsMax() . ' allowed, received ' . $sheet->countRows() . ' instead.');
        }
        if (true === $this->hasInputObjectRestriction() && ! $sheet->isBlank() && false === $sheet->getMetaObject()->is($this->getInputObjectExpected())) {
            throw new ActionInputInvalidObjectError($this, 'Invalid input meta object for action "' . $this->getAlias() . '": exprecting "' . $this->getInputObjectExpected()->getAliasWithNamespace() . '", received "' . $sheet->getMetaObject()->getAliasWithNamespace() . '" instead!');
        }
        
        $logbook->addLine('Performing input validation on data of ' . $sheet->getMetaObject()->__toString() . '.');
        $logbook->addIndent(+1);
        if ($this->getInputChecks()->isDisabled() === false) {
            if ($this->getInputChecks()->isEmpty()) {
                $logbook->addLine('No `ìnput_invalid_if` defined for this action');
            }
            foreach ($this->getInputChecks() as $check) {
                if ($check->isApplicable($sheet)) {
                    try {
                        $check->check($sheet);
                        $logbook->addLine('Check `' . $check->__toString() . '` passed');
                    } catch (DataCheckExceptionInterface $e) {
                        $eHint = '';
                        if (null !== $e->getBadData()) {
                            $eHint = ' on ' . $e->getBadData()->countRows() . ' rows';
                        }
                        $logbook->addLine('Check `' . $check->__toString() . '` failed' . $eHint);
                        throw new ActionInputError($this, $e->getMessage(), null, $e);
                    }
                } else {
                    $logbook->addLine('Check `' . $check->__toString() . '` not applicable');
                }
            }
        } else {
            $logbook->addLine('Property `input_invalid_if` is explicitly disabled');
        }
        $logbook->addIndent(-1);
        
        return $sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isDefinedInWidget()
     */
    public function isDefinedInWidget(): bool
    {
        return is_null($this->widget_defined_in) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getSelector()
     */
    public function getSelector() : ActionSelectorInterface
    {
        return new ActionSelector($this->getWorkbench(), $this->getAliasWithNamespace());
    }
    
    /**
     * Only allow input data with this object or a derivative.
     * 
     * Attempting to perform the action upon data of another object will result in an error.
     * You can use `input_mappers` to map input data to the correct object.
     * 
     * By default, an action accepts data of any object and attempts to deal with it.
     * Many of the core actions are actually agnostic to objects.
     * 
     * @uxon-property input_object_alias
     * @uxon-type metamodel:object
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputObjectAlias()
     */
    public function setInputObjectAlias(string $aliasWithNamespace) : ActionInterface
    {
        $this->input_object_alias = $aliasWithNamespace;
        return $this;
    }
    
    /**
     * Force the result of the action to be based on given meta object or a derivative.
     * 
     * If performing the action results in another object, it will produce an error.
     * 
     * By default, an action does not check the result object.
     * 
     * @uxon-property result_object_alias
     * @uxon-type metamodel:object
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setResultObjectAlias()
     */
    public function setResultObjectAlias(string $aliasWithNamespace) : ActionInterface
    {
        $this->result_object_alias = $aliasWithNamespace;
        return $this;
    }
    
    /**
     * Returns TRUE if the action only accepts input based on a certain meta object and FALSE otherwise.
     * 
     * @return bool
     */
    protected function hasInputObjectRestriction() : bool
    {
        return $this->input_object_alias !== null;
    }
    
    /**
     * Returns the meta object, that the input of the action must be based on - or NULL if not restricted.
     * 
     * @return MetaObjectInterface|NULL
     */
    protected function getInputObjectExpected() : ?MetaObjectInterface
    {
        return $this->hasInputObjectRestriction() ? $this->getWorkbench()->model()->getObject($this->input_object_alias) : null;
    }
    
    protected function hasResultObjectRestriction() : bool
    {
        return $this->result_object_alias !== null;
    }
    
    protected function getResultObjectExpected() : ?MetaObjectInterface
    {
        return $this->hasResultObjectRestriction() ? $this->getWorkbench()->model()->getObject($this->result_object_alias) : null;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return ActionSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isAuthorized()
     */
    public function isAuthorized(UserImpersonationInterface $userOrToken = null) : bool
    {
        $actionAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(ActionAuthorizationPoint::class);
        try {
            $actionAP->authorize($this, null, $userOrToken);
            return true;
        } catch (AuthorizationExceptionInterface $e) {
            return false;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return $this->triggerWidgetRequired;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputTriggerWidgetRequired()
     */
    public function setInputTriggerWidgetRequired(bool $trueOrFalse) : ActionInterface
    {
        $currentValue = $this->isTriggerWidgetRequired();
        if ($currentValue !== null && $currentValue !== $trueOrFalse) {
            throw new ActionRuntimeError($this, 'Cannot set input_trigger_widet_required to ' . ($trueOrFalse ? 'true' : 'false') . ': only ' . ($currentValue ? 'true' : 'false') . ' allowed!');
        }
        $this->triggerWidgetRequired = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getHint()
     */
    public function getHint() : ?string
    {
        return $this->hint;
    }
    
    /**
     * A short description used on mouse-hover, in the contextual help, etc.
     * 
     * @uxon-property hint
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setHint()
     */
    public function setHint(string $value) : ActionInterface
    {
        $this->hint = $value;
        return $this;
    }
    
    /**
     * By default, an action returns the effects specified in its model (via `effects` or `effected_objects`)
     * and those, that can be derived from the UI model **if** the action is known to change data!
     * 
     * Override this method to change the default effeects of an action. See the following actions
     * for examples:
     * 
     * @see \exface\Core\Actions\GenerateModelFromDataSource
     * @see \exface\Core\Actions\CustomDataSourceQuery
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getEffects()
     */
    public function getEffects() : array
    {
        $effects = $this->getEffectsSpecifiedExplicitly();
        if ($this instanceof iModifyData) {
            $effects = array_merge($effects,  $this->getEffectsFromModel());
        }
        
        foreach ($this->getMetaObject()->getBehaviors() as $behavior) {
            if($behavior instanceof IAffectMetaObjectsInterface) {
                foreach ($behavior->getAffectedMetaObjects() as $affectedMetaObject) {
                    $effects[] = new ActionEffect($this, new UxonObject([
                        'effected_object' => $affectedMetaObject->getAliasWithNamespace()
                    ]));
                }
            }
        }
        
        return $effects;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasEffectOn()
     */
    public function hasEffectOn(MetaObjectInterface $object) : bool
    {
        foreach ($this->getEffects() as $effect) {
            if ($effect->getEffectedObject()->is($object)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getEffectsOn()
     */
    public function getEffectsOn(MetaObjectInterface $object) : array
    {
        $result = [];
        foreach ($this->getEffects() as $effect) {
            if ($effect->getEffectedObject()->is($object)) {
                $result[] = $effect;
            }
        }
        return $result;
    }
    
    /**
     * 
     * @return ActionEffectInterface[]
     */
    protected function getEffectsSpecifiedExplicitly() : array
    {
        return $this->customEffects;
    }
    
    /**
     * 
     * @return ActionEffectInterface[]
     */
    protected function getEffectsFromModel() : array
    {
        $button = $this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null;
        $name = $button ? $button->getCaption() : $this->getName();
        $effects = [];
        $effects[] = ActionEffectFactory::createForEffectedObject($this, $this->getMetaObject(), $name);
        if ($button) {
            $effects = array_merge($effects, $this->getEffectsFromTriggerWidget($button, $this->getMetaObject(), $name, RelationPathFactory::createForObject($this->getMetaObject())));
        }
        return $effects;
    }
    
    /**
     * 
     * @param MetaObjectInterface $prevLevelObject
     * @param string $prevLevelName
     * @return array
     */
    protected function getEffectsFromTriggerWidget(iTriggerAction $button, MetaObjectInterface $prevLevelObject, string $prevLevelName, MetaRelationPathInterface $prevLevelRelPath = null) : array
    {
        $effects = [];
        
        if (! ($name = $button->getCaption())) {
            $name = $prevLevelName;
        }
        $thisLevelObject = $button->getMetaObject();
        
        // Add effect on the object of the button triggering this action - if it is based on a different object.
        if ($thisLevelObject !== $prevLevelObject) {
            if ($prevLevelObject !== $button->getMetaObject()) {
                $name .= ' > ' . $prevLevelName;
            }
            $effectUxon = new UxonObject([
                'name' => $name,
                'effected_object' => $thisLevelObject->getAliasWithNamespace()
            ]);
            $relationFromPrev = null;
            if ($prevLevelRelPath && $relationFromPrev = $prevLevelObject->findRelation($thisLevelObject, true)) {
                $relPathFromPrev = $prevLevelRelPath->copy()->appendRelation($relationFromPrev);
                $effectUxon->setProperty('relation_path_to_effected_object', $relPathFromPrev->toString());
            }
            $effects[] = new ActionEffect($this, $effectUxon);
        }
        
        // Add effect on the object of the input widget of the trigger button - if different
        // Also see if the input dialog has a button as parent (= is part of a widget shown by an action)
        // and call this method recursively for that button
        if ($inputWidget = $button->getInputWidget()) {
            $inputObject = $inputWidget->getMetaObject();
            
            // Try to find a relation to the object of the input widget
            $inputObjectRelPath = null;
            if ($prevLevelRelPath) {
                switch (true) {
                    // If it's the same object, as the previous level - use the injected relation path
                    case $inputObject === $prevLevelObject:
                        $inputObjectRelPath = $prevLevelRelPath;
                        break;
                    // If it's the same object, as that of the button, use the relation path determined above
                    case $inputObject === $thisLevelObject:
                        $inputObjectRelPath = $relPathFromPrev;
                        break;
                    // Try to find a relation from the object of the button
                    case $relationToInput = $thisLevelObject->findRelation($inputObject, true):
                        if ($relationFromPrev) {
                            $inputObjectRelPath = $relPathFromPrev->copy()->appendRelation($relationToInput);
                        } elseif ($prevLevelRelPath->getEndObject()->isExactly($thisLevelObject)) {
                            $inputObjectRelPath = $prevLevelRelPath->copy()->appendRelation($relationToInput);
                        }
                        break;
                    // Use no relation path if all above fails
                    default:
                        $inputObjectRelPath = null;
                }
            }
            
            // If the input widgets object had not been added already (= it is different from the button and previous 
            // level objects) - add an effect for it
            if ($inputObject !== $button->getMetaObject() && $inputObject !== $prevLevelObject) {
                $effectUxon = new UxonObject([
                    'name' => $name,
                    'effected_object' => $inputObject->getAliasWithNamespace()
                ]);
                if ($inputObjectRelPath) {
                    $effectUxon->setProperty('relation_path_to_effected_object', $inputObjectRelPath->toString());
                }
                $effects[] = new ActionEffect($this, $effectUxon);
            }
            
            // If the input widget was shown by an action triggered by a button in-turn, get effects of that button
            // recursively
            /* @var $inputDialogTrigger \exface\Core\Widgets\Button */
            if ($inputDialogTrigger = $inputWidget->getParentByClass(Button::class)) {
                if ($inputDialogTrigger->hasAction()) {
                    // Double check if the relation path found so far actually connects the actions object and
                    // this levels object. If so, pass it on to the next level.
                    // This is important for deeply nested buttons when the relation path can only be determined
                    // upto a certain depth or after a certain depth. Both cases are useless for the effects, but
                    // produce relation paths here. Doing the checks earlier in the code would make the logic
                    // overcomplicated, so we just check it once here - right before passing on to the next level.
                    $relPath = $relPathFromPrev ?? $prevLevelRelPath;
                    if ($relPath && (! $relPath->getStartObject()->isExactly($this->getMetaObject()) || ! $relPath->getEndObject()->isExactly($thisLevelObject))) {
                        $relPath = null;
                    }
                    $effects = array_merge(
                        $effects, 
                        $this->getEffectsFromTriggerWidget($inputDialogTrigger, $thisLevelObject, $name, $relPath)
                    );
                }
            }
        }
        
        return $effects;
    }
    
    /**
     * Objects and relations that may be affected by the action (in addition to those determined by the action logic automatically).
     * 
     * Most effects of an action can be determined automatically. If not, you can add them
     * here manually. For example:
     * 
     * - If actions in a dashboard do not cause some of the data widgets to update, add the
     * meta objects of these widgets to the actions `effects` to trigger the update.
     * - CLI command actions mostly cannot determine their effects automatically - add them here!
     * - Actions like `CallWebService`, `CustomFacadeScript`, etc. mostly do not "know" their 
     * effects - add them here too!
     * 
     * **HINT:** If you do not need advanced effects properties like relaiton paths or names, using 
     * the flat `effected_objects` array is simpler!
     * 
     * Every action can have one or more effects, each indicating that it modifies the state of a 
     * meta object - e.g. by changing its data in the data source. Knowing the action effects allows 
     * the workbench to better understand, what actions really do and how this might affect the UI. 
     * In particular, they indicate, what data might have changed and needs reloading after an action 
     * was performed. 
     * 
     * **NOTE:** an effect on a specific object, does not guarantee, that the action will actually 
     * change it every time it is performed - it only means, the action **can** modify that object.
     * 
     * Whether the modification takes place or not depends on the logic of the action, the input
     * data, behaviors of other effected objects etc. - in many cases, we can't even really know
     * for sure, what will happen because actions may trigger logic in external systems, DB-triggers, 
     * etc. - things not known to the workbench at all.
     * 
     * This is why action effects are part of the action model and can be added manually to actions
     * to tell the workbench, that the action is likely to effect an object even if that is 
     * not obvious.
     * 
     * @uxon-property effects
     * @uxon-type \exface\Core\CommonLogic\Actions\ActionEffect[]
     * @uxon-template [{"name": "", "effected_object": ""}]
     * 
     * @param UxonObject $uxonArray
     * @return ActionInterface
     */
    protected function setEffects(UxonObject $uxonArray) : ActionInterface
    {
        foreach ($uxonArray as $uxon) {
            $this->customEffects[] = new ActionEffect($this, $uxon);
        }
        return $this;
    }
    
    /**
     * Aliases of meta objects, that may be affected by this action (apart from the obvious input and action objects).
     * 
     * Examples: 
     * 
     * - If actions in a dashboard do not cause some of the data widgets to update, add the
     * meta objects of these widgets to the actions `effects` to trigger the update.
     * - CLI command actions mostly cannot determine their effects automatically - add them here!
     * - WebService actions also often do not "know" their effects - add them here too!
     * 
     * This property is a simplified shortcut for `effects`. Refer to the documentation of the
     * `effects` property for more details.
     * 
     * @uxon-property effected_objects
     * @uxon-type metamodel:object[]
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return ActionInterface
     */
    protected function setEffectedObjects(UxonObject $uxonArray) : ActionInterface
    {
        foreach ($uxonArray->getPropertiesAll() as $objectAlias) {
            $this->customEffects[] = new ActionEffect($this, new UxonObject([
                'effected_object' => $objectAlias
            ]));
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::addEffect()
     */
    public function addEffect(MetaObjectInterface $effectedObject, string $name = null, MetaRelationPathInterface $relationPathFromActionObject = null) : ActionInterface
    {
        $uxon = new UxonObject();
        if ($name !== null) {
            $uxon->setProperty('name', $name);
        }
        if ($relationPathFromActionObject !== null) {
            $uxon->setProperty('relation_path_to_effected_object', $relationPathFromActionObject->toString());
        } else {
            $uxon->setProperty('effected_object', $effectedObject->getAliasWithNamespace());
        }
        $this->customEffects[] = new ActionEffect($this, $uxon);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getOutputMappers()
     */
    public function getOutputMappers() : array
    {
        return $this->output_mappers;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getOutputMapper()
     */
    public function getOutputMapper(MetaObjectInterface $fromObject) : ?DataSheetMapperInterface
    {
        foreach ($this->getOutputMappers() as $mapper){
            if ($fromObject->is($mapper->getFromMetaObject()) === true){
                return $mapper;
            }
        }
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasOutputMappers()
     */
    public function hasOutputMappers() : bool
    {
        return ! empty($this->getOutputMappers());
    }
    
    /**
     * Allows to apply output mappers depending on the object of the actions result data - similarly as `input_mappers`.
     * 
     * A mapper will be applied if the result data of the action is based on the from-object
     * of the mapper.
     *
     * @uxon-property output_mappers
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper[]
     * @uxon-template [{"from_object_alias": "", "column_to_column_mappings": [{"from": "", "to": ""}]}]
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setOutputMappers()
     */
    public function setOutputMappers(UxonObject $uxon)
    {
        foreach ($uxon as $instance){
            $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $instance, null, $this->getMetaObject());
            $this->addOutputMapper($mapper);
        }
    }
    
    /**
     * Allows to transform the actions result data similarly to the `input_mapper`.
     * 
     * In contrast to `output_mappers` this mapper will take whatever object the action is based on
     * and map it to the mappers `to_object_alias` (or itself if `to_object_alias` is not set).
     *
     * @uxon-property output_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"to_object_alias": "", "column_to_column_mappings": [{"from": "", "to": ""}]}
     *
     * @see setOutputMappers()
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setOutputMapper()
     */
    public function setOutputMapper(UxonObject $uxon)
    {
        if ($uxon->hasProperty('from_object_alias')) {
            $from_object = $this->getWorkbench()->model()->getObject($uxon->getProperty('from_object_alias'));
        } else {
            $from_object = $this->getMetaObject();
        }
        if ($uxon->hasProperty('to_object_alias')) {
            $to_object = $this->getWorkbench()->model()->getObject($uxon->getProperty('to_object_alias'));
        } else {
            $to_object = $this->getMetaObject();
        }
        $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $uxon, $from_object, $to_object);
        return $this->addOutputMapper($mapper);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::addOutputMapper()
     */
    public function addOutputMapper(DataSheetMapperInterface $mapper)
    {
        $this->output_mappers[] = $mapper;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputChecks()
     */
    public function getInputChecks() : ActionDataCheckListInterface
    {
        return $this->input_checks;
    }
    
    /**
     * Check input data against these conditions before the action is performed
     * 
     * If any of these conditions are not met, an error will be raised before the action and
     * it will not be performed. Each check may contain it's own error message to make the
     * errors better understandable for the user.
     * 
     * @uxon-property input_invalid_if
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $arrayOfDataChecks
     * @return AbstractAction
     */
    protected function setInputInvalidIf(UxonObject $arrayOfDataChecks) : AbstractAction
    {
        $this->getInputChecks()->removeAll();
        foreach($arrayOfDataChecks as $uxon) {
            $this->getInputChecks()->add(new DataCheck($this->getWorkbench(), $uxon));
        }
        return $this;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @return ActionLogBook
     */
    protected function getLogBook(TaskInterface $task) : ActionLogBook
    {
        foreach ($this->logBooks as $lb) {
            if ($lb->getTask() === $task) {
                return $lb;
            }
        }
        $lb = new ActionLogBook('Action', $this, $task);
        $this->logBooks[] = $lb;
        return $lb;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        foreach ($this->logBooks as $logbook) {
            $error_message = $logbook->createDebugWidget($error_message);
        }
        return $error_message;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getOfflineStrategy()
     */
    public function getOfflineStrategy() : ?string
    {
        return $this->offlineStrategy;
    }
    
    /**
     * What to do with this action offline: make always available (`presync`), `enqueue` when triggered, `skip`, etc.
     * 
     * @uxon-property offline_strategy
     * @uxon-type [enqueue,presync,use_cache,skip,online_only,client_side]
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setOffline()
     */
    public function setOfflineStrategy(string $value) : ActionInterface
    {
        $this->offlineStrategy = OfflineStrategyDataType::cast($value);
        return $this;
    }

    /**
     * Make the action ask for confirmation when its button is pressed
     * 
     * @uxon-property confirmation_for_action
     * @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
     * @uxon-template {"widget_type": "ConfirmationMessage", "text": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject|bool|string $uxon
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    protected function setConfirmationForAction($uxonOrBoolOrString) : ActionInterface
    {
        switch (true) {
            case $uxonOrBoolOrString === false:
                $this->confirmationForAction = false;
                return $this;
            case $uxonOrBoolOrString === true:
                $uxon = new UxonObject();
                break;
            case $uxonOrBoolOrString instanceof UxonObject:
                $uxon = $uxonOrBoolOrString;
                break;
            case is_string($uxonOrBoolOrString):
                $uxon = new UxonObject([
                    'text' => $uxonOrBoolOrString
                ]);
                break;
            default:
                throw new ActionConfigurationError($this, 'Invalid value for confirmation_for_action in action');
        }
        if ($this->isDefinedInWidget()) {
            $parent = $this->getWidgetDefinedIn();
            if (! $uxon->hasProperty('button_continue')) {
                $uxon->setProperty('button_continue', new UxonObject([
                    'caption' => $this->getName()
                ]));
            }
            $this->confirmationForAction = WidgetFactory::createFromUxonInParent($parent, $uxon, 'ConfirmationMessage');
        } else {
            // TODO what here?
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getConfirmationForAction()
     */
    public function getConfirmationForAction() : ?ConfirmationWidgetInterface
    {
        if ($this->confirmationForAction === false) {
            return null;
        }
        return $this->confirmationForAction;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasConfirmationForAction()
     */
    public function hasConfirmationForAction() : bool
    {
        return ($this->confirmationForAction instanceof UxonObject);
    }

    /**
     * Make the action warn the user if it is to be performed when unsaved changes are still visible
     * 
     * @uxon-property confirmation_for_unsaved_data
     * @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
     * @uxon-template {"widget_type": "ConfirmationMessage", "text": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject|bool|string $uxon
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    protected function setConfirmationForUnsavedChanges($uxonOrBoolOrString) : ActionInterface
    {
        switch (true) {
            case $uxonOrBoolOrString === false:
            case $uxonOrBoolOrString === true:
                $this->confirmationForUnsavedData = $uxonOrBoolOrString;
                return $this;
            case $uxonOrBoolOrString instanceof UxonObject:
                $uxon = $uxonOrBoolOrString;
                break;
            case is_string($uxonOrBoolOrString):
                $uxon = new UxonObject([
                    'text' => $uxonOrBoolOrString
                ]);
                break;
            default:
                throw new ActionConfigurationError($this, 'Invalid value for confirmation_for_unsaved_changes in action');
        }
        if ($this->isDefinedInWidget()) {
            $parent = $this->getWidgetDefinedIn();
            $this->confirmationForAction = WidgetFactory::createFromUxonInParent($parent, $uxon, 'ConfirmationMessage');
        } else {
            // TODO what here?
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getConfirmationForUnsavedChanges()
     */
    public function getConfirmationForUnsavedChanges() : ?ConfirmationWidgetInterface
    {
        if ($this->confirmationForUnsavedData === false) {
            return null;
        }
        if (($this->confirmationForUnsavedData ?? true) === true && $this->hasConfirmationForUnsavedChanges()) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $this->confirmationForUnsavedData = WidgetFactory::createFromUxonInParent($this->getWidgetDefinedIn(), new UxonObject([
                'widget_type' => 'ConfirmationMessage',
                'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.TITLE'),
                'text' => $translator->translate('MESSAGE.DISCARD_CHANGES.TEXT'),
                'button_continue' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CONTINUE')
                ],
                'button_cancel' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CANCEL')
                ]
            ]));
        }
        return $this->confirmationForUnsavedData;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::hasConfirmationForUnsavedChanges()
     */
    public function hasConfirmationForUnsavedChanges(?bool $default = false) : ?bool
    {
        if ($this->confirmationForUnsavedData === false) {
            return false;
        }
        if ($this->confirmationForUnsavedData !== null) {
            return true;
        }

        return $default;
    }
}