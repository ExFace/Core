<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

trait iSupportLazyLoadingTrait {
    
    private $lazy_loading = true;
    
    private $lazy_loading_action_alias = null;
    
    private $lazy_loading_action = null;
    
    private $lazy_loading_group_id = null;
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }
    
    /**
     * Makes data values get loaded asynchronously in background if the template supports it (i.e.
     * via AJAX).
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingActionAlias()
     */
    public function getLazyLoadingActionAlias()
    {
        if (is_null($this->lazy_loading_action_alias)) {
            throw new WidgetPropertyNotSetError($this, 'Lazy loading action not set for widget ' . $this->getWidgetType() . '!');
        }
        return $this->lazy_loading_action_alias;
    }
    
    /**
     * Sets a custom action for lazy data loading.
     *
     * By default, it is the ReadData action, but it can be substituted by any compatible action. Compatible
     * means in this case, that it should fill a given data sheet with data and output the data in a format
     * compatible with the template (e.g. via AbstractAjaxTemplate::encodeData()).
     *
     * @uxon-property lazy_loading_action_alias
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingActionAlias()
     */
    public function setLazyLoadingActionAlias($value)
    {
        $this->lazy_loading_action_alias = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingGroupId()
     */
    public function getLazyLoadingGroupId()
    {
        return $this->lazy_loading_group_id;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface
     */
    public function getLazyLoadingAction()
    {
        if (is_null($this->lazy_loading_action)) {
            $this->lazy_loading_action = ActionFactory::createFromString($this->getWorkbench(), $this->getLazyLoadingActionAlias(), $this);
        } 
        return $this->lazy_loading_action;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::getAction()
     */
    public function getAction()
    {
        return $this->getLazyLoadingAction();
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::hasAction()
     */
    public function hasAction() : bool
    {
        return $this->getAction() ? true : false;
    }
}