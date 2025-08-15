<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainTypedWidgets;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * Tabbed container with one or more tabs.
 * 
 * The `Tabs` widget constis of nav-strip listing the names of all available tabs (usually at 
 * the top) and a large display area showing the current tab. 
 * 
 * Each tab is a `Tab` widget, that can contain any number of widgets. Every `Tab` should have 
 * a `caption` and may have an `icon` and a `hint`. These attributes along with `disabled`, `hidden`
 * etc. control the appearance of the nav-strip. Depending on the facade, the `visibility` property
 * can be used to emhasize certain tabs..
 * 
 * The first tab is visible (active) by default. You can also activate any other tab initially by
 * setting the `active_tab` property to the sequential number of the slide (starting with 0!).
 * 
 * The nav-strip can be positioned explicitly using the `nav_position` property to `top`, `bottom`, 
 * `left` or `right`. It's appearance can be customized by giving slides captions and/or icons. 
 * To force an icon-only nav-strip, set `hide_nav_captions` to `true`.
 *
 * @author Andrej Kabachnik
 *        
 */
class Tabs extends Container implements iFillEntireContainer, iContainTypedWidgets
{
    const NAV_POSITION_TOP = 'top';
    
    const NAV_POSITION_BOTTOM = 'bottom';
    
    const NAV_POSITION_LEFT = 'left';
    
    const NAV_POSITION_RIGHT = 'right';
    
    private $nav_position = null;
    
    private $tabs_with_icons_only = null;

    private $active_tab = 0;
    
    /**
     * Returns the tab under the given index (starting with 0 from the left/top)
     * 
     * @param integer $index
     * @return \exface\Core\Widgets\Tab|null
     */
    public function getTab($index) : ?Tab
    {
        return $this->getTabs()[$index];
    }

    /**
     *
     * @return Tab[]
     */
    public function getTabs() : array
    {
        return $this->getWidgets();
    }
    
    /**
     * Defines an array of widgets as tabs.
     * 
     * Adding widgets to Tabs will automatically produce Tab widgets for each added widget, 
     * unless it already is a tab or another widget based on it. This way, a short and understandable 
     * notation of tabs is possible: simply add any type of widget to the tabs array and see 
     * them be displayed in tabs.
     * 
     * @uxon-property tabs
     * @uxon-type \exface\Core\Widgets\Tab[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"caption": "", "widgets": [{"": ""}]}]
     * 
     * @param UxonObject|Tab[] $widget_or_uxon_array
     * @return Tabs
     */
    public function setTabs($widget_or_uxon_array) : Tabs
    {
        return $this->setWidgets($widget_or_uxon_array);
    }
    
    /**
     * Returns TRUE if there is at least one tab and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasTabs() : bool
    {
        return $this->hasWidgets();
    }
    
    /**
     * Returns TRUE if at least one tab has at least one widget and FALSE otherwise.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::isEmpty()
     */
    public function isEmpty()
    {
        foreach ($this->getTabs() as $tab){
            if (! $tab->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * @param string $default
     * @return string
     */
    public function getNavPosition(string $default = self::NAV_POSITION_TOP) : string
    {
        return $this->nav_position ?? $default;
    }
    
    /**
     * Explicitly sets the position of the navigation-strip (by default, the facade decides, where to place it).
     * 
     * @uxon-property nav_position
     * @uxon-type [top,bottom,left,right]
     * 
     * @param string $value
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\Tabs
     */
    public function setNavPosition(string $value) : Tabs
    {
        if (! defined('static::NAV_POSITION_' . mb_strtoupper($value))) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid nav_position value "' . $value . '": use "top", "bottom", "left" or "right"!');
        }
        $this->nav_position = constant('static::NAV_POSITION_' . mb_strtoupper($value));
        return $this;
    }

    /**
     * Returns the index of the initally active tab (starting with 0).
     * 
     * @return int
     */
    public function getActiveTabIndex() : int
    {
        return $this->active_tab;
    }

    /**
     * Makes the tab with the given index active instead of the first one.
     * 
     * @uxon-property active_tab
     * @uxon-type integer
     * @uxon-default 0
     * 
     * @param int $value
     * @return Tabs
     */
    public function setActiveTab(int $value) : Tabs
    {
        $this->active_tab = $value;
        return $this;
    }

    /**
     * Defines widets (tabs) to be displayed - same as tabs property.
     * 
     * Adding widgets to Tabs will automatically produce Tab widgets for each added widget, 
     * unless it already is a tab or another widget based on it. This way, a short an understandable 
     * notation of tabs is possible: simply add any type of widget to the tabs array and see 
     * them be displayed in tabs.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Tab[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"caption": "", "widgets": [{"widget_type": ""}]}]
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                // If we have a UXON or instantiated widget object, use the widget directly
                $page = $this->getPage();
                $widgetType = WidgetFactory::getWidgetType($this->getPage(), $w, $this, $this->getTabWidgetType());
                
                if($this->isWidgetTypeAllowed($widgetType)) {
                    $widget = WidgetFactory::createFromUxon($page, $w, $this, $this->getTabWidgetType());
                } else {
                    $widget = $this->wrapInTab($w);
                }
            } elseif ($w instanceof WidgetInterface){
                $widget = $w;
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $w;
            }
            
            // If the widget is not a Tab itself, wrap it in a Tab. Otherwise, add it directly to the result.
            $widgets[] = $widget;
        }
        
        // Now the resulting array consists of widgets and unknown items. Send it to the parent class. Widgets will get
        // added directly and the unknown types may get some special treatment or just lead to errors. We don't handle
        // them here in order to ensure centralised processing in the container widget.
        return parent::setWidgets($widgets);
    }
    
    /**
     * Returns the widget type to use when creating new tabs.
     * 
     * @return string
     */
    protected function getTabWidgetType() : string
    {
        return 'Tab';
    }

    /**
     * Creates a tab (but does not add it automatically!!!)
     *
     * @return Tab
     */
    public function createTab() : Tab
    {
        // Create an empty tab
        return $this->getPage()->createWidget($this->getTabWidgetType(), $this);
    }
    
    public function wrapInTab(UxonObject $content) : Tab
    {
        $tab = $this->createTab();
        $content = WidgetFactory::createFromUxon($this->getPage(), $content, $tab, $this->getTabWidgetType());
        
        $tab->addWidget($content);
        $tab->setMetaObject($content->getMetaObject());
        $tab->setCaption($content->getCaption());
        
        return $tab;
    }

    /**
     * Adds the given widget as a new tab.
     * The position (sequential number) of the tab can
     * be specified optionally. If the given widget is not a tab itself, it will be wrapped
     * in a Tab widget.
     *
     * @see add_widget()
     *
     * @param WidgetInterface $widget            
     * @param int $position            
     * @return Tabs
     */
    public function addTab(WidgetInterface $widget, int $position = null) : Tabs
    {
        if ($this->isWidgetAllowed($widget)) {
            $tab = $widget;
        } elseif ($widget->isExactly('Panel')) {
            $tab = $this->createTab();
            $tab->setCaption($widget->getCaption());
            foreach ($widget->getWidgets() as $child) {
                $tab->addWidget($child);
            }
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot add Widget "' . $widget->getId() . '". Widget type "Tab" expected, "' . $widget->getWidgetType() . '" given!');
        }
        return $this->addWidget($tab, $position);
    }

    /**
     * Returns the number of currently contained tabs
     * @return int
     */
    public function countTabs() : int
    {
        return parent::countWidgets();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Container::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = null)
    {
        if ($widget instanceof Tab) {
            return parent::addWidget($widget, $position);
        } else {
            return $this->getDefaultTab()->addWidget($widget);
        }
        return $this;
    }

    /**
     *
     * @return Tab
     */
    protected function getDefaultTab() : Tab
    {
        if ($this->countTabs() == 0) {
            $tab = $this->createTab();
            // FIXME translate "General"
            $tab->setCaption('General');
            $this->addWidget($tab);
        }
        return $this->getTabs()[0];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        $defaultTab = $this->getDefaultTab();
        if ($filler = $defaultTab->getFillerWidget()) {
            if ($alternative = $filler->getAlternativeContainerForOrphanedSiblings()) {
                return $alternative;
            }
        }
        return $defaultTab;
    }
    
    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getHideNavCaptions(bool $default = false) : bool
    {
        return $this->tabs_with_icons_only ?? $default;
    }
    
    /**
     * Set to TRUE to make the tab ribbon show icons only (no captions).
     * 
     * @uxon-property hide_nav_captions
     * @uxon-type boolean
     * @uxon-default false 
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\Tabs
     */
    public function setHideNavCaptions(bool $true_or_false) : Tabs
    {
        $this->tabs_with_icons_only = $true_or_false;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        // See if tabs and widgets arrays both exist. If so, remove tabs (they came from the original UXON)
        // and keep widgets (they were generated by the parent (Container).
        if ($uxon->hasProperty('tabs') && $uxon->hasProperty('widgets')) {
            $uxon->removeProperty('tabs');
        }
        return $uxon;
    }

    /**
     * {@inheritDoc}
     * @see iContainTypedWidgets::isWidgetAllowed()
     */
    public function isWidgetAllowed(WidgetInterface $widget) : bool
    {
        return $widget instanceof Tab;
    }

    /**
     * {@inheritDoc}
     * @see iContainTypedWidgets::isWidgetTypeAllowed()
     */
    public function isWidgetTypeAllowed(string $typeOrClassOrInterface) : bool
    {
        if (mb_strpos($typeOrClassOrInterface, '\\') !== false) {
            $class = $typeOrClassOrInterface;
        } else {
            $class = WidgetFactory::getWidgetClassFromType($typeOrClassOrInterface);
        }
        return is_a($class, Tab::class, true);
    }
}