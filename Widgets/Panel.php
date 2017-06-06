<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\DataTypes\BooleanDataType;

/**
 * A panel is a visible container with a configurable layout (number of columns, 
 * etc.) and optional support for lazy-loading of content.
 *
 * The panel is the base widget for many containers, that show multiple smaller 
 * widgets in a column-based (newspaper-like) layout.
 *
 * @see Form - Panel with buttons
 * @see Dashboard - Panel with a common customizer (common filters, buttons, etc.)
 * @see InputGroup - Small panel to easily group input widgets
 * @see SplitPanel - Special resizable panel to be used in SplitVertical and SplitHorizontal widgets
 * @see Tab - Special panel to be used in the Tabs widget
 *     
 * @author Andrej Kabachnik
 *        
 */
class Panel extends Container implements iLayoutWidgets, iSupportLazyLoading, iHaveIcon, iAmCollapsible, iFillEntireContainer
{

    // A panel will not be loaded via AJAX by default
    private $lazy_loading = false;

    private $lazy_loading_action = 'exface.Core.ShowWidget';

    private $collapsible = false;

    private $icon_name = null;

    private $number_of_columns = null;

    private $column_stack_on_smartphones = null;

    private $column_stack_on_tablets = null;

    private $lazy_loading_group_id = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::isCollapsible()
     */
    public function isCollapsible()
    {
        return $this->collapsible;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsible()
     */
    public function setCollapsible($value)
    {
        $this->collapsible = BooleanDataType::parse($value);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIconName()
     */
    public function getIconName()
    {
        return $this->icon_name;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIconName()
     */
    public function setIconName($value)
    {
        $this->icon_name = $value;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = BooleanDataType::parse($value);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getNumberOfColumns()
     */
    public function getNumberOfColumns()
    {
        return $this->number_of_columns;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setNumberOfColumns()
     */
    public function setNumberOfColumns($value)
    {
        $this->number_of_columns = intval($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsSmartphones()
     */
    public function getStackColumnsOnTabletsSmartphones()
    {
        return $this->column_stack_on_smartphones;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsSmartphones()
     */
    public function setStackColumnsOnTabletsSmartphones($value)
    {
        $this->column_stack_on_smartphones = BooleanDataType::parse($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsTablets()
     */
    public function getStackColumnsOnTabletsTablets()
    {
        return $this->column_stack_on_tablets;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsTablets()
     */
    public function setStackColumnsOnTabletsTablets($value)
    {
        $this->column_stack_on_tablets = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *  
     * {@inheritdoc} 
     * 
     * If the parent widget of a panel has other children (siblings of the panel), 
     * they should be moved to the panel itself, once it is added to it's paren.
     *              
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     * @return Panel
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
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
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
}
?>