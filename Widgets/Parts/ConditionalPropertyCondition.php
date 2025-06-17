<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;


/**
 * A condition compares it's `value_left` with `value_right` using the `comparator`.
 * 
 * Widget property conditions are used in so-called conditional widget properties like
 * `disabled_If`, where the property value is calculated live by evaluating the condition 
 * every time it's left or right side changes.
 * 
 * ## Available values and references
 * 
 * Each condition compares `value_right` and `value_left`. Each of them can either be a value (string or number), a
 * static formula or a widget reference. 
 * 
 * A few examples:
 * 
 * - `1` - the scalar value "1"
 * - `=User('USERNAME')` - resolves to the username of the current user
 * - `=some_widget` - references the entire widget with id `some_widget`
 * - `=some_widget!mycol` - references the column `mycol` in the data of the widget with id `some_widget`
 * 
 * There are also a couple of "shortcut" references available instead of explicit page/widget ids:
 * 
 * - `~self` - references the widget the link is defined in
 * - `~parent` - references the immediate parent of `~self`
 * - `~input` - references the `input_widget` of a `Button` or anything else that supports input widgets. 
 * 
 * For example:
 * 
 * - `=~self!mycol` - references the column `mycol` in the data of the current widget
 * - `=~parent!mycol` - references the column `mycol` of the current widgets parent
 * - `=~input!mycol` - references the column `mycol` of the input widget (if the current widget is a `Button`)
 * 
 * @see ConditionalProperty
 * 
 * @author Andrej Kabachnik
 * 
 */
class ConditionalPropertyCondition implements WidgetPartInterface, \Stringable
{
    use ImportUxonObjectTrait;
    
    /**
     * 
     * @var ConditionalProperty
     */
    private $conditionGroup = null;
    
    /**
     * 
     * @var string|ExpressionInterface
     */
    private $valueLeft = null;
    
    /**
     * 
     * @var ExpressionInterface
     */
    private $valueLeftExpr = null;
    
    /**
     * 
     * @var string
     */
    private $comparator = null;
    
    /**
     * 
     * @var string|ExpressionInterface
     */
    private $valueRight = null;
    
    /**
     * 
     * @var ExpressionInterface
     */
    private $valueRightExpr = null;
    
    /**
     * 
     * @param ConditionalProperty $conditionGroup
     */
    public function __construct(ConditionalProperty $conditionGroup, UxonObject $uxon = null)
    {
        $this->conditionGroup = $conditionGroup;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'value_left' => $this->getValueLeftExpression()->toString(),
            'comparator' => $this->getComparator(),
            'value_right' => $this->getValueRightExpression()->toString()
        ]);
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->conditionGroup->getWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->conditionGroup->getWorkbench();
    }
    
    /**
     *
     * @return string
     */
    public function getComparator() : string
    {
        return $this->comparator ?? ComparatorDataType::IS;
    }
    
    /**
     * Comparator to use in this condition.
     * 
     * ## Scalar (single value) comparators
     * 
     * - `=` - universal comparator similar to SQL's `LIKE` with % on both sides. Can compare different 
     * data types. If the left value is a string, becomes TRUE if it contains the right value. Case 
     * insensitive for strings
     * - `!=` - yields TRUE if `IS` would result in FALSE
     * - `==` - compares two single values of the same type. Case sensitive for stings. Normalizes the 
     * values before comparison though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
     * - `!==` - the inverse of `EQUALS`
     * - `<` - yields TRUE if the left value is less than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `<=` - yields TRUE if the left value is less than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * - `>` - yields TRUE if the left value is greater than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `>=` - yields TRUE if the left value is greater than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * 
     * ## List comparators
     * 
     * - `[` - IN-comparator - compares a value with each item in a list via EQUALS. Becomes true if the left
     * value equals at least on of the values in the list within the right value. The list on the
     * right side must consist of numbers or strings separated by commas or the attribute's value
     * list delimiter if filtering over an attribute. The right side can also be another type of
     * expression (e.g. a formula or widget link), that yields such a list.
     * - `![` - the inverse von `[` . Becomes true if the left value equals none of the values in the 
     * list within the right value. The list on the right side must consist of numbers or strings separated 
     * by commas or the attribute's value list delimiter if filtering over an attribute. The right side can 
     * also be another type of expression (e.g. a formula or widget link), that yields such a list.
     * - `][` - intersection - compares two lists with each other. Becomes TRUE when there is at least 
     * one element, that is present in both lists.
     * - `!][` - the inverse of `][`. Becomes TRUE if no element is part of both lists.
     * - `[[` - subset - compares two lists with each other. Becomes true when all elements of the left list 
     * are in the right list too
     * - `![[` - the inverse of `][`. Becomes true when at least one element of the left list is NOT in 
     * the right list.
     * 
     * ## Range comparators
     * 
     * - `..` - range between two values - e.g. `1 .. 5`
     * 
     * @uxon-property comparator
     * @uxon-type metamodel:comparator
     * @uxon-default =
     * 
     * @param string $value
     * @return ConditionalPropertyCondition
     */
    public function setComparator(string $value) : ConditionalPropertyCondition
    {
        $this->comparator = ComparatorDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return ExpressionInterface
     */
    public function getValueLeftExpression() : ExpressionInterface
    {
        if ($this->valueLeftExpr === null) {
            if ($this->valueLeft === null) {
                throw new WidgetConfigurationError($this->getWidget(), 'Missing value_left value for conditional property "' . $this->conditionGroup->getPropertyName() . '"!');
            }
            if ($this->valueLeft instanceof ExpressionInterface) {
                $this->valueLeftExpr = $this->valueLeft;
            } else {
                $this->valueLeftExpr = ExpressionFactory::createFromString($this->getWorkbench(), $this->valueLeft, $this->getBaseObject());
            }
        }
        return $this->valueLeftExpr;
    }
    
    /**
     * Left side of the condition: `=widget_link` link or `scalar`
     * 
     * @uxon-property value_left
     * @uxon-type metamodel:widget_link|string
     * 
     * @param string|ExpressionInterface $stringOrUxon
     * @return ConditionalPropertyCondition
     */
    public function setValueLeft($stringOrExpression) : ConditionalPropertyCondition
    {
        $this->valueLeft = $stringOrExpression;
        $this->valueLeftExpr = null;
        return $this;
    }
    
    /**
     *
     * @return ExpressionInterface
     */
    public function getValueRightExpression() : ExpressionInterface
    {
        if ($this->valueRightExpr === null) {
            if ($this->valueRight === null) {
                throw new WidgetConfigurationError($this->getWidget(), 'Missing value_right for conditional property "' . $this->conditionGroup->getPropertyName() . '"!');
            }
            if ($this->valueRight instanceof ExpressionInterface) {
                $this->valueRightExpr = $this->valueRight;
            } else {
                $this->valueRightExpr = ExpressionFactory::createFromString($this->getWorkbench(), $this->valueRight, $this->getBaseObject());
            }
        }
        return $this->valueRightExpr;
    }
    
    /**
     * Right side of the condition: `=widget_link` link or `scalar`
     * 
     * @uxon-property value_right
     * @uxon-type string|metamodel:widget_link
     *
     * @param string|ExpressionInterface $stringOrUxon
     * @return ConditionalPropertyCondition
     */
    public function setValueRight($stringOrExpression) : ConditionalPropertyCondition
    {
        $this->valueRight = $stringOrExpression;
        $this->valueRightExpr = null;
        return $this;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getBaseObject() : MetaObjectInterface
    {
        return $this->conditionGroup->getBaseObject();
    }
    
    /**
     * 
     * @return bool
     */
    public function hasLiveReference() : bool
    {
        return $this->getValueLeftExpression()->isReference() || $this->getValueRightExpression()->isReference();
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return $this->getValueLeftExpression()->__toString() . ' ' . $this->getComparator() . ' ' . $this->getValueRightExpression()->__toString();
    }
}