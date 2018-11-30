<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Navigates to the given page optionally taking along a set of definable filters.
 * 
 * @author Andrej Kabachnik
 *        
 */
class GoToPage extends ShowWidget
{

    private $takeAlongFilters = array();
    
    private $prefillWithUidOnly = true;

    private $open_in_new_window = false;
    
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
     * @uxon-type WidgetLink[]
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
?>