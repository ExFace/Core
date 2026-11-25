<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Model\UiScreenInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Filter;

/**
 * A focusable area of a widget - e.g. a column of a table, the header of a table or the user menu of the context bar.
 * 
 * A focus area is described by a string consisting of the id of the widget and the name of the area within this
 * widget, separated by `~`. The area part is optional - without it, the focus area is the entire widget.
 * 
 * Examples:
 * 
 * - `myTableId` - the entire widget
 * - `myTableId~column.Column1` - all cells of a column
 * - `myTableId~column.Column1$2` - the cell of a column in the second row
 * - `myTableId~header`
 * - `myTableId~table`
 * - `myTableId~row$3`
 * - `myGanttId~chart`
 * - `myInputComboTable~lookupButton`
 * - `ContextBar`
 * - `ContextBar~user`
 * 
 * The widget id is resolved within the given UI screen: on a page it is the regular widget id, inside a dialog
 * it is the id within the id space of the dialog - e.g. the id specified in the UXON of the dialog. The id
 * `ContextBar` always points to the context bar of the page.
 * 
 * What areas are available, depends on the facade element of the widget - see `buildCssFocusAreaSelector()` of
 * the elements in AJAX facades. Filters are replaced by their input widgets because they are rendered by them.
 * 
 * Widget links (e.g. `myTableId!Column1`) are not supported here - use `widget_to_focus` in tours for them.
 * 
 * @author Sergej Riel
 */
class FocusArea implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    const AREA_SEPARATOR = '~';
    
    const CONTEXT_BAR_ID = 'ContextBar';

    private UiScreenInterface $screen;
    
    private string $focusArea;
    
    private WidgetInterface $widget;
    
    private ?string $widgetArea;

    /**
     * Parses the given focus area string and resolves its widget within the given UI screen
     * 
     * @param UiScreenInterface $screen
     * @param string $focusArea
     */
    public function __construct(UiScreenInterface $screen, string $focusArea)
    {
        $this->screen = $screen;
        $this->focusArea = trim($focusArea);
        $widgetId = StringDataType::substringBefore($this->focusArea, self::AREA_SEPARATOR, $this->focusArea);
        $widgetArea = StringDataType::substringAfter($this->focusArea, self::AREA_SEPARATOR, null);
        $this->widgetArea = ($widgetArea === null || $widgetArea === '') ? null : $widgetArea;
        $this->widget = $this->findWidget($widgetId);
    }
    
    /**
     * Returns the widget with the given id from the screen of this focus area
     * 
     * Inside a dialog, the id is searched within the id space of the dialog. Filters are replaced by their
     * input widgets because filters are rendered by them.
     * 
     * ## Tours inside dialogs
     * 
     * The screen is the widget the tour is configured in: the page for page tours, the dialog for tours
     * defined in the UXON of a dialog. For page tours, the id is searched as-is.
     * 
     * Dialogs usually have their own id space, so the ids of their widgets are stored with a prefix:
     * `"id": "myTable"` inside the dialog becomes `<idSpace>.myTable`. The tour of the dialog, however,
     * uses the id as written in the dialog: `myTable~column.X`. Searching for `myTable` would not find the
     * table in the dialog or even return a widget with the same id from the main page. This is why the id
     * space of the dialog is prepended here - unless the id already starts with it.
     * 
     * This is the same logic as `WidgetLink::getTargetWidgetId()` uses for widget links.
     * 
     * @param string $widgetId
     * @return WidgetInterface
     */
    protected function findWidget(string $widgetId) : WidgetInterface
    {
        $screen = $this->screen;
        $page = $screen instanceof UiPageInterface ? $screen : $screen->getPage();
        
        if ($widgetId === self::CONTEXT_BAR_ID) {
            return $page->getContextBar();
        }
        
        if ($screen instanceof WidgetInterface) {
            // Prepend the id space of the dialog unless the id already includes it
            $idSpace = $screen->getIdSpace();
            if ($idSpace !== '') {
                $idSpace .= $page->getWidgetIdSpaceSeparator();
                if (! StringDataType::startsWith($widgetId, $idSpace)) {
                    $widgetId = $idSpace . $widgetId;
                }
            }
            $widget = $page->getWidget($widgetId, $screen);
        } else {
            $widget = $page->getWidget($widgetId);
        }
        
        if ($widget instanceof Filter) {
            $widget = $widget->getInputWidget();
        }
        return $widget;
    }

    /**
     * Returns the widget, that contains the focus area
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * Returns the UI screen (page or dialog), the focus area was resolved in
     * 
     * @return UiScreenInterface
     */
    public function getScreen() : UiScreenInterface
    {
        return $this->screen;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->screen->getWorkbench();
    }

    /**
     * Returns the complete focus area string relative to the screen - e.g. `myTableId~column.Column1`
     * 
     * @return string
     */
    public function getIdRelativeToScreen(): string
    {
        return $this->focusArea;
    }

    /**
     * Returns the area within the widget - e.g. `column.Column1` - or NULL if the entire widget is meant
     * 
     * @return string|null
     */
    public function getIdRelativeToWidget(): ?string
    {
        return $this->widgetArea;
    }
    
    /**
     * Returns TRUE if the focus area points to the entire widget and not to a specific area within it
     * 
     * @return bool
     */
    public function isEntireWidget() : bool
    {
        return $this->widgetArea === null;
    }
}
