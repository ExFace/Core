<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

interface iHaveValue extends WidgetInterface
{

    /**
     * Returns the current value of this widget if it is set and NULL otherwise - no fallback to default value!
     * 
     * @return string|null
     */
    public function getValue();
    
    /**
     * Returns the value of this widget if set and the default value otherwise.
     * 
     */
    public function getValueWithDefaults();

    /**
     * Sets the value of the widget to a string or an instance of Expression.
     * 
     * If the first parameter is a string, it will be parsed into an expression unless
     * $parseStringAsExpression is explicitly set to FALSE.
     * 
     * @param ExpressionInterface|string $expressionOrString   
     * @param bool $parseStringAsExpression
     * 
     * @triggers \exface\Core\Events\Widget\OnWidgetLinkedEvent
     * 
     * @return iHaveValue
     */
    public function setValue($expressionOrString, bool $parseStringAsExpression = true);

    /**
     *
     * @return ExpressionInterface
     */
    public function getValueExpression() : ?ExpressionInterface;

    /**
     * Returns the link to the widget, this widget's value is linked to.
     * Returns NULL if the value of this widget is not a link.
     *
     * The widget link will be resolved relative to the id space of this widget.
     *
     * @return NULL|\exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getValueWidgetLink() : ?WidgetLinkInterface;
    
    /**
     * Returns TRUE if a value is set for this widget and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasValue() : bool;

    /**
     * Returns the data type of the widget's value.
     * 
     * If a widget references a meta attribute, this data type should be compatible
     * with the attribute's type. In most cases, the attribute's data type should be
     * used as default. 
     * 
     * Override this method to add extra data type options specified by the widget itself.
     * 
     * @return DataTypeInterface
     */
    public function getValueDataType();
    
    /**
     * Returns the placeholder text to be used by facades if the widget has no value.
     *
     * @return string
     */
    public function getEmptyText();
    
    /**
     * Defines the placeholder text to be used if the widget has no value.
     * Set to blank string to remove the placeholder.
     *
     * @param string $value
     * @return iHaveValue
     */
    public function setEmptyText($value);
}