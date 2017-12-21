<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class Tree extends Container implements iSupportLazyLoading
{
    private $lazyLoadingAction = 'exface.Core.ReadData';
    
    private $lazy_loading = true;

    /** @var string */
    private $lazy_loading_group_id = null;
    
    private $lazy_loading_disable_filters_over_attribute_aliases = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazyLoadingAction;
    }

    /**
     * Set to TRUE to make each level load asynchronously or use FALSE otherwise.
     * 
     * @uxon-property lazy_loading
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * Alias of the action to use for lazy loading levels: exface.Core.ReadData by default.
     * 
     * @uxon-property lazy_loading_action
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazyLoadingAction = $value;
        return $this;
    }

    /**
     * Makes this tree belong to the lazy loading group with the given id.
     * 
     * If not set, the tree will not belong to any group and will load data on it's own.
     * 
     * @uxon-property lazy_loading_group_id
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
    
    /**
     * 
     * @return TreeLevel[]
     */
    public function getLevels()
    {
        return $this->getWidgets();
    }
    
    /**
     * Defines the levels of this tree: array of TreeLevel widgets or derivatives.
     * 
     * @uxon-property levels
     * @uxon-type \exface\Core\Widgets\TreeLevel[]
     * 
     * @param UxonObject|TreeLevel[] $widget_or_uxon_array
     * @return \exface\Core\Widgets\Tree
     */
    public function setLevels($widget_array_or_uxon)
    {
        return $this->setWidgets($widget_array_or_uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_array_or_uxon)
    {
        foreach ($widget_array_or_uxon as $level) {
            if ($level instanceof UxonObject) {
                if ($level->hasProperty('type')) {
                    $levelType = 'TreeLevel' . ucfirst(strtolower($level->getProperty('type')));
                } else {
                    $levelType = 'TreeLevel';
                }
                $this->addLevel(WidgetFactory::createFromUxon($this->getPage(), $level, $this, $levelType));
            } else {
                $this->addLevel($level);
            }
        }
        return $this;
    }
    
    /**
     * 
     * @param TreeLevel $widget
     * @return Tree
     */
    public function addLevel(TreeLevel $widget) 
    {
        return $this->addWidget($widget);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = null)
    {
        if (! ($widget instanceof TreeLevel)) {
            throw new WidgetConfigurationError($this, 'Cannot add widget ' . $widget->getWidgetType() . ' to ' . $this->getWidgetType() . ': can only accept TreeLevel widgets or derivatives!', '6YD44MC');
        }
        return parent::addWidget($widget, $position);
    }
    
    /**
     * 
     * @param TreeLevel $widget
     * @return number|boolean
     */
    public function getLevelIndex(TreeLevel $widget)
    {
        return $this->getWidgetIndex($widget);
    }
    
    /**
     * @return string[]
     */
    public function getLazyLoadingDisableFiltersOverAttributeAliases()
    {
        return $this->lazy_loading_disable_filters_over_attribute_aliases;
    }

    /**
     * @param UxonObject|string[] 
     */
    public function setLazyLoadingDisableFiltersOverAttributeAliases($alias_array_or_uxon)
    {
        $this->lazy_loading_disable_filters_over_attribute_aliases = $alias_array_or_uxon;
        return $this;
    }

}
?>