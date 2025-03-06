<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Events\Widget\OnGlobalActionsAddedEvent;

/**
 * A special toolbar for data widgets that includes global actions and search actions automatically.
 * 
 * The auto-generated buttons are added as separate button groups __after__ any regular buttons.
 * Search actions have opposite alignment by default.
 * 
 * If a `DataToolbar` is instantiated manually, the properties `include_global_actions`
 * and `include_search_actions` can used to control automatically added buttons explicitly.
 * By default button groups for these actions are only added to the main toolbar of a
 * widget.
 * 
 * @see Toolbar
 * 
 * @triggers \exface\Core\Events\Widgets\OnGlobalActionsAddedEvent
 *
 * @author Andrej Kabachnik
 *        
 */
class DataToolbar extends Toolbar
{    
    /** @var boolean */
    private $disable_autoinclude_actions = false;
    
    /** @var boolean */
    private $include_global_actions = null;
    
    /** @var boolean */
    private $include_search_actions = null;
    
    /** @var ButtonGroup */
    private $global_action_button_group = null;
    
    /** @var ButtonGroup */
    private $search_button_group = null;
    
    private $included_buttons_initialized = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setParent()
     */
    public function setParent(WidgetInterface $widget)
    {
        if (! $this->is($widget->getToolbarWidgetType())
        && ! $widget instanceof DataToolbar){
            throw new WidgetConfigurationError($this, 'The widget DataToolbar can only be used within Data widgets or other DataToolbars');
        }
        return parent::setParent($widget);
    }
    
    /**
     * Returns the data widget the toolbar belongs to.
     * 
     * @return Data
     */
    public function getDataWidget() : ?iShowData
    {
        $inputWidget = $this->getInputWidget();
        switch (true) {
            case $inputWidget instanceof iShowData:
                return $inputWidget;
            case $inputWidget instanceof iUseData:
                return $inputWidget->getData();
            default:
                return null;
        }
    }
    
    /**
     * Returns TRUE if no actions should be included automatically and FALSE otherwise.
     * 
     * @return boolean $disable_autoinclude_actions
     */
    public function getIncludeNoExtraActions() : bool
    {
        return $this->disable_autoinclude_actions;
    }
    
    /**
     * Set to TRUE to disable automatically included buttong groups for this toolbar completely.
     * 
     * Defaults to FALSE. Each type of autoinclude can be disabled separately
     * using the include_xxx_actions properties.
     * 
     * @uxon-property include_no_extra_actions
     * @uxon-value boolean
     * @uxon-default false
     * 
     * @param boolean $disable_autoinclude_actions
     * @return DataToolbar
     */
    public function setIncludeNoExtraActions(bool $true_or_false) : DataToolbar
    {
        $this->disable_autoinclude_actions = $true_or_false;
        $this->included_buttons_initialized = false;
        return $this;
    }
    
    /**
     * Returns TRUE if actions defined in the core config option 
     * WIDGET.DATATOOLBAR.GLOBAL_ACTIONS are to be included in this toolbar and 
     * FALSE otherwise.
     * 
     * Defaults to TRUE for the main toolbar of a widget and FALSE otherwise.
     * 
     * @return bool
     */
    public function getIncludeGlobalActions() : bool
    {
        if ($this->include_global_actions === null && $this->isMainToolbar()){
            return true;
        }
        return $this->include_global_actions ?? false;
    }
    
    /**
     * Set to FALSE to disable global actions for this toolbar.
     * 
     * Defaults to TRUE for the main toolbar of a widget and FALSE otherwise.
     * 
     * Global actions are defined in the core config option
     * WIDGET.DATATOOLBAR.GLOBAL_ACTIONS and can be overridden on installation
     * and user level. These actions will be present in all DataToolbars, where
     * this property is TRUE.
     * 
     * @uxon-property include_global_actions
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     * @return DataToolbar
     */
    public function setIncludeGlobalActions(bool $true_or_false) : DataToolbar
    {
        $this->include_global_actions = $true_or_false;
        $this->included_buttons_initialized = false;
        return $this;
    }
    
    /**
     * Instantiates the auto-included button groups for global actions and search-related actions.
     * 
     * It is important to generate these button groups together as they must always be in the same
     * order and with the same ids - regardless of why and where their initialization was triggered.
     * 
     * @return void
     */
    protected function initAutoIncludedButtonGroups()
    {
        if ($this->getIncludeGlobalActions()) {
            $this->global_action_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->global_action_button_group->setVisibility(WidgetVisibilityDataType::OPTIONAL);
            
            foreach ($this->getWorkbench()->getConfig()->getOption('WIDGET.DATATOOLBAR.GLOBAL_ACTIONS') as $uxon){
                /* @var $btn \exface\Core\Widgets\Button */
                $btn = $this->global_action_button_group->createButton();
                $btn->setAction($uxon);
                $btn->setVisibility(WidgetVisibilityDataType::OPTIONAL);
                $this->global_action_button_group->addButton($btn);
            }
            $this->getWorkbench()->eventManager()->dispatch(new OnGlobalActionsAddedEvent($this->global_action_button_group));
        }
        
        if ($this->getIncludeSearchActions()) {
            $this->search_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $search_button = $this->search_button_group->createButton();
            $search_button
                ->setActionAlias('exface.Core.RefreshWidget')
                ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.READDATA.SEARCH'))
                ->setIcon(Icons::SEARCH)
                ->setAlign(EXF_ALIGN_OPPOSITE);
            $this->search_button_group->addButton($search_button);
            
            $reset_button = $this->search_button_group->createButton();
            $reset_button
                ->setActionAlias('exface.Core.ResetWidget')
                ->setIcon(Icons::UNDO)
                ->setAlign(EXF_ALIGN_OPPOSITE);
            $this->search_button_group->addButton($reset_button);

            if (! ($this->getDataWidget() instanceof iCanEditData) || $this->getDataWidget()->isEditable() === false) {
                $search_button->getAction()->getConfirmations()->disableConfirmationsForUnsavedChanges(true);
                $reset_button->getAction()->getConfirmations()->disableConfirmationsForUnsavedChanges(true);
            }
        }
        $this->included_buttons_initialized = true;
        return;
    }
    
    
    /**
     * Returns a button group with buttons for global actions.
     * 
     * The button group has visibility "optional" by default!
     * 
     * @return ButtonGroup|NULL
     */
    public function getButtonGroupForGlobalActions() : ?ButtonGroup
    {
        if ($this->included_buttons_initialized === false){
            $this->initAutoIncludedButtonGroups();
        }
        
        return $this->global_action_button_group;
    }
    
    /**
     * Returns a buttong group with buttons for search actions
     * 
     * The button group has opposite alignment and normal visibility by default!
     * 
     * @return ButtonGroup|NULL
     */
    public function getButtonGroupForSearchActions() : ?ButtonGroup
    {
        if ($this->included_buttons_initialized === false){
            $this->initAutoIncludedButtonGroups();
        }
        
        return $this->search_button_group;
    }
    
    /**
     * The DataToolbar creates additional button groups automatically unless the
     * group is disabled via setInclude...Action(false).
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if ($this->getIncludeNoExtraActions()){
            return parent::getWidgets($filter_callback);
        }
        
        // Add automatic button groups - but only if their parent is still this
        // toolbar. If they were moved, the parent changed, and we don't want
        // to see them here anymore.
        // Adding these groups must be done every time, because they must always
        // be at the end
        $groups = parent::getWidgets();
        if ($this->getIncludeGlobalActions() && $this->getButtonGroupForGlobalActions()->getParent() === $this){
            $globalActions = $this->getButtonGroupForGlobalActions();
            foreach ($groups as $overrides) {
                $globalActions = $this->removeButtonsWithOverriddenActions($globalActions, $overrides);
            }
            $groups[] = $globalActions;
        }
        if ($this->getIncludeSearchActions() && $this->getButtonGroupForSearchActions()->getParent() === $this){
            $groups[] = $this->getButtonGroupForSearchActions();
        }
        if (! is_null($filter_callback)){
            return array_filter($groups, $filter_callback);
        }
        
        return $groups;
    }

    /**
     * Removes all buttons from the specified group, whose action match the action of any button in
     * the overrides group - where "match" means either same action or one extending the same prototype class.
     * 
     * @param ButtonGroup $buttonGroup
     * @param ButtonGroup $overrides
     * @return ButtonGroup
     */
    protected function removeButtonsWithOverriddenActions(ButtonGroup $buttonGroup, ButtonGroup $overrides) : ButtonGroup
    {
        foreach ($overrides->getButtons() as $userButton) {
            $userAction = $userButton->getAction();
            if ($userAction === null) {
                continue;
            }
            
            foreach ($buttonGroup->getButtons() as $globalButton) {
                $globalAction = $globalButton->getAction();
                if ($globalAction === null) {
                    continue;
                }
                // Remove the button from the global actions toolbar if there is another button (user-made)
                // with the same caption and the same action. Just checking for the same action did not work
                // because generic actions like ShowDialog would get hidden too easily.
                if (($userAction instanceof $globalAction) && $userButton->getCaption() === $globalButton->getCaption()) {
                    $buttonGroup->removeButton($globalButton);
                }
            }
        }
        
        return $buttonGroup;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::addButtonGroup()
     */
    public function addButtonGroup(ButtonGroup $button_group, $index = null)
    {
        if ($this->getIncludeGlobalActions()) {
            $global_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForGlobalActions());
        }
        if ($this->getIncludeSearchActions()) {
            $search_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForSearchActions());
        }
        
        return parent::addButtonGroup($button_group, ($search_actions_index !== false || $global_actions_index !== false ? min($search_actions_index, $global_actions_index) : null));
    }
    
    /**
     * Returns TRUE if the search button will added to this toolbar and FALSE otherwise.
     *
     * @return bool
     */
    public function getIncludeSearchActions() : bool
    {
        if ($this->include_search_actions === null && $this->isMainToolbar()){
            return true;
        }
        return $this->include_search_actions ?? false;
    }
    
    /**
     * Set to FALSE to remove the search/reset buttons from this toolbar.
     *
     * @uxon-property include_search_actions
     * @uxon-type bool
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataToolbar
     */
    public function setIncludeSearchActions(bool $true_or_false) : DataToolbar
    {
        $this->include_search_actions = $true_or_false;
        $this->included_buttons_initialized = false;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }
    
    /**
     * Returns TRUE if this is the main toolbar of the data widget and FALSE otherwise.
     *
     * @return boolean
     */
    public function isMainToolbar() : bool
    {
        return $this->getInputWidget()->getToolbarMain() === $this ? true : false;
    }
}