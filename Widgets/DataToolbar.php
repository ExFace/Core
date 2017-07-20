<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;

/**
 * A special button bar to be used in data widgets for actions.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataToolbar extends Toolbar
{    
    /** @var boolean */
    private $include_global_actions = true;
    
    /** @var boolean */
    private $include_search_button = true;
    
    /** @var boolean */
    private $include_object_basket_actions = false;
    
    /** @var ButtonGroup */
    private $global_action_button_group = null;
    
    /** @var Button */
    private $search_button = null;
    
    /** @var ButtonGroup */
    private $search_button_group = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setParent()
     */
    public function setParent(WidgetInterface $widget)
    {
        if (! $widget instanceof Data
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
     * @return boolean
     */
    public function getIncludeGlobalActions()
    {
        return $this->include_global_actions;
    }
    
    /**
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataRowMenu
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
     * @return \exface\Core\Widgets\DataRowMenu
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
    protected function getButtonGroupForGlobalActions(){
        if (is_null($this->global_action_button_group)){
            $this->global_action_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->global_action_button_group->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
            
            foreach ($this->getWorkbench()->getConfig()->getOption('GLOBAL.ACTIONS') as $uxon){
                /* @var $btn \exface\Core\Widgets\Button */
                $btn = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
                $btn->setAction($uxon);
                $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                $this->global_action_button_group->addButton($btn);
            }
        }
        
        return $this->global_action_button_group;
    }
    
    public function getButtonGroups()
    {
        $groups = parent::getButtonGroups();
        if ($this->getIncludeGlobalActions()){
            $groups[] = $this->getButtonGroupForGlobalActions();
        }
        if ($this->getIncludeSearchButton()){
            // FIXME config widget failure
            // $groups[] = $this->getButtonGroupForSearchButtons();
        }
        return $groups;
    }
    
    /**
     * Returns TRUE if the search button will added to this toolbar and FALSE otherwise.
     * 
     * @return boolean
     */
    public function getIncludeSearchButton()
    {
        return $this->include_search_button;
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
    public function setIncludeSearchButton($true_or_false)
    {
        $this->include_search_button = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    public function getSearchButton()
    {
        if (is_null($this->search_button)){
            $this->search_button = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
            $this->search_button
                ->setActionAlias('exface.Core.RefreshWidget')
                ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
                ->setAlign(EXF_ALIGN_OPPOSITE);
        }
        return $this->search_button;
    }
    
    protected function getButtonGroupForSearchButtons()
    {
        if (is_null($this->search_button_group)){
            $this->search_button_group = WidgetFactory::create($this->getPage(), 'ButtonGroup', $this);
            $this->search_button_group->addButton($this->getSearchButton());
        }
        
        return $this->search_button_group;
    }
    
    /*public function getButtonGroupMain(){
        $grp = parent::getButtonGroupMain();
        if (is_null($this->search_button)){
            //$grp->addButton($this->getSearchButton());
        }
        return $grp;
    }*/
}
?>