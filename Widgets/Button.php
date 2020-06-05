<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Traits\iUseInputWidgetTrait;
use exface\Core\Interfaces\Widgets\iDefineAction;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Interfaces\Widgets\iCanBeDisabled;
use exface\Core\Interfaces\Actions\iResetWidgets;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\Model\UiPage;

/**
 * A Button is the primary widget for triggering actions.
 *
 * In addition to the general widget attributes it can have an icon and also subwidgets (if the triggered action shows a widget).
 *
 * @author Andrej Kabachnik
 *        
 */
class Button extends AbstractWidget implements iHaveIcon, iHaveColor, iTriggerAction, iDefineAction, iUseInputWidget, iCanBeAligned, iCanBeDisabled
{
    use iCanBeAlignedTrait;
    
    use iUseInputWidgetTrait;
    
    use iHaveIconTrait {
        getIcon as getIconViaTrait;
    }
    
    use iHaveColorTrait;
    
    private $action_alias = null;

    private $action = null;
    
    private $action_uxon = null;

    private $active_condition = null;

    private $input_widget_id = null;

    private $input_widget = null;

    private $hotkey = null;

    private $refresh_input = true;
    
    private $refreshWidgetIds = [];
    
    private $resetInputWidget = null;
    
    private $hiddenIfAccessDenied = false;
    
    /**
     * 
     * @var string[]
     */
    private $resetWidgetIds = [];

    public function getAction()
    {
        if ($this->action === null) {
            if ($this->getActionAlias()) {
                $this->action = ActionFactory::createFromString($this->getWorkbench(), $this->getActionAlias(), $this);
                if ($this->action_uxon !== null) {
                    $this->action->importUxonObject($this->action_uxon);
                }
            } elseif ($this->action_uxon !== null) {
                $this->action = ActionFactory::createFromUxon($this->getWorkbench(), $this->action_uxon, $this);
            }
        }
        return $this->action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::hasAction()
     */
    public function hasAction() : bool
    {
        return $this->getAction() ? true : false;
    }

    /**
     * Sets the action, that the button will trigger.
     *
     * Properties of the action can also be set as properties of the button directly by prefixing them with "action_".
     * Thus setting "action_alias: SOME_ALIAS" for the button is the same as settin "action: {alias: SOME_ALIAS}".
     *
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iDefineAction::setAction()
     */
    public function setAction($action_or_uxon)
    {
        if ($action_or_uxon instanceof ActionInterface) {
            $this->action = $action_or_uxon;
            // Let the action know, it was (or will be) called by this button
            // unless the action already has a called-by-widget. This would be
            // the case if the same action is assigned to multiple buttons. 
            // Although this feature is currently not explicitly used, it seems
            // a decent idea to share an action between buttons: e.g. a toolbar
            // button and a menu button which actually do exactly the same thing.
            if (! $this->action->isDefinedInWidget()){
                $this->action->setWidgetDefinedIn($this);
            }
        } elseif ($action_or_uxon instanceof UxonObject) {
            $this->setActionAlias($action_or_uxon->getProperty('alias'));
            $this->setActionOptions($action_or_uxon);
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'The set_action() method of a button accepts either an action object extended from ActionInterface or a UXON description object. ' . gettype($action_or_uxon) . ' given for button "' . $this->getId() . '".', '6T919D5');
        }
        return $this;
    }

    public function getActionAlias()
    {
        // If the action has already been instantiated, return it's qualified alias. This is mostly the same as the alias in $this->action_alias
        // but they may differ in case ($this->action_alias is entered by the user!). In addition this approach would allow to switch the
        // action of the button programmatically, still getting the right alias here.
        if ($this->action) {
            return $this->getAction()->getAliasWithNamespace();
        }
        return $this->action_alias;
    }

    /**
     * Specifies the action to be performed by it's fully qualified alias (with namespace!).
     *
     * This property does the same as {widget_type: Button, action: {alias: SOME_ALIAS} }
     *
     * @uxon-property action_alias
     * @uxon-type metamodel:action
     *
     * @param string $value
     * @return Button            
     */
    public function setActionAlias($value)
    {
        $this->action_alias = $value;
        return $this;
    }

    /**
     * Buttons allow to set action options as an options array or directly as an option of the button itself.
     * In the latter case the option's name must be prefixed by "action_": to set a action's property
     * called "script" simply add "action_script": XXX to the button.
     *
     * @see \exface\Core\Widgets\AbstractWidget::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        // If there are button attributes starting with "action_", these are just shortcuts for
        // action attributes. We need to remove them from the button's description an pass
        // them all in on "action_options" attribute. The only exclusion is the action_alias, which
        // we need to instantiate the action.
        $action_options = $uxon->hasProperty('action_options') ? $uxon->getProperty('action_options') : new UxonObject();
        foreach ($uxon as $attr => $val) {
            if ($attr != 'action_alias' && strpos($attr, "action_") === 0) {
                $uxon->unsetProperty($attr);
                $attr = substr($attr, 7);
                $action_options->setProperty($attr, $val);
            }
        }
        if (! $action_options->isEmpty()) {
            $uxon->setProperty('action_options', $action_options);
        }
        parent::importUxonObject($uxon);
    }

    /**
     * Sets options of the action, defined in the button's description.
     * NOTE: the action must be defined first!
     *
     * @param UxonObject $action_options            
     * @throws WidgetPropertyInvalidValueError
     * @return Button
     */
    protected function setActionOptions(UxonObject $action_options)
    {
        if (is_null($this->action)) {
            $this->action_uxon = $action_options;
        } else {
            $this->action->importUxonObject($action_options);
        }
        
        return $this;
    }

    /**
     * Returns the hotkeys bound to this button.
     *
     * @see set_hotkey()
     * @return string
     */
    public function getHotkey()
    {
        return $this->hotkey;
    }

    /**
     * Make the button perform it's action when the hotkey is pressed.
     * Hotkeys can be passed in JS manner: ctrl+z, alt+q, etc. Multiple hotkeys can be used by separating them by comma.
     * If multiple hotkeys defined, they will all act exactly the same.
     *
     * @uxon-property hotkey
     * @uxon-type string
     *
     * @param string $value  
     * @return Button          
     */
    public function setHotkey($value)
    {
        $this->hotkey = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIcon()
     */
    public function getIcon() : ?string
    {
        $icon = $this->getIconViaTrait();
        if (! $icon && $this->getAction()) {
            return $this->getAction()->getIcon();
        }
        return $icon;
    }

    public function getRefreshInput() : bool
    {
        return $this->refresh_input;
    }

    /**
     * Set to FALSE to prevent the button from refreshing it's input widget automatically.
     * Default: TRUE.
     *
     * @uxon-property refresh_input
     * @uxon-type boolean
     *
     * @param boolean $value            
     */
    public function setRefreshInput(bool $value) : Button
    {
        $this->refresh_input = $value;
        return $this;
    }

    /**
     * The Button may have a child widget, if the action it triggers shows a widget.
     * NOTE: the widget description will only be returned, if the widget is explicitly defined, not merely by a link to another resource.
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        $action = $this->getAction();
        if ($action && $action->implementsInterface('iShowWidget') && $action->isWidgetDefined()) {
            yield $this->getAction()->getWidget();
        }
    }

    /**
     * The button's caption falls back to the name of the action if there is no caption defined explicitly and the button has an action.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption() : ?string
    {
        $caption = parent::getCaption();
        if (is_null($caption) && $this->getAction()) {
            $caption = $this->getAction()->getName();
        }
        return $caption;
    }

    /**
     * @deprecated use setRefreshWidgetIds() instead!
     *
     * @param WidgetLinkInterface|UxonObject|string $widget_link_or_uxon_or_string            
     * @return \exface\Core\Widgets\Button
     */
    public function setRefreshWidgetLink($widget_link_or_uxon_or_string)
    {
        if ($widget_link_or_uxon_or_string === null) {
            $this->refreshWidgetIds = [];
        } else {
            if ($widget_link_or_uxon_or_string instanceof WidgetLinkInterface) {
                $this->refreshWidgetIds = [$widget_link_or_uxon_or_string->getTargetWidgetId()];
            } else {
                $this->refreshWidgetIds = [(WidgetLinkFactory::createFromWidget($this, $widget_link_or_uxon_or_string))->getTargetWidgetId()];
            }
        }
        return $this;
    }
    
    /**
     * Returns the ids of widgets to refresh after the button's action succeeds.
     * 
     * By default, the result will include the id of the input widget if it must be
     * refreshed too (i.e. `refresh_input` is `true`). Set the parameter `$includeInputWidget`
     * to `false` to get only the additional refresh-widgets.
     * 
     * @param bool $includeInputWidget
     * 
     * @return string[]
     */
    public function getRefreshWidgetIds(bool $includeInputWidget = true) : array
    {
        if ($includeInputWidget && $this->getRefreshInput() === true) {
            return array_merge($this->refreshWidgetIds, [$this->getInputWidget()->getId()]);
        }
        return $this->refreshWidgetIds;
    }
    
    /**
     * Ids of widgets to refresh after the button's action was complete successfully.
     * 
     * @uxon-property refresh_widget_ids
     * @uxon-type uxon:$..id[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $value
     * @return Button
     */
    public function setRefreshWidgetIds($uxonOrArray) : Button
    {
        if ($uxonOrArray instanceof UxonObject) {
            $array = $uxonOrArray->toArray();
        } elseif (is_array($uxonOrArray)) {
            $array = $uxonOrArray;
        } else {
            throw new WidgetConfigurationError($this, 'Invalid value "' . $uxonOrArray . '" of property "refresh_widget_ids" in widget "' . $this->getWidgetType() . '": expecting PHP or UXON array!');
        }
        $array = array_unique($array);
        
        // If the button itself has an id space (= e.g. is inside a dialog), and the provided
        // ids don't have an id space, we should prefix them with the id space of the button,
        // so they will be resolved within the same space as the button itself.
        if ($idSpace = $this->getIdSpace()) {
            foreach ($array as $no => $id) {
                if(strpos($id, UiPage::WIDGET_ID_SPACE_SEPARATOR) === false) {
                    $array[$no] = $idSpace . UiPage::WIDGET_ID_SPACE_SEPARATOR . $id;
                }
            }
        }
        
        $this->refreshWidgetIds = $array;
        return $this;
    }
    

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO What do we do with the action here?
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHint()
     */
    public function getHint()
    {
        return parent::getHint() ? parent::getHint() : $this->getCaption();
    } 
    
    /**
     *
     * @return bool
     */
    public function getResetInput() : bool
    {
        if ($this->resetInputWidget === null) {
            if ($this->hasAction() === true && $this->getAction() instanceof iResetWidgets) {
                return true;
            }
        }
        return $this->resetInputWidget ?? false;
    }
    
    /**
     * Set to TRUE to reset the input widget to it's original state after the action of the button is performed.
     * 
     * @uxon-property reset_input
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Button
     */
    public function setResetInput(bool $value) : Button
    {
        $this->resetInputWidget = $value;
        return $this;
    }
    
    /**
     * Returns the ids of widgets to reset after the button's action succeeds.
     * 
     * By default, the result will include the id of the input widget if it must be
     * reset too (i.e. `reset_input` is `true`). Set the parameter `$includeInputWidget`
     * to `false` to get only the additional reset-widgets.
     * 
     * @param bool $includeInputWidget
     * 
     * @return string[]
     */
    public function getResetWidgetIds(bool $includeInputWidget = true) : ?array
    {
        if ($includeInputWidget && $this->getResetInput() === true) {
            return array_merge($this->resetWidgetIds, [$this->getInputWidget()->getId()]);
        }
        return $this->resetWidgetIds;
    }
    
    /**
     * Reset these widgets if the button's action is performed successfully.
     * 
     * @uxon-property reset_widget_ids
     * @uxon-type uxon:$..id[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $value
     * @return Button
     */
    public function setResetWidgetIds($uxonOrArray) : Button
    {
        if ($uxonOrArray instanceof UxonObject) {
            $array = $uxonOrArray->toArray();
        } elseif (is_array($uxonOrArray)) {
            $array = $uxonOrArray;
        } else {
            throw new WidgetConfigurationError($this, 'Invalid value "' . $uxonOrArray . '" of property "reset_widget_ids" in widget "' . $this->getWidgetType() . '": expecting PHP or UXON array!');
        }
        
        $this->resetWidgetIds = array_unique($array);
        return $this;
    }
    
    /**
     * Set this property if the button should be hidden if a user is not allowed access to the action bound to it.
     * 
     * @uxon-property hidden_if_access_denied
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return Button
     */
    public function setHiddenIfAccessDenied(bool $trueOrFalse) : Button
    {
      $this->hiddenIfAccessDenied = $trueOrFalse;
      return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::isHidden()
     */
    public function isHidden()
    {
        if ($this->hiddenIfAccessDenied === false) {
            return parent::isHidden();
        }        
        return $this->getAction()->isAuthorized() === false;
                   
    }
}