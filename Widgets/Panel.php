<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Traits\WidgetLayoutTrait;
use exface\Core\Widgets\Traits\iAmCollapsibleTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * A panel is a visible container with a configurable layout (number of columns,
 * etc.) and optional support for lazy-loading of content.
 *
 * The panel is the base widget for many containers, that show multiple smaller
 * widgets in a column-based (newspaper-like) layout.
 *
 * @see Form - Panel with buttons
 * @see Dashboard - Panel with a common customizer (common filters, buttons, etc.)
 * @see WidgetGroup - Small panel to easily group input widgets
 * @see SplitPanel - Special resizable panel to be used in SplitVertical and SplitHorizontal widgets
 * @see Tab - Special panel to be used in the Tabs widget
 *     
 * @author Andrej Kabachnik
 *        
 */
class Panel extends WidgetGrid implements iSupportLazyLoading, iHaveIcon, iAmCollapsible, iFillEntireContainer
{
    
    use WidgetLayoutTrait;
    use iAmCollapsibleTrait;
    use iHaveIconTrait;
    
    // A panel will not be loaded via AJAX by default
    private $lazy_loading = false;

    private $lazy_loading_action = 'exface.Core.ShowWidget';

    private $lazy_loading_group_id = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = BooleanDataType::cast($value);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc} If the parent widget of a panel has other children (siblings of the panel),
     *               they should be moved to the panel itself, once it is added to it's paren.
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
     * {@inheritdoc}
     *
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
}
?>