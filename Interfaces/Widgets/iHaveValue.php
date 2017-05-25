<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveValue extends WidgetInterface
{

    /**
     *
     * @return string
     */
    public function getValue();

    /**
     *
     * @param Expression|string $expression_or_string            
     */
    public function setValue($value);

    /**
     *
     * @return Expression
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