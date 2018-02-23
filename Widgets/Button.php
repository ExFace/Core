<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveChildren;
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

/**
 * A Button is the primary widget for triggering actions.
 *
 * In addition to the general widget attributes it can have an icon and also subwidgets (if the triggered action shows a widget).
 *
 * @author Andrej Kabachnik
 *        
 */
class Button extends AbstractWidget implements iHaveIcon, iTriggerAction, iDefineAction, iUseInputWidget, iHaveChildren, iCanBeAligned
{
    use iCanBeAlignedTrait;
    
    use iUseInputWidgetTrait;
    
    private $action_alias = null;

    private $action = null;
    
    private $action_uxon = null;

    private $active_condition = null;

    private $input_widget_id = null;

    private $input_widget = null;

    private $hotkey = null;

    private $icon = null;

    private $refresh_input = true;

    private $refresh_widget_link = null;

    private $hide_button_text = false;

    private $hide_button_icon = false;

    public function getAction()
    {
        if (is_null($this->action)) {
            if ($this->getActionAlias()) {
                $this->action = ActionFactory::createFromString($this->getWorkbench(), $this->getActionAlias(), $this);
            }
            if (! is_null($this->action_uxon)) {
                $this->action->importUxonObject($this->action_uxon);
            }
        }
        return $this->action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::hasAction()
     */
    public function hasAction()
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
            if (! $this->action->getTriggerWidget()){
                $this->action->setTriggerWidget($this);
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
     * @uxon-type string
     *
     * @param string $value
     * @return Button            
     */
    public function setActionAlias($value)
    {
        $this->action_alias = $value;
        return $this;
    }

    public function setCaption($caption)
    {
        // TODO get caption automatically from action model once it is created
        return parent::setCaption($caption);
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

    public function getIcon()
    {
        if (! $this->icon && $this->getAction()) {
            $this->icon = $this->getAction()->getIcon();
        }
        return $this->icon;
    }

    /**
     * Specifies the name of the icon to be displayed by this button.
     *
     * There are some default icons defined in the core, but every template is free to add more icons. The names of the latter
     * are, of course, absolutely template specific.
     *
     * @uxon-property icon
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIcon()
     */
    public function setIcon($value)
    {
        $this->icon = $value;
        return $this;
    }

    public function getRefreshInput()
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
    public function setRefreshInput($value)
    {
        $this->refresh_input = $value;
        return $this;
    }

    public function getHideButtonText()
    {
        return $this->hide_button_text;
    }

    /**
     * Set to TRUE to hide the button's caption leaving only the icon.
     * Default: FALSE.
     *
     * @uxon-property hide_button_text
     * @uxon-type boolean
     *
     * @param boolean $value  
     * @return Button          
     */
    public function setHideButtonText($value)
    {
        $this->hide_button_text = $value;
        return $this;
    }

    public function getHideButtonIcon()
    {
        return $this->hide_button_icon;
    }

    /**
     * Set to TRUE to hide the button's icon leaving only the caption.
     * Default: FALSE.
     *
     * @uxon-property hide_button_icon
     * @uxon-type boolean
     *
     * @param boolean $value 
     * @return Button           
     */
    public function setHideButtonIcon($value)
    {
        $this->hide_button_icon = $value;
        return $this;
    }

    /**
     * The Button may have a child widget, if the action it triggers shows a widget.
     * NOTE: the widget description will only be returned, if the widget is explicitly defined, not merely by a link to another resource.
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren()
    {
        $children = array();
        $action = $this->getAction();
        if ($action && $action->implementsInterface('iShowWidget') && $action->isWidgetDefined()) {
            $children[] = $this->getAction()->getWidget();
        }
        return $children;
    }

    /**
     * The button's caption falls back to the name of the action if there is no caption defined explicitly and the button has an action.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        $caption = parent::getCaption();
        if (is_null($caption) && $this->getAction()) {
            $caption = $this->getAction()->getName();
        }
        return $caption;
    }

    /**
     * Returns a link to the widget, that should be refreshed when this button is pressed.
     *
     * @return \exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getRefreshWidgetLink()
    {
        return $this->refresh_widget_link;
    }

    /**
     * Sets the link to the widget to be refreshed when this button is pressed.
     * Pass NULL to unset the link
     *
     * @uxon-property refresh_widget_link
     * @uxon-type string|\exface\Core\CommonLogic\WidgetLink
     *
     * @param WidgetLinkInterface|UxonObject|string $widget_link_or_uxon_or_string            
     * @return \exface\Core\Widgets\Button
     */
    public function setRefreshWidgetLink($widget_link_or_uxon_or_string)
    {
        if (is_null($widget_link_or_uxon_or_string)) {
            $this->refresh_widget_link = null;
        } else {
            $exface = $this->getWorkbench();
            if ($link = WidgetLinkFactory::createFromAnything($exface, $widget_link_or_uxon_or_string, $this->getIdSpace())) {
                $this->refresh_widget_link = $link;
            }
        }
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
}
?>
