<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\CommonLogic\Traits\WidgetLayoutTrait;
use exface\Core\Widgets\Traits\iAmCollapsibleTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;
use exface\Core\CommonLogic\UxonObject;

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
    use iSupportLazyLoadingTrait;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Traits\iSupportLazyLoadingTrait::getLazyLoadingActionUxonDefault()
     */
    protected function getLazyLoadingActionUxonDefault() : UxonObject
    {
        return new UxonObject([
            'alias' => 'exface.Core.ShowWidget'
        ]);
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
}
?>