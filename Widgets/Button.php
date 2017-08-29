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
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Interfaces\Widgets\iUseInputWidget;

/**
 * A Button is the primary widget for triggering actions.
 *
 * In addition to the general widget attributes it can have an icon and also subwidgets (if the triggered action shows a widget).
 *
 * @author Andrej Kabachnik
 *        
 */
class Button extends AbstractWidget implements iHaveIcon, iTriggerAction, iUseInputWidget, iHaveChildren, iCanBeAligned
{
    use iCanBeAlignedTrait;
    
    private $action_alias = null;

    private $action = null;

    private $active_condition = null;

    private $input_widget_id = null;

    private $input_widget = null;

    private $hotkey = null;

    private $icon_name = null;

    private $refresh_input = true;

    private $refresh_widget_link = null;

    private $hide_button_text = false;

    private $hide_button_icon = false;

    public function getAction()
    {
        if (! $this->action) {
            if ($this->getActionAlias()) {
                $this->action = ActionFactory::createFromString($this->getWorkbench(), $this->getActionAlias(), $this);
            }
        }
        return $this->action;
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
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::setAction()
     */
    public function setAction($action_object_or_uxon_description)
    {
        if ($action_object_or_uxon_description instanceof ActionInterface) {
            $this->action = $action_object_or_uxon_description;
            // Let the action know, it was (or will be) called by this button
            // unless the action already has a called-by-widget. This would be
            // the case if the same action is assigned to multiple buttons. 
            // Although this feature is currently not explicitly used, it seems
            // a decent idea to share an action between buttons: e.g. a toolbar
            // button and a menu button which actually do exactly the same thing.
            if (! $this->action->getCalledByWidget()){
                $this->action->setCalledByWidget($this);
            }
        } elseif ($action_object_or_uxon_description instanceof \stdClass) {
            $this->setActionAlias($action_object_or_uxon_description->alias);
            $this->setActionOptions($action_object_or_uxon_description);
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'The set_action() method of a button accepts either an action object extended from ActionInterface or a UXON description object. ' . gettype($action_object_or_uxon_description) . ' given for button "' . $this->getId() . '".', '6T919D5');
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
     * Returns the id of the widget, which the action is supposed to be performed upon.
     * I.e. if it is an Action doing something with a table row, the input widget will be
     * the table. If the action ist to be performed upon an Input field - that Input is the input widget.
     *
     * By default the input widget is the actions parent
     */
    public function getInputWidgetId()
    {
        if (! $this->input_widget_id) {
            if ($this->input_widget) {
                $this->setInputWidgetId($this->getInputWidget()->getId());
            } else {
                $this->setInputWidgetId($this->getParent()->getId());
            }
        }
        return $this->input_widget_id;
    }

    /**
     * Sets the id of the widget to be used to fetch input data for the action performed by this button.
     *
     * @uxon-property input_widget_id
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setInputWidgetId($value)
    {
        $this->input_widget_id = $value;
        return $this;
    }
    
    /**
     * Returns the input widget of the button.
     * 
     * If no input widget was set for this button explicitly (via UXON or
     * programmatically using setInputWidget()), the input widget will be
     * determined automatically:
     * - If the parent of the button is a button or a button group, the input
     * widget will be inherited
     * - If the parent of the widget has buttons (e.g. a Data widget), it will
     * be used as input widget
     * - Otherwise the search for those criteria will continue up the hierarchy
     * untill the root widget is reached. If no match is found, the root widget
     * itself will be returned.
     * 
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::getInputWidget()
     */
    public function getInputWidget()
    {
        if (is_null($this->input_widget)) {
            if ($this->input_widget_id) {
                $this->input_widget = $this->getUi()->getWidget($this->input_widget_id, $this->getPageId());
            } elseif ($this->getParent()) {
                $parent = $this->getParent();
                while (!(($parent instanceof iHaveButtons) || ($parent instanceof iUseInputWidget)) && ! is_null($parent->getParent())) {
                    $parent = $parent->getParent();
                }
                if ($parent instanceof iUseInputWidget){
                    $this->input_widget = $parent->getInputWidget();
                } else {
                    $this->input_widget = $parent;
                }
            }
        }
        return $this->input_widget;
    }

    public function setInputWidget(AbstractWidget $widget)
    {
        $this->input_widget = $widget;
        $this->setInputWidgetId($widget->getId());
        return $this;
    }

    /**
     * Buttons allow to set action options as an options array or directly as an option of the button itself.
     * In the latter case the option's name must be prefixed by "action_": to set a action's property
     * called "script" simply add "action_script": XXX to the button.
     *
     * @see \exface\Core\Widgets\AbstractWidget::importUxonObject()
     */
    public function importUxonObject(\stdClass $source)
    {
        // If there are button attributes starting with "action_", these are just shortcuts for
        // action attributes. We need to remove them from the button's description an pass
        // them all in on "action_options" attribute. The only exclusion is the action_alias, which
        // we need to instantiate the action.
        $action_options = $source->action_options ? $source->action_options : new \stdClass();
        foreach ($source as $attr => $val) {
            if ($attr != 'action_alias' && strpos($attr, "action_") === 0) {
                unset($source->$attr);
                $attr = substr($attr, 7);
                $action_options->$attr = $val;
            }
        }
        if (count((array) $action_options)) {
            $source->action_options = $action_options;
        }
        parent::importUxonObject($source);
    }

    /**
     * Sets options of the action, defined in the button's description.
     * NOTE: the action must be defined first!
     *
     * @param \stdClass $action_options            
     * @throws WidgetPropertyInvalidValueError
     * @return Button
     */
    protected function setActionOptions(\stdClass $action_options)
    {
        if (! $action = $this->getAction()) {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set action properties prior to action initialization! Please ensure, that the action_alias is defined first!', '6T919D5');
        } else {
            $action->importUxonObject($action_options);
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

    public function getIconName()
    {
        if (! $this->icon_name && $this->getAction()) {
            $this->icon_name = $this->getAction()->getIconName();
        }
        return $this->icon_name;
    }

    /**
     * Specifies the name of the icon to be displayed by this button.
     *
     * There are some default icons defined in the core, but every template is free to add more icons. The names of the latter
     * are, of course, absolutely template specific.
     *
     * @uxon-property icon_name
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIconName()
     */
    public function setIconName($value)
    {
        $this->icon_name = $value;
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
