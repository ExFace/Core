<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * Navigates to the given page taking the selected input object as filter and an optional set of additional filters.
 * 
 * By default, this action tries to keep the current context by prefilling the target page with the
 * object from the input data. In contrast to the regular behavior of `prefill_with_input_data` for
 * `ShowWidget*` actions (e.g. `ShowObject*`, etc.), this action will only use the UID from the input
 * data - not the other columns. This is done to avoid unexpected filter values coming from additional
 * columns of the input data (we don't want to set filters just because we can!).
 * 
 * If you need more filter presets, use `take_along_filters` to configure them explicitly!
 * 
 * @author Andrej Kabachnik
 *        
 */
class GoToPage extends ShowWidget
{

    private $takeAlongFilters = array();

    private $open_in_new_window = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setPrefillWithInputData(true);
    }

    /**
     *
     * @return WidgetLinkInterface[]
     */
    public function getTakeAlongFilters()
    {
        return $this->takeAlongFilters;
    }

    /**
     * Specifies filters on the target page that should be filled with values from widgets on the current page.
     *
     * This option accepts an object with attribute aliases for keys and
     * widget links for values. Thus, if you need to pass a filter over 
     * `ORDER__ORDER_DATE` to a page showing ORDER_POSITION and use the value 
     * of the widget with the id "my_date" on the current page, use the 
     * following configuration:
     *
     * ```
     * {
     *  "filters": [
     *      {
     *          "widget_type": "InputDate",
     *          "id": "my_date"
     *      }
     *  ],
     *  
     *  "buttons": [
     *      {
     *          "action": {
     *              "alias": "GoToPage",
     *              "take_along_filters": {
     *                  "ORDER__ORDER_DATE": "my_date"
     *              }
     *          }
     *      }
     *  ]
     * }
     *  
     * ```
     *
     * @uxon-property take_along_filters
     * @uxon-type object[]
     * @uxon-template {"": ""}
     *
     * @param UxonObject $takeAlongFilters
     * @return ShowWidget
     */
    public function setTakeAlongFilters(UxonObject $takeAlongFilters)
    {
        $array = [];
        foreach ($takeAlongFilters as $attributeAlias => $widgetLink) {
            if (! $widgetLink instanceof WidgetLinkInterface) {
                $array[$attributeAlias] = WidgetLinkFactory::createFromWidget($this->getWidgetDefinedIn(), $widgetLink);
            }
        }
        
        $this->takeAlongFilters = $array;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return $this->open_in_new_window;
    }
    
    /**
     * Set to TRUE to make the page open in a new browser window or tab (depending on the browser).
     * 
     * @uxon-property open_in_new_window
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool|string $value
     * @return \exface\Core\Actions\GoToPage
     */
    public function setOpenInNewWindow($value) : GoToPage
    {
        $this->open_in_new_window = BooleanDataType::cast($value);
        return $this;
    }
}