<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

/**
 * An Input widget with a button next to it: the button is pressed automatically on enter in the input.
 * 
 * The action of the button receives the value of the `Input` as input data by default. If the action
 * returns data, this data will be available in the `InputButton` via widget links afer the action had
 * been performed - just like the table columns in an `InputComboTable`.
 * 
 * ## Examples
 * 
 * ### Looking up some information based on an identifier. 
 * 
 * In the following example, we have an `InputButton` for the product number, which calls a custom lookup action 
 * returning some product data - in particular the product name. The `Panel` also includes a readonly-widget 
 * showing the product name, that get's filled automatically once the user enters the product number.
 * 
 * ```
 * {
 *  "widget_type": "Panel",
 *  "widgets": [
 *      {
 *          "id": "product_number_input",
 *          "widget_type": "InputButton",
 *          "attribute_alias": "product_number",
 *          "button": {
 *              "action_alias": "my.App.LookupProductInfo"
 *          }
 *      },
 *      {
 *          "attribute_alias": "product_name",
 *          "value": "=product_number_input!product_name",
 *          "readonly": true
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * Once the lookup action is performed, it's result is stored as a table behind the `InputData` and the reference
 * formula `=product_number_input!product_name` retrieves the value of the first row in the column `product_name`
 * of that data.
 * 
 * ### Performing an action upon scan
 * 
 * In apps, that use barcode scanners for input, it is sometimes usefull to pass the scanned code directly
 * to the server - e.g. for validation, looking up additional info, etc. In fact, the above product
 * lookup example will work fine in this case as long as the `InputButton` has focus. To make it work even
 * without focus, wee will need to use a parent widget with buttons (e.g. `Form`) and add a scan action to
 * it. Now, any scan will be sent to our `InputButton` - even if it does not have focus at the time (unless,
 * of course, another input element has focus).
 * 
 * ```
 * {
 *  "widget_type": "Form",
 *  "widgets": [
 *      {
 *          "id": "product_number_input",
 *          "widget_type": "InputButton",
 *          "attribute_alias": "product_number",
 *          "button": {
 *              "action_alias": "my.App.LookupProductInfo"
 *          }
 *      },
 *      {
 *          "attribute_alias": "product_name",
 *          "value": "=product_number_input!product_name",
 *          "readonly": true
 *      }
 *  ],
 *  "buttons": [
 *      {
 *          "hidden": true,
 *          "action": {
 *              "alias": "exface.BarcodeScanner.ScanToSetValue",
 *              "target_widget_id": "product_number_input"
 *          }
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * @see InputSelect
 *
 * @author Andrej Kabachnik
 */
class InputButton extends Input
{
    private $buttonUxon = null;
    
    private $button = null;
    
    private $buttonPressOnStart = false;
    
    /**
     * The button next to the input: it's action, icon, etc.
     * 
     * @uxon-property button
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"action":{"alias": ""}}
     *  
     * @param UxonObject $uxon
     * @return InputButton
     */
    public function setButton(UxonObject $uxon) : InputButton
    {
        $this->buttonUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @throws WidgetPropertyNotSetError
     * @return Button
     */
    public function getButton() : Button
    {
        if ($this->button === null) {
            if ($this->buttonUxon !== null) {
                $this->button = WidgetFactory::createFromUxonInParent($this, $this->buttonUxon, 'Button');
            } else {
                throw new WidgetPropertyNotSetError($this, 'No button defined in ' . $this->getWidgetType() . ': please add the "button" property!');
            }
        }
        return $this->button;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getButton();
    }
    
    /**
     * 
     * @return bool
     */
    public function getButtonPressOnStart() : bool
    {
        return $this->buttonPressOnStart;
    }
    
    /**
     * Set to TRUE to perform the button's action automatically, when the widget is loaded.
     * 
     * @uxon-property button_press_on_start
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return InputButton
     */
    public function setButtonPressOnStart(bool $trueOrFalse) : InputButton
    {
        $this->buttonPressOnStart = $trueOrFalse;
        return $this;
    }
}