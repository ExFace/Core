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
     * 
     * @return Data
     */
    public function getDataWidget()
    {
        return $this->getInputWidget();
    }
    
    /**
     *
     * @return boolean $disable_autoinclude_actions
     */
    public function getDoNotAutoincludeActions()
    {
        return $this->disable_autoinclude_actions;
    }
    
    /**
     *
     * @param boolean $disable_autoinclude_actions
     * @return DataToolbar
     */
    public function setDoNotAutoincludeActions($true_or_false)
    {
        $this->disable_autoinclude_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
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
     *
     * @return boolean
     */
    public function getIncludeObjectBasketActions()
    {
        return $this->include_object_basket_actions;
    }
    
    /**
     *
     * @param boolean $true_or_false
     * @return DataToolbar
     */
    public function setIncludeObjectBasketActions($true_or_false)
    {
        $this->include_object_basket_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    public function getPosition()
    {
        if (is_null($this->position)){
            $this->setPosition(static::POSITION_DEFAULT);
        }
        return $this->position;
    }
    
    /**
     *
     * @return Button[]
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
     * The DataToolbar creates additional button groups automatically unless the
     * group is disabled via setInclude...Action(false).
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if ($this->getDoNotAutoincludeActions()){
            return parent::getWidgets($filter_callback);
        }
        
        // Add automatic button groups - but only if their parent is still this
        // toolbar. If they were moved, the parent changes and we don't want
        // to see them here anymore.
        // Adding these groups must be done every time, because the must allways
        // be at the end
        $groups = parent::getWidgets();
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
        if ($this->getIncludeGlobalActions()){
            $global_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForGlobalActions());
        }
        if ($this->getIncludeSearchActions()){
            $search_actions_index = parent::getButtonGroupIndex($this->getButtonGroupForSearchActions());
        }
        
        return parent::addButtonGroup($button_group, ($search_actions_index !== false || $global_actions_index !== false ? min($search_actions_index, $global_actions_index) : null));
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
     * Set to FALSE to remove the search button from this toolbar.
     *
     * @uxon-property include_search_button
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