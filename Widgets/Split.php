<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * A Split consists of multiple panels aligned vertically or horizontally.
 * 
 * Splits allow groups of widgets to be explicitly positioned side-by-side or below-each-other 
 * instead of leaving the positioning to the tempalte. The borders between panels within a split can 
 * be dragged, thus resizing parts of the split.
 * 
 * Apart from a generic `Split` with a configurable orientation, there are specific widgets for each
 * type of orientation: `SplitVertical` (position panels below-each-other) and `SplitHorizontal` (position
 * panels side-by-side). They are easier to understand for most users.
 *
 * Splits use special panels: SplitPanels. However, you can use any widget in the `panels` or `widgets`
 * arrays - it will be automatically converted to a `SplitPanel`.
 *
 * @author Andrej Kabachnik
 *        
 */
class Split extends Container implements iFillEntireContainer
{
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';
    
    private $orientation = null;

    /**
     * Creates a new SplitPanel for this Split and returns it.
     * The panel is not automatically added to the panels collection!
     *
     * @return SplitPanel
     */
    private function createSplitPanel()
    {
        $widget = $this->getPage()->createWidget('SplitPanel', $this);
        return $widget;
    }

    /**
     * Returns the panels of the Split.
     * Technically it is an alias for Split::getWidgets() for better readability.
     *
     * @see getWidgets()
     */
    public function getPanels()
    {
        return $this->getWidgets();
    }

    /**
     * Sets an array of SplitPanel widgets.
     * 
     * Adding widgets to a Split will automatically produce SplitPanels for each widget, 
     * unless it already is one. This way, a short an understandable notation of splits 
     * is possible: simply add any type of widget to the panels or widgets array and see 
     * them be displayed in the split.
     * 
     * @uxon-property panels
     * @uxon-type \exface\Core\Widgets\SplitPanel[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"caption": "", "widgets": [{"": ""}]}]
     *
     * @param UxonObject|SplitPanel|AbstractWidget $widget_or_uxon_array
     * @return \exface\Core\Widgets\SplitVertical
     */
    public function setPanels($widget_or_uxon_array) : SplitVertical
    {
        return $this->setWidgets($widget_or_uxon_array);
    }

    /**
     * Specifies the widgets to be used as split panels - same as the panels property.
     * 
     * Adding widgets to a Split will automatically produce SplitPanels for each widget, 
     * unless it already is one. This way, a short an understandable notation of splits 
     * is possible: simply add any type of widget to the panels or widgets array and see 
     * them be displayed in the split.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\SplitPanel[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"widgets": [{"": ""}]}]
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                $page = $this->getPage();
                $widget = WidgetFactory::createFromUxon($page, $w, $this, 'SplitPanel');
            } elseif ($w instanceof WidgetInterface) {
                // If it is already a widget, take it for further checks
                $widget = $w;
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $this->addWidget($w);
            }
            
            // If the widget is not a SplitPanel itslef, wrap it in a SplitPanel. Otherwise add it directly to the result.
            if (! ($widget instanceof SplitPanel)) {
                $panel = $this->createSplitPanel();
                $panel->setHeight($widget->getHeight());
                $widget->setHeight('100%');
                $panel->setWidth($widget->getWidth());
                $widget->setWidth('100%');
                $panel->addWidget($widget);
                $widgets[] = $panel;
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
     *
     * @return string
     */
    protected function getOrientation() : string
    {
        if ($this->orientation === null) {
            return self::ORIENTATION_HORIZONTAL;
        }
        return $this->orientation;
    }
    
    /**
     * A Split can either be vertical (panels below-each-other) or horizontal (panels side-by-side).
     * 
     * @uxon-property orientation
     * @uxon-type [vertical,horizontal]
     * @uxon-default horizontal
     * 
     * @param string $value
     * @return Split
     */
    public function setOrientation(string $value) : Split
    {
        $value = trim(strtolower($value));
        
        if ($value !== self::ORIENTATION_HORIZONTAL && $value !== self::ORIENTATION_VERTICAL) {
            throw new WidgetConfigurationError($this, 'Invalid Split type "' . $value . '": only "vertical" or "horizontal" are allowed!');
        }
        
        $this->orientation = $value;
        return $this;
    }
    
    public function isVertial() : bool
    {
        return $this->getOrientation() === self::ORIENTATION_VERTICAL;
    }
    
    public function isSideBySide() : bool
    {
        return $this->getOrientation() === self::ORIENTATION_HORIZONTAL;
    }
    
    
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        $firstPanel = $this->getWidgetFirst();
        if ($filler = $firstPanel->getFillerWidget()) {
            if ($alternative = $filler->getAlternativeContainerForOrphanedSiblings()) {
                return $alternative;
            }
        }
        return $firstPanel;
    }
}