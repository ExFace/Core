<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * A special button bar to be used in data widgets for actions.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTableToolbar extends DataToolbar
{    
    /** @var boolean */
    private $include_global_actions = null;
    
    /** @var boolean */
    private $include_search_actions = null;
    
    /** @var boolean */
    private $include_object_basket_actions = null;
    
    /** @var ButtonGroup */
    private $global_action_button_group = null;
    
    /** @var Button */
    private $search_button = null;
    
    /** @var ButtonGroup */
    private $search_button_group = null;
    
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
                $btn = $this->createButton();
                $btn->setAction($uxon);
                $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                $this->global_action_button_group->addButton($btn);
            }
        }
        
        return $this->global_action_button_group;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getButtonGroups(callable $filter_callback = null)
    {        
        // Add automatic button groups - but only if their parent is still this
        // toolbar. If they were moved, the parent changes and we don't want
        // to see them here anymore.
        // Adding these groups must be done every time, because the must allways
        // be at the end
        $groups = parent::getButtonGroups();
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
     * @see \exface\Core\Interfaces\Widgets\iContainButtonGroups::getButtonGroupIndex()
     */
    public function getButtonGroupIndex(ButtonGroup $button_group){
        return array_search($button_group, $this->getButtonGroups());
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
    
    public function getSearchButton()
    {
        if (is_null($this->search_button)){
            $this->search_button = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
            $this->search_button
                ->setActionAlias('exface.Core.RefreshWidget')
                ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.READDATA.SEARCH'))
                ->setIconName(Icons::SEARCH)
                ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
                ->setAlign(EXF_ALIGN_OPPOSITE);
        }
        return $this->search_button;
    }
    
    public function getButtonGroupForSearchActions()
    {
        if (is_null($this->search_button_group)){
            $this->search_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->search_button_group->addButton($this->getSearchButton());
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