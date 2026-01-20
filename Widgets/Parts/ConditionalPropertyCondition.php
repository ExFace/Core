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
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Input;


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
    
    private ConditionalProperty $conditionGroup;
    
    private string|ExpressionInterface|bool|int|float|null $valueLeft = null;
    private ?ExpressionInterface $valueLeftExpr = null;
    private ?WidgetLinkInterface $valueLeftLink = null;
    private ?bool $valueLeftIsLink = null;
    
    private ?string $comparator = null;
    
    private string|ExpressionInterface|bool|int|float|null $valueRight = null;
    private ?ExpressionInterface $valueRightExpr = null;
    private ?WidgetLinkInterface $valueRightLink = null;
    private ?bool $valueRightIsLink = null;
    
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
     * Returns the comparator defined for this condition - optionally optimized for use in with the current widget
     * 
     * If `$optimized` ist set to `true`, the comparator will be automatically converted to the best match
     * for this condition - e.g. scalar comparators will be converted to list comparators if the condition
     * references a multi-select widget, etc.
     * 
     * @param bool $optimize
     * @return string
     */
    public function getComparator(bool $optimize = false) : string
    {
        $comparator = $this->comparator ?? ComparatorDataType::IS;
        if ($optimize === true) {
            $leftTargetWidget = $this->getValueLeftLinkTarget(true);
            $rightTargetWidget = $this->getValueRightLinkTarget(true);
            // Check if either expression is a reference to a widget, that supports multi-select or similar features.
            // In those cases, the comparator needs to be a list comparator, in case more than one data-set must
            // be evaluated.
            // TODO Filters support multi-select, but do not expose that information. How to handle this?
            $requiresListComparator =
                ($leftTargetWidget instanceof iSupportMultiSelect && $leftTargetWidget->getMultiSelect())
                || ($leftTargetWidget instanceof Input && $leftTargetWidget->getMultipleValuesAllowed())
                || ($rightTargetWidget instanceof iSupportMultiSelect && $rightTargetWidget->getMultiSelect())
                || ($rightTargetWidget instanceof Input && $rightTargetWidget->getMultipleValuesAllowed());

            if ($requiresListComparator === true) {
                $comparator = ComparatorDataType::convertToListComparator($comparator) ?? $comparator;
            }
        }
        return $comparator;
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
     * ### Comparing a scalar value to a list (IN, NOT IN)
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
     * 
     * Additionally, you can also use the **EACH** and **ANY** comparators below if with a scalar value on one side.
     * 
     * ### Comparing two lists
     * 
     * - `][` - intersection - compares two lists with each other. Becomes TRUE when there is at least
     * one element, that is present in both lists.
     * - `!][` - the inverse of `][`. Becomes TRUE if no element is part of both lists.
     * - `[[` - subset - compares two lists with each other. Becomes true when all elements of the left list
     * are in the right list too
     * - `![[` - the inverse of `][`. Becomes true when at least one element of the left list is NOT in
     * the right list.
     * 
     * ### EACH comparators
     * 
     * The following comparators yield TRUE if **EACH** of the values of the left list yields TRUE
     * when compared to at least one value of the right list using the respective scalar comparator.
     *
     * - `[=` - each value left is at least one value on the right
     * - `[!=` - at least one value on the left does not match any value on the right
     * - `[==` - each value left equals at least one value on the right exactly
     * - `[!==` - at least one value on the left does not exactly equal any value on the right
     * - `[<` - each value left is less than any value on the right
     * - `[<=` - each value left is less than or equals any value on the right
     * - `[>` - each value left is greater than any value on the right
     * - `[>=` - each value left is greater than or equals value on the right
     * 
     * ### ANY comparators
     * 
     * Similarly, the following comparators will yield TRUE if **ANY** of the values of the left list yields TRUE
     * when compared to at least one value of the right list using the respective scalar comparator.
     * 
     * - `]=` - at least one value left is at least one value on the right
     * - `]!=` - none of the left values match any value on the right
     * - `]==` - at least one value left equals at least one value on the right exactly
     * - `]!==` - none of the left values equals exactly any value on the right
     * - `]<` - at least one value left is less than any value on the right
     * - `]<=` - at least one value left is less than or equals any value on the right
     * - `]>` - at least one value left is greater than any value on the right
     * - `]>=` - at least one value left is greater than or equals value on the right
     * 
     *  ## Range comparators
     * 
     *  - `..` - range between two values - e.g. `1 .. 5` 
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
     * @param WidgetInterface $sourceWidget
     * @param bool $silent
     * @return WidgetLinkInterface|null
     */
    public function getValueLeftLink() : ?WidgetLinkInterface
    {
        if ($this->valueLeftIsLink === null) {
            $expr = $this->getValueLeftExpression();
            $this->valueLeftIsLink = $expr->isReference();
            $this->valueLeftLink = $expr->isReference() ? $expr->getWidgetLink($this->getWidget()) : null;
        }
        return $this->valueLeftLink;
    }

    /**
     * @param WidgetInterface $sourceWidget
     * @param bool $silent
     * @return WidgetLinkInterface|null
     */
    public function getValueRightLink() : ?WidgetLinkInterface
    {
        if ($this->valueRightIsLink === null) {
            $expr = $this->getValueRightExpression();
            $this->valueRightIsLink = $expr->isReference();
            $this->valueRightLink = $expr->isReference() ? $expr->getWidgetLink($this->getWidget()) : null;
        }
        return $this->valueRightLink;
    }

    /**
     * @param bool $silent
     * @return WidgetLinkInterface|null
     * @throws \Throwable
     */
    public function getValueLeftLinkTarget(bool $silent = false) : ?WidgetInterface
    {
        $link = $this->getValueLeftLink();
        if ($link === null) {
            return null;
        }
        try {
            $target = $link->getTargetWidget();
        } catch (\Throwable $e) {
            if ($silent) {
                return null;
            }
            throw $e;
        }
        return $target;
    }

    /**
     * @param bool $silent
     * @return WidgetLinkInterface|null
     * @throws \Throwable
     */
    public function getValueRightLinkTarget(bool $silent = false) : ?WidgetInterface
    {
        $link = $this->getValueRightLink();
        if ($link === null) {
            return null;
        }
        try {
            $target = $link->getTargetWidget();
        } catch (\Throwable $e) {
            if ($silent) {
                return null;
            }
            throw $e;
        }
        return $target;
    }
    
    public function getValueListDelimiter() : string
    {
        $rightTargetWidget = $this->getValueRightLinkTarget(true);
        if ($rightTargetWidget !== null) {
            if (($rightTargetWidget instanceof iShowSingleAttribute) && $rightTargetWidget->isBoundToAttribute()) {
                $delim = $rightTargetWidget->getAttribute()->getValueListDelimiter();
            } elseif ($rightTargetWidget instanceof iHaveColumns && $colName = $this->getValueRightLink()->getTargetColumnId()) {
                $targetCol = $rightTargetWidget->getColumnByDataColumnName($colName);
                if ($targetCol->isBoundToAttribute() === true) {
                    $delim = $targetCol->getAttribute()->getValueListDelimiter();
                }
            }
        }
        return $delim ?? EXF_LIST_SEPARATOR;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return $this->getValueLeftExpression()->__toString() . ' ' . $this->getComparator() . ' ' . $this->getValueRightExpression()->__toString();
    }
}