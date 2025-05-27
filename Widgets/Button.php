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
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Contexts\DebugContext;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Widgets\iCanBeBoundToAttribute;

/**
 * A Button is the primary widget for triggering actions.
 *
 * In addition to the general widget attributes it can have an icon and also subwidgets (if the triggered action shows
 * a widget).
 *
 * @author Andrej Kabachnik
 *
 */
class Button extends AbstractWidget implements iHaveIcon, iHaveColor, iTriggerAction, iDefineAction, iUseInputWidget, iCanBeAligned, iCanBeDisabled
{
    use iCanBeAlignedTrait;

    use iUseInputWidgetTrait;

    use iHaveIconTrait {
        getIcon as getIconFromButton;
        getIconSet as getIconSetFromButton;
    }

    use iHaveColorTrait;

    const APPEARANCE_DEFAULT = 'default';

    const APPEARANCE_FILLED = 'filled';

    const APPEARANCE_STROKED = 'stroked';

    const APPEARANCE_LINK = 'link';

    /**
     * Press the button (default button function)
     *
     * @uxon-property press
     *
     * @var string
     */
    const FUNCTION_PRESS = 'press';

    /**
     * Focus the button
     *
     * @uxon-property focus
     *
     * @var string
     */
    const FUNCTION_FOCUS = 'focus';

    private $action_alias = null;

    private $action = null;

    private $action_uxon = null;

    private $active_condition = null;

    private $input_widget_id = null;

    private $input_widget = null;

    private $hotkey = null;

    private $refresh_input = null;

    private $refreshWidgetIds = [];

    private $resetInputWidget = null;

    private $hiddenIfAccessDenied = false;

    private $appearance = self::APPEARANCE_DEFAULT;

    private $inputDataUxon = null;

    private $hiddenIfInputInvalid = false;

    private $disabledIfInputInvalid = true;

    private $showIcon = null;

    /**
     *
     * @var string[]
     */
    private $resetWidgetIds = [];

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->hiddenIfAccessDenied = $this->getWorkbench()->getConfig()->getOption('WIDGET.BUTTON.HIDDEN_IF_ACCESS_DENIED');
    }

    public function getAction()
    {
        if ($this->action === null) {
            if ($this->action_alias !== null) {
                try {
                    $this->action = ActionFactory::createFromString($this->getWorkbench(), $this->getActionAlias(), $this);
                    if ($this->action_uxon !== null) {
                        $this->action->importUxonObject($this->action_uxon);
                    }
                } catch (\Throwable $e) {
                    throw new WidgetConfigurationError($this, 'Error in Button widget: ' . $e->getMessage(), null, $e);
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
        return $this->action !== null || $this->action_alias !== null || $this->action_uxon !== null;
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
        if ($this->action !== null) {
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
        $this->action_alias = $value === '' ? null : $value;
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
        $icon = $this->getIconFromButton();
        if (! $icon && $this->getAction()) {
            return $this->getAction()->getIcon();
        }
        return $icon;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIconSet()
     */
    public function getIconSet() : ?string
    {
        $iconSet = $this->getIconSetFromButton();
        if (! $iconSet && $this->getAction()) {
            return $this->getAction()->getIconSet();
        }
        return $iconSet;
    }

    /**
     * Returns TRUE if the input widget is to be refreshed after the action succeeds
     *
     * @return bool|NULL
     */
    public function getRefreshInput() : ?bool
    {
        return $this->refresh_input;
    }

    /**
     * Set to FALSE to prevent the button from refreshing it's input widget automatically or to TRUE to force refresh
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
     * NOTE: the widget description will only be returned, if the widget is explicitly defined, not merely by a link to
     * another resource.
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        $action = $this->getAction();
        if ($action) {
            yield from $this->getChildrenFromAction($action);
        }
    }

    /**
     *
     * @param ActionInterface $action
     * @return \Iterator
     */
    protected function getChildrenFromAction(ActionInterface $action) : \Iterator
    {
        if (($action instanceof iShowWidget) && $action->isWidgetDefined()) {
            yield $action->getWidget();
        }
        if ($action instanceof iCallOtherActions) {
            foreach ($action->getActions() as $child) {
                yield from $this->getChildrenFromAction($child);
            }
        }
    }

    /**
     * The button's caption falls back to the name of the action if there is no caption defined explicitly and the
     * button has an action.
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
        if ($includeInputWidget && $this->getRefreshInput() !== false) {
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
    public function getHint(bool $includeDebugInfo = true) : ?string
    {
        $hint = parent::getHint(false);

        if (empty($hint) === true && $this->hasAction()) {
            $hint = $this->getAction()->getHint();
        }

        if (empty($hint) === true) {
            $hint = $this->getCaption();
        }

        // Disabled reason
        if ($this->isDisabled() && $disabledReason = $this->getDisabledReason()) {
            $hint .= ($hint ? "\n\n" : '') . $disabledReason;
        }

        // Dev-hint
        if ($includeDebugInfo === true && $this->getWorkbench()->getContext()->getScopeWindow()->hasContext(DebugContext::class)) {
            $hint = ($hint ? StringDataType::endSentence($hint) : '') . $this->getHintDebug();
        }

        return $hint;
    }

    /**
     *
     * @return string
     */
    protected function getHintDebug() : string
    {
        $hint = parent::getHintDebug();
        if ($this->hasAction()) {
            $actionAliasHint = "`{$this->getAction()->getAliasWithNamespace()}`";
            $actionObjectHint = $this->getAction()->getMetaObject()->__toString();
        } else {
            $actionAliasHint = 'no action defined';
            $actionObjectHint = 'no action defined';
        }
        return $hint . "\n- Action alias: {$actionAliasHint} \n- Action object: '{$actionObjectHint}' \n- Button object: {$this->getMetaObject()->__toString()}";
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
     * The default value is set in the core configuration file `exface.Core.config.json` as
     * `WIDGET.BUTTON.HIDDEN_IF_ACCESS_DENIED`.
     *
     * @uxon-property hidden_if_access_denied
     * @uxon-type boolean
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
        $hiddenExplicitly = parent::isHidden();
        if ($hiddenExplicitly === true || $this->hiddenIfAccessDenied === false || $this->hasAction() === false) {
            return $hiddenExplicitly;
        }
        return $this->getAction()->isAuthorized() === false;
    }

    public function getAppearance() : string
    {
        return $this->appearance;
    }

    /**
     * Change the way, how the button is displayed: `filled`, `stroked`, `link`, etc.
     *
     * By default, the facade will pick an appearance automatically based on it's
     * internal logic and the button's `visibility`.
     *
     * @uxon-property appearance
     * @uxon-type [default,link,stroked,filled]
     * @uxon-default default
     *
     * @param string $value
     * @throws WidgetConfigurationError
     * @return Button
     */
    public function setAppearance(string $value) : Button
    {
        $constName = 'self::APPEARANCE_' . strtoupper($value);
        if (! defined($constName)) {
            throw new WidgetConfigurationError('Invalid value "' . $value . '" for property `appearance` of widget "' . $this->getWidgetType() . '": expecting `default`, `link`, `filled` or `stroked`.');
        }
        $this->appearance = constant($constName);
        return $this;
    }

    public function getInputData() : ?DataSheetInterface
    {
        if ($this->inputDataUxon !== null) {
            return DataSheetFactory::createFromUxon($this->getWorkbench(), $this->inputDataUxon);
        }

        return null;
    }

    /**
     * Custom input data for this buttons action.
     *
     * Allows to specify input data for this specific button explicitly - including live references to
     * other widgets. In contrast to `input_mapper` and `input_data_sheet` of the action, this property
     * only affects this one button and not the action in general. Hence, it allows to reference other
     * widgets on the page. This makes it possible to "gather" any data from the page without sticking
     * to a single widget - you can even put together values from widgets with different objects!
     *
     * ```
     *  "input_data": {
     *      "object_alias": "my.App.my_object",
     *      "rows": [
     *          {
     *              "attribute1": "=some_widget_id",
     *              "attribute2": "=some_table_id!column",
     *              "attribute3": 23,
     *              "attribute4": "=Now()"
     *          }
     *      ]
     *  }
     *
     * ```
     *
     * @uxon-property input_data
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "rows": [{"": ""}]}
     *
     * @param UxonObject $value
     * @return Button
     */
    public function setInputData(UxonObject $value) : Button
    {
        $this->inputDataUxon = $value;
        return $this;
    }

    /**
     * The button will attempt generate its disabled_if automatically from action input_invalid_if
     *
     * @see \exface\Core\Widgets\AbstractWidget::getDisabledIf()
     */
    public function getDisabledIf() : ?ConditionalProperty
    {
        // If there is a disabled_if already, use it
        $ownProperty = parent::getDisabledIf();
        if ($ownProperty !== null) {
            return $ownProperty;
        }
        // Otherwise see if we can generate one from the action
        if (! $this->hasAction()) {
            return null;
        }

        if ($this->isDisabledIfInputInvalid() === true && null !== $uxon = $this->getConditionalPropertyFromAction($this->getAction())) {
            $this->setDisabledIf($uxon);
        }

        return parent::getDisabledIf();
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHiddenIf()
     */
    public function getHiddenIf() : ?ConditionalProperty
    {
        // If there is a disabled_if already, use it
        $ownProperty = parent::getHiddenIf();
        if ($ownProperty !== null) {
            return $ownProperty;
        }
        // Otherwise see if we can generate one from the action
        if (! $this->hasAction()) {
            return null;
        }

        $hiddenIfInvalid = null;
        $hiddenIfUnauthorized = null;
        if ($this->isHiddenIfInputInvalid() === true && null !== $uxon = $this->getConditionalPropertyFromAction($this->getAction())) {
            $hiddenIfInvalid = $uxon;
        }
        if ($this->hiddenIfAccessDenied === true) {
            $inputWidget = $this->getInputWidget();
            $formula = "=IsButtonAuthorized('{$this->getPage()->getAliasWithNamespace()}', '{$this->getId()}')";
            $formulaLink = null;
            switch (true) {
                case $inputWidget instanceof iShowData:
                case $inputWidget instanceof iUseData:
                    $dataWidget = $inputWidget instanceof iUseData ? $inputWidget->getData() : $inputWidget;
                    if (! $formulaCol = $dataWidget->getColumnByExpression($formula)) {
                        $formulaCol = $dataWidget->createColumnFromUxon(new UxonObject([
                            'calculation' => $formula,
                            'hidden' => true,
                            'exportable' => false
                        ]));
                        $dataWidget->addColumn($formulaCol);
                    }
                    $formulaLink = "=~input!{$formulaCol->getDataColumnName()}";
                    break;
                case $inputWidget instanceof iContainOtherWidgets:
                    // TODO how to add the formula to containers?
                    break;
            }
            if ($formulaLink !== null) {
                $hiddenIfUnauthorized = new UxonObject([
                    'conditions' => [
                        [
                            'value_left' => $formulaLink,
                            'comparator' => ComparatorDataType::EQUALS,
                            'value_right' => false
                        ]
                    ]
                ]);
            }
        }

        switch (true) {
            case $hiddenIfInvalid !== null && $hiddenIfUnauthorized === null:
                $this->setHiddenIf($hiddenIfInvalid);
                break;
            case $hiddenIfUnauthorized !== null && $hiddenIfInvalid === null:
                $this->setHiddenIf($hiddenIfUnauthorized);
                break;
            case $hiddenIfInvalid !== null && $hiddenIfUnauthorized !== null:
                $this->setHiddenIf(new UxonObject([
                    'operator' => EXF_LOGICAL_OR,
                    'nested_groups' => [
                        $hiddenIfInvalid->toArray(),
                        $hiddenIfUnauthorized->toArray()
                    ]
                ]));
                break;
        }

        return parent::getHiddenIf();
    }

    /**
     *
     * @param ActionInterface $action
     * @return UxonObject|NULL
     */
    protected function getConditionalPropertyFromAction(ActionInterface $action) : ?UxonObject
    {
        // If there the input is not going to be used, don't bother at all
        if (($action instanceof iPrefillWidget) && $action->getPrefillWithInputData() === false) {
            return null;
        }

        $inputWidget = $this->getInputWidget();
        $inputWidgetObject = $inputWidget->getMetaObject();

        // Generate an xxx_if property for every check, that is applicable to the input widgets object
        // If there will be multiple checks, we gather them into an array and use them in a nested
        // OR condition later
        $uxons = [];
        /* @var $check \exface\Core\CommonLogic\DataSheets\DataCheck */
        foreach ($action->getInputChecks()->getAll() as $check) {
            if (! $check->isApplicableToObject($inputWidgetObject)) {
                continue;
            }

            // If we have found a check, we need to make sure, the input widget will
            // be able to supply enough data.
            /* @var $condGrp \exface\Core\CommonLogic\Model\ConditionGroup */
            $condGrp = $check->getConditionGroup($inputWidgetObject);
            switch (true) {
                case $inputWidget instanceof iShowData:
                    $propUxon = $this->getConditionalPropertyForData($inputWidget, $condGrp);
                    break;
                case $inputWidget instanceof iUseData:
                    $propUxon = $this->getConditionalPropertyForData($inputWidget->getData(), $condGrp);
                    break;
                case $inputWidget instanceof iContainOtherWidgets:
                    $propUxon = $this->getConditionalPropertyForContainer($inputWidget, $condGrp);
                    break;
            }
            if ($propUxon !== null) {
                $uxons[] = $propUxon;
            }
        }

        // If there is only one check, use that as-is
        if (count($uxons) === 1) {
            return $uxons[0];
        }
        // If there are multiple checks, wrap them in an OR-condition
        if (count($uxons) > 1) {
            $uxon = new UxonObject([
                'operator' => 'OR'
            ]);
            foreach ($uxons as $propUxon) {
                $uxon->appendToProperty('condition_groups', $propUxon);
            }
            return $uxon;
        }

        return null;
    }

    /**
     * Transforms the given condition group into a conditional property UXON for the provided data widget
     *
     * Returns NULL if no conditional property can be generated automatically!
     *
     * The data widget will be able to supply required data if each condition compares
     * an existing column with a static value. Currently, that should be mostly the case as regular
     * Conditions will have an attribute or a formula on the left side and a static value on the right.
     *
     * @param Data $dataWidget
     * @param ConditionGroupInterface $condGrp
     * @return UxonObject|null
     */
    protected function getConditionalPropertyForData(Data $dataWidget, ConditionGroupInterface $condGrp) : ?UxonObject
    {
        $uxon = new UxonObject([
            'operator' => $condGrp->getOperator()
        ]);

        $dataMultiSelect =  ($dataWidget instanceof iSupportMultiSelect) ? $dataWidget->getMultiSelect() : false;

        // Transform each condition into a conditional property condition
        foreach ($condGrp->getConditions() as $cond) {
            $condExpr = $cond->getExpression();
            switch (true) {
                // TODO Widget links on the left side of expressions are currently not supported properly,
                // so we just skip the entire ConditionGroup if we encounter such case.
                // If left-side links will be supported in future, we will need to change this here.
                case $condExpr->isReference():
                    return null;
                    break;
                // Skip empty condition expressions
                case $condExpr->isEmpty():
                    continue 2;
                /* In previous versions there was this separate static expression handling. After adding support for
                 * non-static formulas, there seemed no point in separating static ones, so they were both moved to
                 * the default branch
                case $condExpr->isStatic():
                    $uxon->appendToProperty('conditions', new UxonObject([
                        "value_left" => $cond->getExpression()->__toString(),
                        "comparator" => $cond->getComparator(),
                        "value_right" => $cond->getRightExpression()->__toString()
                    ]));
                    break;
                */
                // Formulas and attributes simply need to be added to the data widget, so they can be read with
                // all the other data. After that, we can compare them in JavaScript easily - just need to reference
                // the data column name.
                default:
                    // Check if there is already such a column. If not, add a hidden one
                    if (! $col = $dataWidget->getColumnByExpression($cond->getExpression()->__toString())) {
                        $colUxon = new UxonObject([
                            'hidden' => true,
                            'exportable' => false,
                            'sortable' => false,
                            'filterable' => false
                        ]);
                        if ($condExpr->isMetaAttribute()) {
                            $colUxon->setProperty('attribute_alias', $cond->getAttributeAlias());
                        } else {
                            $colUxon->setProperty('calculation', $condExpr->__toString());
                        }
                        $col = $dataWidget->createColumnFromUxon($colUxon);
                        $dataWidget->addColumn($col);
                    }

                    $comp = $cond->getComparator();
                    // Multi-select data widget can only handle list-comparators properly as their
                    // value is mostly a list.
                    if ($dataMultiSelect === true) {
                        $comp = ComparatorDataType::convertToListComparator($comp, false);
                        if ($comp === null) {
                            return null;
                        }
                    }

                    $uxon->appendToProperty('conditions', new UxonObject([
                        "value_left" => "=~input!" . $col->getDataColumnName(),
                        "comparator" => $comp,
                        "value_right" => $cond->getRightExpression()->__toString()
                    ]));
                    break;
            }
        }

        // Transform nested groups into conditional property condition groups by calling this method recursively
        foreach ($condGrp->getNestedGroups() as $nestedGrp) {
            $nestedUxon = $this->getConditionalPropertyForData($dataWidget, $nestedGrp);
            if ($nestedUxon === null) {
                if ($condGrp->getOperator() === EXF_LOGICAL_OR) {
                    continue;
                } else {
                    return null;
                }
            }
            $uxon->appendToProperty('condition_groups', $nestedUxon);
        }

        return $uxon;
    }

    protected function getConditionalPropertyForContainer(iContainOtherWidgets $containerWidget, ConditionGroupInterface $condGrp) : ?UxonObject
    {
        $uxon = new UxonObject([
            'operator' => $condGrp->getOperator()
        ]);

        // The data widget will be able to supply required data if each condition compares
        // an existing column with a scalar value
        foreach ($condGrp->getConditions() as $cond) {
            switch (true) {
                case $cond->getExpression()->isMetaAttribute():
                    $attrAlias = $cond->getExpression()->__toString();
                    if (! $containerWidget->getMetaObject()->hasAttribute($attrAlias)) {
                        return null;
                    }
                    $matches = $containerWidget->findChildrenRecursive(function($child) use ($attrAlias, $containerWidget) {
                        return ($child instanceof iTakeInput)
                            && $child->getMetaObject()->isExactly($containerWidget->getMetaObject())
                            && ($child instanceof iCanBeBoundToAttribute)
                            && $child->isBoundToAttribute()
                            && $child->getAttributeAlias() === $attrAlias;
                    });
                    if (! $w = $matches[0] ?? null) {
                        /*
                        $w = WidgetFactory::createFromUxonInParent($containerWidget, new UxonObject([
                            'widget_type' => 'InputHidden',
                            'attribute_alias' => $attrAlias
                        ]));
                        $containerWidget->addWidget($w);*/
                        return null;
                    }
                    $uxon->appendToProperty('conditions', new UxonObject([
                        "value_left" => "=~input!" . $w->getDataColumnName(),
                        "comparator" => $cond->getComparator(),
                        "value_right" => $cond->getRightExpression()->__toString()
                    ]));
                    break;
                case $cond->getExpression()->isStatic():
                    $comp = $cond->getComparator();
                    $uxon->appendToProperty('conditions', new UxonObject([
                        "value_left" => $cond->getExpression()->__toString(),
                        "comparator" => $comp,
                        "value_right" => $cond->getRightExpression()->__toString()
                    ]));
                    break;
                default:
                    return null;
            }
        }

        foreach ($condGrp->getNestedGroups() as $nestedGrp) {
            $nestedUxon = $this->getConditionalPropertyForContainer($containerWidget, $nestedGrp);
            if ($nestedUxon === null) {
                if ($condGrp->getOperator() === EXF_LOGICAL_OR) {
                    continue;
                } else {
                    return null;
                }
            }
            $uxon->appendToProperty('condition_groups', $nestedUxon);
        }

        return $uxon;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        if ($this->hasAction() && parent::getDisabledIf() === null) {
            $this->getDisabledIf();
        }
        return $data_sheet;
    }

    /**
     *
     * @return bool
     */
    public function isHiddenIfInputInvalid() : bool
    {
        return $this->hiddenIfInputInvalid;
    }

    /**
     * Set to TRUE to hide the button if the input does not match the actions `input_invalid_if`.
     *
     * **NOTE:** this only works reliably if the buttons input widget (e.g. the DataTable) contains all
     * values required to evaluate the `input_invalid_if` conditions (and if the facade used supports this
     * feature of course). If anything is missing, the button will try to add the required columns
     * automatically, but this is not always possible.
     *
     * If automatic hiding does not work, the Button will remain active and the `input_invalid_if`
     * conditions will be evaluated by the server - when trying to perform the action.
     *
     * You can also try to fix issues by adding the required expressions as hidden column to the input
     * widget manully. Or use an explicit `hidden_if` configuration for the button instead.
     *
     * @uxon-property hidden_if_input_invalid
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return Button
     */
    public function setHiddenIfInputInvalid(bool $value) : Button
    {
        $this->hiddenIfInputInvalid = $value;
        return $this;
    }

    /**
     *
     * @return bool
     */
    public function isDisabledIfInputInvalid() : bool
    {
        return $this->disabledIfInputInvalid;
    }

    /**
     * Set to TRUE to disable the button if the input does not match the actions `input_invalid_if`.
     *
     * **NOTE:** this only works reliably if the buttons input widget (e.g. the DataTable) contains all
     * values required to evaluate the `input_invalid_if` conditions (and if the facade used supports this
     * feature of course). If anything is missing, the button will try to add the required columns
     * automatically, but this is not always possible.
     *
     * If automatic disabling does not work, the Button will remain active and the `input_invalid_if`
     * conditions will be evaluated by the server - when trying to perform the action.
     *
     * You can also try to fix issues by adding the required expressions as hidden column to the input
     * widget manully. Or use an explicit `disabled_if` configuration for the button instead.
     *
     * @uxon-property disabled_if_input_invalid
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return Button
     */
    public function setDisabledIfInputInvalid(bool $value) : Button
    {
        $this->disabledIfInputInvalid = $value;
        return $this;
    }

    /**
     *
     * @param bool $default
     * @return bool|NULL
     */
    public function getShowIcon(bool $default = null) : ?bool
    {
        // If show_icon is not set explicitly, set it to true when specifying an icon.
        // Indeed, if the user specifies and icon, it is expected to be show, isn't it?
        if ($this->getIconFromButton() !== null && $this->showIcon !== false) {
            return true;
        }
        return $this->showIcon ?? $default;
    }

    /**
     * Force the icon to show (TRUE) or hide (FALSE)
     *
     * The default depends on the facade used.
     *
     * @uxon-property show_icon
     * @uxon-type boolean
     *
     * @param bool $value
     * @return iHaveIcon
     */
    public function setShowIcon(bool $value) : iHaveIcon
    {
        $this->showIcon = $value;
        return $this;
    }
}