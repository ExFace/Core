<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Tabs is a special container widget, that holds one or more Tab widgets allowing the
 * typical tabbed navigation between them.
 * Tabs will typically show the contents of
 * the active tab and a navbar to enable the user to change tabs. The position of that
 * navbar can be determined by the tab_position attribute. Most typical position is "top".
 *
 * @author Andrej Kabachnik
 *        
 */
class Tabs extends Container implements iFillEntireContainer
{
    const TAB_POSITION_TOP = 'top';
    
    const TAB_POSITION_BOTTOM = 'bottom';
    
    const TAB_POSITION_LEFT = 'left';
    
    const TAB_POSITION_RIGHT = 'right';
    
    private $tab_position = null;
    
    private $tabs_with_icons_only = false;

    private $active_tab = 0;
    
    /**
     * Returns the tab under the given index (starting with 0 from the left/top)
     * 
     * @param integer $index
     * @return \exface\Core\Widgets\Tab|null
     */
    public function getTab($index)
    {
        return $this->getTabs()[$index];
    }

    /**
     *
     * @return Tab[]
     */
    public function getTabs()
    {
        return $this->getWidgets();
    }
    
    /**
     * Adds an array of widgets as tabs.
     * 
     * @uxon-property tabs
     * @uxon-type \exface\Core\Widgets\Tab[]
     * 
     * @param array|Tab|Container $widget_or_uxon_array
     * @return Tabs
     */
    public function setTabs(array $widget_or_uxon_array)
    {
        return $this->setWidgets($widget_or_uxon_array);
    }
    
    /**
     * Returns TRUE if there is at least one tab and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasTabs()
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
     * @return string
     */
    public function getTabPosition()
    {
        if (is_null($this->tab_position)){
            $this->tab_position = static::TAB_POSITION_TOP;
        }
        return $this->tab_position;
    }
    
    /**
     * 
     * @param unknown $value
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\Tabs
     */
    public function setTabPosition($value)
    {
        if (! defined('static::TAB_POSITION_' . mb_strtoupper($value))) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid tab_position value "' . $value . '": use "top", "bottom", "left" or "right"!');
        }
        $this->tab_position = constant('static::TAB_POSITION_' . mb_strtoupper($value));
        return $this;
    }

    /**
     *
     * @return number
     */
    public function getActiveTab()
    {
        return $this->active_tab;
    }

    public function setActiveTab($value)
    {
        $this->active_tab = $value;
        return $this;
    }

    /**
     * Adding widgets to Tabs will automatically produce Tab widgets for each added widget, unless it already is a tab.
     * This
     * way, a short an understandable notation of tabs is possible: simply add any type of widget to the tabs array and
     * see them be displayed in tabs.
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets(array $widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof \stdClass || $w instanceof AbstractWidget) {
                if ($w instanceof \stdClass && ! isset($w->widget_type)) {
                    $w->widget_type = 'Tab';
                }
                // If we have a UXON or instantiated widget object, use the widget directly
                $page = $this->getPage();
                $widget = WidgetFactory::createFromAnything($page, $w, $this);
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $w;
            }
            
            // If the widget is not a SplitPanel itslef, wrap it in a SplitPanel. Otherwise add it directly to the result.
            if (! ($widget instanceof Tab)) {
                $widgets[] = $this->createTab($widget);
            } else {
                $widgets[] = $widget;
            }
        }
        
        // Now the resulting array consists of widgets and unknown items. Send it to the parent class. Widgets will get
        // added directly and the unknown types may get some special treatment or just lead to errors. We don't handle
        // them here in order to ensure centralised processing in the container widget.
        return parent::setWidgets($widgets);
    }

    /**
     * Creates a tab and adds
     *
     * @param AbstractWidget $contents            
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function createTab(AbstractWidget $contents = null)
    {
        // Create an empty tab
        $widget = $this->getPage()->createWidget('Tab', $this);
        
        // If any contained widget is specified, add it to the tab an inherit some of it's attributes
        if ($contents) {
            $widget->addWidget($contents);
            $widget->setMetaObjectId($contents->getMetaObjectId());
            $widget->setCaption($contents->getCaption());
        }
        
        return $widget;
    }

    /**
     * Adds the given widget as a new tab.
     * The position (sequential number) of the tab can
     * be specified optionally. If the given widget is not a tab itself, it will be wrapped
     * in a Tab widget.
     *
     * @see add_widget()
     *
     * @param AbstractWidget $widget            
     * @param int $position            
     * @return Tabs
     */
    public function addTab(AbstractWidget $widget, $position = null)
    {
        if ($widget instanceof Tab) {
            $tab = $widget;
        } elseif ($widget->isExactly('Panel')) {
            $tab = $this->createTab();
            $tab->setCaption($widget->getCaption());
            foreach ($widget->getWidgets() as $child) {
                $tab->addWidget($child);
            }
        } else {
            $tab = $this->createTab($widget);
        }
        return $this->addWidget($tab, $position);
    }

    /**
     * Returns the number of currently contained tabs
     *
     * @return number
     */
    public function countTabs()
    {
        return parent::countWidgets();
    }

    /**
     *
     * {@inheritdoc}
     *
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
    protected function getDefaultTab()
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
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return $this->getDefaultTab();
    }
    
    /**
     * 
     * @return boolean
     */
    public function getHideTabsCaptions()
    {
        return $this->tabs_with_icons_only;
    }
    
    /**
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\Tabs
     */
    public function setHideTabsCaptions($true_or_false)
    {
        $this->tabs_with_icons_only = $true_or_false;
        return $this;
    }
 
}
?>