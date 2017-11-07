<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;

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
    
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    public function getLazyLoadingGroupId()
    {
        return $this->lazy_loading_group_id;
    }

    public function getLazyLoadingAction()
    {
        return $this->lazyLoadingAction;
    }

    public function setLazyLoading($value)
    {
        $this->lazy_loading = BooleanDataType::cast($value);
        return $this;
    }

    public function setLazyLoadingAction($value)
    {
        $this->lazyLoadingAction = $value;
        return $this;
    }

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
     * 
     * @param UxonObject|TreeLevel[] $widget_or_uxon_array
     * @return \exface\Core\Widgets\Tree
     */
    public function setLevels($widget_or_uxon_array)
    {
        return $this->setWidgets($widget_or_uxon_array);
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