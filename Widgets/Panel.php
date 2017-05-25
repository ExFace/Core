<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\Interfaces\Widgets\iAmCollapsible;

/**
 * A panel is a visible container with a configurable layout, support for lazy-loading of content.
 *
 * The panel is the base widget for many containers, that show multiple smaller widgets in a column-based
 * (newspaper-like) layout.
 *
 * @see Form - Panel with buttons
 * @see InputGroup - Small panel to easily group input widgets
 * @see SplitPanel - Special resizable panel to be used in SplitVertical and SplitHorizontal widgets
 * @see Tab - Special panel to be used in the Tabs widget
 *     
 * @author Andrej Kabachnik
 *        
 */
class Panel extends Container implements iLayoutWidgets, iSupportLazyLoading, iHaveIcon, iAmCollapsible, iFillEntireContainer
{

    private $lazy_loading = false;

    // A panel will not be loaded via AJAX by default
    private $lazy_loading_action = 'exface.Core.ShowWidget';

    private $collapsible = false;

    private $icon_name = null;

    private $column_number = null;

    private $column_stack_on_smartphones = null;

    private $column_stack_on_tablets = null;

    private $lazy_loading_group_id = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::getCollapsible()
     */
    public function getCollapsible()
    {
        return $this->collapsible;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsible()
     */
    public function setCollapsible($value)
    {
        $this->collapsible = $value;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIconName()
     */
    public function getIconName()
    {
        return $this->icon_name;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIconName()
     */
    public function setIconName($value)
    {
        $this->icon_name = $value;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = $value;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    public function getColumnNumber()
    {
        return $this->column_number;
    }

    public function setColumnNumber($value)
    {
        $this->column_number = $value;
        return $this;
    }

    /**
     * Returns TRUE if the columns should be stacked on small screens and FALSE otherwise.
     * Returns NULL if the creator of the widget
     * had made no preference and thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getColumnStackOnSmartphones()
    {
        return $this->column_stack_on_smartphones;
    }

    /**
     * Determines wether columns should be stacked on smaller screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value            
     */
    public function setColumnStackOnSmartphones($value)
    {
        $this->column_stack_on_smartphones = $value;
        return $this;
    }

    /**
     * Returns TRUE if the columns should be stacked on midsize screens and FALSE otherwise.
     * Returns NULL if the creator of the widget
     * had made no preference and thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getColumnStackOnTablets()
    {
        return $this->column_stack_on_tablets;
    }

    /**
     * Determines wether columns should be stacked on midsize screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value            
     */
    public function setColumnStackOnTablets($value)
    {
        $this->column_stack_on_tablets = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc} If the parent widget of a panel has other children (siblings of the panel), they should be moved to the panel itself, once it is
     *               added to it's paren.
     *              
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     * @return Panel
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return $this;
    }

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
}
?>