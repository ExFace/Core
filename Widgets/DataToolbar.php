<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * A special toolbar for data widgets with extra features like automatically
 * included button groups for global actions, search actions, etc.
 * 
 * @see Toolbar
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
    
    /** @var boolean */
    private $include_object_basket_actions = null;
    
    /** @var ButtonGroup */
    private $global_action_button_group = null;
    
    /** @var ButtonGroup */
    private $search_button_group = null;
    
    /** @var ButtonGroup */
    private $object_basket_button_group = null;
    
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
    public function getDataWidget()
    {
        return $this->getInputWidget();
    }
    
    /**
     * Returns TRUE if no actions should be included automatically and FALSE otherwise.
     * 
     * @return boolean $disable_autoinclude_actions
     */
    public function getIncludeNoExtraActions()
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
     * 
     * @param boolean $disable_autoinclude_actions
     * @return DataToolbar
     */
    public function setIncludeNoExtraActions($true_or_false)
    {
        $this->disable_autoinclude_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
     * Returns TRUE if actions defined in the core config option 
     * WIDGET.DATATOOLBAR.GLOBAL_ACTIONS are to be included in this toolbar and 
     * FALSE otherwise.
     * 
     * Defaults to TRUE for the main toolbar of a widgets and FALSE otherwise.
     * 
     * @return boolean
     */
    public function getIncludeGlobalActions()
    {
        if (is_null($this->include_global_actions) && $this->isMainToolbar()){
            return true;
        }
        return $this->include_global_actions;
    }
    
    /**
     * Set to FALSE to disable global actions for this toolbar.
     * 
     * Defaults to TRUE for the main toolbar of a widgets and FALSE otherwise.
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
    public function setIncludeGlobalActions($true_or_false)
    {
        $this->include_global_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
     * Returns TRUE if actions available in the object basket for the meta
     * object of the toolbar are to be included automatically.
     * 
     * Defaults to FALSE.
     * 
     * @return boolean
     */
    public function getIncludeObjectBasketActions()
    {
        return $this->include_object_basket_actions;
    }
    
    /**
     * Set to TRUE to automatically include a button group with actions available in the object basket.
     * 
     * Defaults to FALSE.
     * 
     * @uxon-property include_object_basket_actions
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @return DataToolbar
     */
    public function setIncludeObjectBasketActions($true_or_false)
    {
        $this->include_object_basket_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
     * Returns a button group with buttons for global actions.
     * 
     * The button group has visibility "optional" by default!
     * 
     * @return ButtonGroup
     */
    public function getButtonGroupForGlobalActions(){
        if (is_null($this->global_action_button_group)){
            $this->global_action_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->global_action_button_group->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
            
            foreach ($this->getWorkbench()->getConfig()->getOption('WIDGET.DATATOOLBAR.GLOBAL_ACTIONS') as $uxon){
                /* @var $btn \exface\Core\Widgets\Button */
                $btn = $this->global_action_button_group->createButton();
                $btn->setAction($uxon);
                $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                $this->global_action_button_group->addButton($btn);
            }
        }
        
        return $this->global_action_button_group;
    }
    
    /**
     * Returns a button group with buttons for object basket actions.
     * 
     * The button group has visibility "optional" by default!
     * 
     * @return \exface\Core\Widgets\ButtonGroup
     */
    public function getButtonGroupForObjectBasketActions(){
        if (is_null($this->object_basket_button_group)){
            $this->object_basket_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->object_basket_button_group->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
            
            foreach ($this->getMetaObject()->getActions()->getUsedInObjectBasket() as $action){
                /* @var $btn \exface\Core\Widgets\Button */
                $btn = $this->object_basket_button_group->createButton();
                $btn->setAction($action);
                $this->object_basket_button_group->addButton($btn);
            }
        }
        
        return $this->object_basket_button_group;
    }
    
    /**
     * Returns a buttong group with buttons for search actions
     * 
     * The button group has opposite alignment and normal visibility by default!
     * 
     * @return \exface\Core\Widgets\ButtonGroup
     */
    public function getButtonGroupForSearchActions()
    {
        if (is_null($this->search_button_group)){
            $this->search_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            
            $search_button = $this->search_button_group->createButton();
            $search_button
            ->setActionAlias('exface.Core.RefreshWidget')
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.READDATA.SEARCH'))
            ->setIconName(Icons::SEARCH)
            ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
            ->setAlign(EXF_ALIGN_OPPOSITE);
            
            $this->search_button_group->addButton($search_button);
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
        // toolbar. If they were moved, the parent changes and we don't want
        // to see them here anymore.
        // Adding these groups must be done every time, because the must allways
        // be at the end
        $groups = parent::getWidgets();
        if ($this->getIncludeObjectBasketActions() && $this->getButtonGroupForObjectBasketActions()->getParent() === $this){
            $groups[] = $this->getButtonGroupForObjectBasketActions();
        }
        if ($this->getIncludeGlobalActions() && $this->getButtonGroupForGlobalActions()->getParent() === $this){
            $groups[] = $this->getButtonGroupForGlobalActions();
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
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::addButtonGroup()
     */
    public function addButtonGroup(ButtonGroup $button_group, $index = null)
    {
        if ($this->getIncludeObjectBasketActions()){
            $object_basket_index = parent::getButtonGroupIndex($this->getButtonGroupForObjectBasketActions());
        }
        if ($this->getIncludeGlobalActions()){
            $global_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForGlobalActions());
        }
        if ($this->getIncludeSearchActions()){
            $search_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForSearchActions());
        }
        
        return parent::addButtonGroup($button_group, ($search_actions_index !== false || $global_actions_index !== false || $object_basket_index !== false ? min($search_actions_index, $global_actions_index, $object_basket_index) : null));
    }
    
    /**
     * Returns TRUE if the search button will added to this toolbar and FALSE otherwise.
     *
     * @return boolean
     */
    public function getIncludeSearchActions()
    {
        if (is_null($this->include_search_actions) && $this->isMainToolbar()){
            return true;
        }
        return $this->include_search_actions;
    }
    
    /**
     * Set to FALSE to remove the search/reset buttons from this toolbar.
     *
     * @uxon-property include_search_actions
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataToolbar
     */
    public function setIncludeSearchActions($true_or_false)
    {
        $this->include_search_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }
    
    /**
     * Returns TRUE if this is the main toolbar of the data widget and FALSE otherwise.
     *
     * @return boolean
     */
    public function isMainToolbar()
    {
        return $this->getDataWidget()->getToolbarMain() === $this ? true : false;
    }
}
?>