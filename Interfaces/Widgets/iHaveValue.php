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
     *
     * @param ExpressionInterface|string $expression_or_string            
     */
    public function setValue($value);

    /**
     *
     * @return ExpressionInterface
     */
    public function getValueExpression();

    /**
     * Returns the link to the widget, this widget's value is linked to.
     * Returns NULL if the value of this widget is not a link.
     *
     * The widget link will be resolved relative to the id space of this widget.
     *
     * @return NULL|\exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getValueWidgetLink();

    /**
     * Returns the data type of the widget's value.
     * 
     * @return DataTypeInterface
     */
    public function getValueDataType();
    
    /**
     * Returns the placeholder text to be used by templates if the widget has no value.
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