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

    private $tab_position = 'top';

    private $active_tab = 1;

    /**
     *
     * @return Tab[]
     */
    public function getTabs()
    {
        return $this->getWidgets();
    }

    public function setTabs(array $widget_or_uxon_array)
    {
        return $this->setWidgets($widget_or_uxon_array);
    }

    /**
     *
     * @return string
     */
    public function getTabPosition()
    {
        return $this->tab_position;
    }

    public function setTabPosition($value)
    {
        if ($value != 'top' && $value != 'bottom' && $value != 'left' && $value != 'right') {
            throw new WidgetPropertyInvalidValueError($this, 'Tab position accepts only the following values: top, left, right, bottom. "' . $value . '" given!', '6T911YV');
        } else {
            $this->tab_position = $value;
        }
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
            return parent::addWidget($widget);
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
}
?>