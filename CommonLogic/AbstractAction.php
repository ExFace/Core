<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Actions\ActionOutputError;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * The abstract action is the base ActionInterface implementation, that simplifies the creation of custom actions.
 * All core
 * action are based on this class.
 *
 * To implement a specific action one atually only needs to implement the abstract perform() method. From within that method
 * the set_result...() methods should be called to set the action output. Everything else (registering in the action context, etc.)
 * is done automatically by the abstract action.
 *
 * The abstract action dispatches the following events prefixed by the actions alias (@see ActionEvent):
 * - Perform (.Before/.After)
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractAction implements ActionInterface
{
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}

    private $id = null;

    private $alias = null;

    private $name = null;

    private $exface = null;

    private $app = null;

    /** @var WidgetInterface widget, that called this action */
    private $called_by_widget = null;

    /** @var ActionInterface[] contains actions, that can be performed after the current one*/
    private $followup_actions = array();

    private $result_data_sheet = null;

    private $result_message = null;

    private $result_message_text = null;

    private $result = null;

    private $performed = false;

    private $is_undoable = null;

    private $is_data_modified = null;

    /** @var DataTransactionInterface */
    private $transaction = null;

    /**
     * @uxon
     *
     * @var DataSheetInterface
     */
    private $input_data_sheet = null;
    
    private $input_mappers = [];

    /**
     * @uxon template_alias Qualified alias of the template to be used to render the output of this action
     *
     * @var string
     */
    private $template_alias = null;

    /**
     * @var string
     */
    private $icon_name = null;

    /**
     *@var integer
     */
    private $input_rows_min = 0;

    /**
     * @var integer
     */
    private $input_rows_max = null;

    /**
     * @uxon
     *
     * @var array
     */
    private $disabled_behaviors = array();

    /**
     * @uxon object_alias Qualified alias of the base meta object for this action
     *
     * @var string
     */
    private $meta_object = null;

    private $autocommit = true;

    /**
     *
     * @deprecated use ActionFactory instead
     * @param AppInterface $app            
     * @param WidgetInterface $called_by_widget            
     */
    function __construct(AppInterface $app, WidgetInterface $called_by_widget = null)
    {
        $this->app = $app;
        $this->exface = $app->getWorkbench();
        if ($called_by_widget) {
            $this->setCalledByWidget($called_by_widget);
        }
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
        return $this->getNamespace() . NameResolver::NAMESPACE_SEPARATOR . $this->getAlias();
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
            $this->id = md5($this->exportUxonObject()->toJson());
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
     * Loads data from a standard UXON object (stdClass) into any action using setter functions.
     * E.g. calls $this->setId($source->id) for every property of the source object. Thus the behaviour of this
     * function like error handling, input checks, etc. can easily be customized by programming good
     * setters.
     *
     * @param \stdClass $source            
     */
    public function importUxonObject(\stdClass $uxon)
    {
        // Skip alias property if found because it was processed already to instantiate the right action class.
        // Setting the alias after instantiation is currently not possible beacuase it would mean recreating
        // the entire action.
        return $this->importUxonObjectDefault(UxonObject::fromStdClass($uxon), array(
            'alias'
        ));
    }

    public function hasProperty($name)
    {
        return method_exists($this, 'set_' . $name) || method_exists($this, 'set' . StringDataType::convertCaseUnderscoreToPascal($name));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getIconName()
     */
    public function getIconName()
    {
        return $this->icon_name;
    }

    /**
     * Sets the icon to be used for this action.
     * 
     * This icon will be used on buttons and menu items with this action unless they have
     * their own icons defined.
     * 
     * By default all icons from font awsome (http://fontawesome.io/icons/) are supported.
     *
     * @uxon-property icon_name
     * @uxon-type string
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setIconName()
     */
    public function setIconName($value)
    {
        $this->icon_name = $value;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getCalledByWidget()
     */
    public function getCalledByWidget()
    {
        return $this->called_by_widget;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setCalledByWidget()
     */
    public function setCalledByWidget($widget_or_widget_link)
    {
        if ($widget_or_widget_link instanceof WidgetInterface) {
            $this->called_by_widget = $widget_or_widget_link;
        } elseif ($widget_or_widget_link instanceof WidgetLink) {
            $this->called_by_widget = $widget_or_widget_link->getWidget();
        } else {
            $link = WidgetLinkFactory::createFromAnything($this->exface, $widget_or_widget_link);
            $this->called_by_widget = $link->getWidget();
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getFollowupActions()
     */
    public function getFollowupActions()
    {
        return $this->followup_actions;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setFollowupActions()
     */
    public function setFollowupActions(array $actions_array)
    {
        $this->followup_actions = $actions_array;
    }

    public function addFollowupAction(ActionInterface $action)
    {
        if (! $action->getCalledByWidget()) {
            $action->setCalledByWidget($this->getCalledByWidget());
        }
        $this->followup_actions[] = $action;
    }

    /**
     * Performs the action and registers it in the current window context.
     * This is a wrapper function for perform() that takes care of the contexts etc. The actual logic
     * of the action sits in the perform() method that, on the other hand should not be called
     * from external sources because the developer of a specific action might not have taken care
     * of contexts etc.
     *
     * @return ActionInterface
     */
    private function prepareResult()
    {
        $this->dispatchEvent('Perform.Before');
        // Register the action in the action context of the window. Since it is passed by reference, we can
        // safely do it here, befor perform(). On the other hand, this gives all kinds of action event handlers
        // the possibility to access the current action and it's current state
        $this->getApp()->getWorkbench()->context()->getScopeWindow()->getActionContext()->addAction($this);
        // Marke the action as performed first, to make sure it is not performed again if there is some exception
        // In the case of an exception in perform() it might be caught somewhere outside and the execution will
        // move on an mitght lead to another call on perform()
        $this->setPerformed();
        $this->perform();
        $this->dispatchEvent('Perform.After');
        if ($this->getAutocommit() && $this->isDataModified()) {
            $this->getTransaction()->commit();
        }
        return $this;
    }

    /**
     * Returns the resulting data sheet.
     * Performs the action if it had not been performed yet. If the action does not explicitly
     * produce a result data sheet (e.g. by calling $this->setResultDataSheet() somewhere within the perform() method), the
     * input data sheet will be passed through without changes. This ensures easy chainability of actions.
     *
     * @return DataSheetInterface
     */
    final public function getResultDataSheet()
    {
        // Make sure, the action has been performed
        if (! $this->isPerformed()) {
            $this->prepareResult();
        }
        
        // Pass through the input data if no result data is set by the perform() method
        if (! $this->result_data_sheet) {
            $this->result_data_sheet = $this->getInputDataSheet();
        }
        return $this->result_data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResult()
     */
    final public function getResult()
    {
        // Perform the action if not yet done so
        if (! $this->isPerformed()) {
            $this->prepareResult();
        }
        // If the actual result is still empty, try the result data sheet - that should always be filled
        if (is_null($this->result)) {
            return $this->getResultDataSheet();
        } else {
            return $this->result;
        }
    }

    protected function setResult($data_sheet_or_widget_or_string)
    {
        $this->result = $data_sheet_or_widget_or_string;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResultStringified()
     */
    public function getResultStringified()
    {
        $result = $this->getResult();
        if ($result instanceof DataSheetInterface) {
            return $result->toUxon();
        } elseif ($result instanceof WidgetInterface) {
            return '';
        } elseif (! is_object($result)) {
            return $result;
        } else {
            throw new ActionOutputError($this, 'Cannot convert result object of type "' . get_class($result) . '" to string for action "' . $this->getAliasWithNamespace() . '"', '6T5DUT1');
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResultOutput()
     */
    public function getResultOutput()
    {
        $result = $this->getResult();
        if ($result instanceof DataSheetInterface) {
            return $result->toUxon();
        } elseif ($result instanceof WidgetInterface) {
            return $this->getTemplate()->draw($result);
        } elseif (! is_object($result)) {
            return $result;
        } else {
            throw new ActionOutputError($this, 'Cannot render output for unknown result object type "' . gettype($result) . '" of action "' . $this->getAliasWithNamespace() . '"', '6T5DUT1');
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResultMessage()
     */
    final public function getResultMessage()
    {
        if (! $this->isPerformed()) {
            $this->prepareResult();
        }
        
        // If there is a custom result message text defined, use it instead of the autogenerated message
        if ($this->getResultMessageText()) {
            $message = '';
            $placeholders = $this->getWorkbench()->utils()->findPlaceholdersInString($this->getResultMessageText());
            foreach ($this->getResultDataSheet()->getRows() as $row) {
                $message_line = $this->getResultMessageText();
                foreach ($placeholders as $ph) {
                    $message_line = str_replace('[#' . $ph . '#]', $row[$ph], $message_line);
                }
                $message .= ($message ? "\n" : '') . $message_line;
            }
        } else {
            $message = $this->result_message;
        }
        
        return $message;
    }

    protected function setResultMessage($text)
    {
        $this->result_message = $text;
        return $this;
    }

    protected function addResultMessage($text)
    {
        $this->result_message .= $text;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getResultMessageText()
     */
    public function getResultMessageText()
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
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputDataSheet()
     */
    public function setInputDataSheet($data_sheet_or_uxon)
    {
        $data_sheet = DataSheetFactory::createFromAnything($this->exface, $data_sheet_or_uxon);
        $this->input_data_sheet = $data_sheet;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputDataSheet()
     */
    public function getInputDataSheet($apply_mappers = true)
    {        
        if ($apply_mappers && $this->input_data_sheet){
            foreach ($this->getInputMappers() as $mapper){
                if ($mapper->getFromMetaObject()->is($this->input_data_sheet->getMetaObject())){
                    return $mapper->map($this->input_data_sheet);
                    break;
                }
            }
        }
        
        return $this->input_data_sheet ? $this->input_data_sheet->copy() : $this->input_data_sheet;
    }

    protected function setResultDataSheet(DataSheetInterface $data_sheet)
    {
        $this->result_data_sheet = $data_sheet;
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
    abstract protected function perform();

    /**
     *
     * {@inheritdoc}
     *
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
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        if (is_null($this->meta_object)) {
            if ($this->getInputDataSheet()) {
                $this->meta_object = $this->getInputDataSheet()->getMetaObject();
            } elseif ($this->getCalledByWidget()) {
                $this->meta_object = $this->getCalledByWidget()->getMetaObject();
            } else {
                throw new ActionObjectNotSpecifiedError($this, 'Cannot determine the meta object, the action is performed upon! An action must either have an input data sheet or a reference to the widget, that called it, or an explicitly specified object_alias option to determine the meta object.');
            }
        }
        return $this->meta_object;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setMetaObject()
     */
    public function setMetaObject(Object $object)
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
     * @uxon-type string
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
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::implementsInterface()
     */
    public function implementsInterface($interface)
    {
        $interface = '\\exface\\Core\\Interfaces\\Actions\\' . $interface;
        if ($this instanceof $interface) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isUndoable()
     */
    public function isUndoable()
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
     *
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
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::isDataModified()
     */
    public function isDataModified()
    {
        if (is_null($this->is_data_modified)) {
            if ($this instanceof iModifyData) {
                return $this->is_data_modified = true;
            } else {
                return $this->is_data_modified = false;
            }
        }
        return $this->is_data_modified;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setDataModified()
     */
    public function setDataModified($value)
    {
        $this->is_data_modified = $value;
        return $this;
    }

    public function getUndoAction()
    {
        if ($this->isUndoable()) {
            return ActionFactory::createFromString($this->exface, 'exface.Core.UndoAction', $this->getCalledByWidget());
        }
    }

    /**
     * Returns a loadable UXON-representation of the action including the input data
     *
     * @return UxonObject
     */
    public function exportUxonObject()
    {
        $uxon = $this->getWorkbench()->createUxonObject();
        $uxon->alias = $this->getAliasWithNamespace();
        if ($this->getCalledByWidget()) {
            $uxon->called_by_widget = $this->getCalledByWidget()->createWidgetLink()->exportUxonObject();
        }
        $uxon->template_alias = $this->getTemplateAlias();
        $uxon->input_data_sheet = $this->getInputDataSheet(false)->exportUxonObject();
        $uxon->disabled_behaviors = UxonObject::fromArray($this->getDisabledBehaviors());
        
        if (empty($this->getInputMappers())){
            $input_mappers = new UxonObject();
            foreach ($this->getInputMappers() as $nr => $mapper){
                $input_mappers->setProperty($nr, $mapper->exportUxonObject());
            }
            $uxon->setProperty('input_mappers', $input_mappers);
        }
        
        return $uxon;
    }

    protected function dispatchEvent($event_name)
    {
        /* @var $event \exface\Core\Events\ActionEvent */
        $this->getApp()->getWorkbench()->eventManager()->dispatch(EventFactory::createActionEvent($this, $event_name));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    protected final function isPerformed()
    {
        return $this->performed;
    }

    protected final function setPerformed()
    {
        $this->performed = true;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setDisabledBehaviors()
     */
    public function setDisabledBehaviors(array $behavior_aliases)
    {
        $this->disabled_behaviors = $behavior_aliases;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getDisabledBehaviors()
     */
    public function getDisabledBehaviors()
    {
        return $this->disabled_behaviors;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getCalledOnUiPage()
     */
    public function getCalledOnUiPage()
    {
        return $this->getCalledByWidget() ? $this->getCalledByWidget()->getPage() : $this->getWorkbench()->ui()->getPageCurrent();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getTemplate()
     */
    public function getTemplate()
    {
        return $this->getWorkbench()->ui()->getTemplate($this->getTemplateAlias());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getTemplateAlias()
     */
    public function getTemplateAlias()
    {
        if (is_null($this->template_alias)) {
            $this->template_alias = $this->exface->ui()->getTemplateFromRequest()->getAliasWithNamespace();
        }
        return $this->template_alias;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setTemplateAlias()
     */
    public function setTemplateAlias($value)
    {
        $this->template_alias = $value;
        return $this;
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
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getName()
     */
    public function getName()
    {
        if (is_null($this->name)) {
            $this->name = $this->translate('NAME');
        }
        return $this->name;
    }

    public function hasName()
    {
        return ! $this->name || substr($this->name, - 5) == '.NAME' ? false : true;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setName()
     */
    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    public function copy()
    {
        return clone $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\DataSources\DataTransactionInterface
     */
    public function getTransaction()
    {
        if (is_null($this->transaction)) {
            $this->transaction = $this->getWorkbench()->data()->startTransaction();
        }
        return $this->transaction;
    }

    /**
     *
     * @param DataTransactionInterface $transaction            
     */
    public function setTransaction(DataTransactionInterface $transaction)
    {
        $this->transaction = $transaction;
        return $this;
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
     * @see \exface\Core\Interfaces\Actions\ActionInterface::is()
     */
    public function is($action_or_alias)
    {
        if ($action_or_alias instanceof ActionInterface){
            $class = get_class($action_or_alias);
            return $this instanceof $class;
        } elseif (is_string($action_or_alias)){
            return $this->getAliasWithNamespace() === trim($action_or_alias);
        } else {
            throw new UnexpectedValueException('Invalid value "' . gettype($action_or_alias) .'" passed to "ActionInterface::is()": instantiated action or action alias with namespace expected!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::getInputMappers()
     */
    public function getInputMappers()
    {
        return $this->input_mappers;
    }
    
    /**
     * Defines transformation rules for input datasheets if they are not based on the object of the action.
     * 
     * Input mappers can be used to perform an action on an object, that it was
     * not explicitly made for - even if the objects are not related in any way.
     * 
     * You can define as many mappers as you like - each containing rules to
     * map data of its form-object to its to-object. These rules basically
     * define simple mappings from one expression to another.
     * 
     * For example, if you want to have an action, that will create a support
     * ticket for a selected purchase order, you will probably use a the
     * action CreateObjectDialog (or a derivative) based on the ticket object.
     * Now, you can use input mappers to prefill it with data from the (totally
     * unrelated) purchase order object:
     * 
     * {
     *  "input_mappers": [
     *      {
     *          "from_object_alias": "my.App.PurchaseOrder",
     *          "expression_maps": [
     *              {
     *                  "from": "LABEL",
     *                  "to": "TITLE"
     *              },{
     *                  "from": "CUSTOMER__PRIORITY__LEVEL",
     *                  "to": "PRIORITY__LEVEL"
     *              }
     *          ]
     *      }
     *  ]
     * }
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
     * @uxon-type \exface\Core\CommonLogic\DataSheet\DataSheetMapper[]
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionInterface::setInputMappers()
     */
    public function setInputMappers(array $data_sheet_mappers_or_uxon_objects)
    {
        foreach ($data_sheet_mappers_or_uxon_objects as $instance){
            if ($instance instanceof DataSheetMapper){
                $mapper = $instance;
            } elseif ($instance instanceof UxonObject){
                $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $instance, null, $this->getMetaObject());
            } else {
                throw new ActionConfigurationError($this, 'Error in specification of input mappers: expecting array of mappers or their UXON descriptions - "' . gettype($instance) . '" given instead!');
            }
            
            $this->addInputMapper($mapper);
        }
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
}
?>