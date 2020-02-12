<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 * Filters data, where an attribute's value lies between the range boundaries.
 * 
 * The `RangeFilter` lets the user specify the lower and the upper range boundary
 * for a single attiribute.
 * 
 * The visual appearance may differ depending on the facade used and the type of
 * the attribute. The simplest version would be simply two identical input widgets
 * side-by-side. However, special date-range filters are also common.
 * 
 * Instead of having a single `comparator`, the `RangeFilter` provides the option
 * to define `comparator_from` and `comparator_to` for both boudnaries separately.
 * Keep in mind, that only `>`, `<`, `>=` and `<=` make sense for ranges!
 * 
 * Similarly, the default values can be set via `value_from` and `value_to`.
 * 
 * You can customize the `input_widget` just like in regular `Filter` widgets.
 * If you choose not to, the default editor of the attribute will be used.
 * 
 * ## Examples
 * 
 * ### Date range
 * 
 * ```
 * {
 *  "widget_type": "FilterRange",
 *  "attribute_alias": "start_date"
 * }
 * 
 * ```
 * 
 * ### Date range with default value
 * 
 * ```
 * {
 *  "widget_type": "FilterRange",
 *  "attribute_alias": "start_date",
 *  "value_from": "now"
 * }
 * 
 * ```
 * 
 * ### Exclusive number range
 * 
 * ```
 * {
 *  "widget_type": "FilterRange",
 *  "attribute_alias": "value",
 *  "comparator_from": ">",
 *  "comparator_to": "<"
 * }
 * 
 * ```
 *     
 * @author Andrej Kabachnik
 *        
 */
class RangeFilter extends Filter
{
    private $comparatorFrom = ComparatorDataType::GREATER_THAN_OR_EQUALS;
    
    private $comparatorTo = ComparatorDataType::LESS_THAN_OR_EQUALS;
    
    private $valueFrom = null;
    
    private $valueTo = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::getComparator()
     */
    public function getComparator() : ?string
    {
        return $this->getComparatorFrom();
    }
    
    /**
     * @deprecated Use setComparatorFrom() and setComparatorTo() for RangeFilters instead!
     * 
     * @see \exface\Core\Widgets\Filter::setComparator()
     */
    public function setComparator(string $value) : Filter
    {
        if ($value === ComparatorDataType::LESS_THAN || $value === ComparatorDataType::LESS_THAN_OR_EQUALS) {
            $this->setComparatorTo($value);
        } else {
            $this->setComparatorFrom($value);
        }
        return $this;
    }
    
    /**
     * 
     * @param string $comparator
     * @throws WidgetPropertyInvalidValueError
     * @return string
     */
    protected function validateComparator(string $comparator) : string
    {
        if (in_array(ComparatorDataType::cast($comparator), $this->getValidComparators()) === false) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid comparator "' . $comparator . '" used for RangeFilter widget: only <, >, <=, >= supported!');
        }
        return $comparator;
    }
    
    /**
     *
     * @param WidgetInterface $input
     * @return ComparatorDataType|NULL
     */
    protected function getDefaultComparator(WidgetInterface $input) : ?ComparatorDataType
    {
        return null;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::enhanceInputWidgetWithComparatorHint()
     */
    protected function enhanceInputWidgetWithComparatorHint(WidgetInterface $input) : WidgetInterface
    {
        return $input;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getValidComparators() : array
    {
        return [
            ComparatorDataType::GREATER_THAN_OR_EQUALS,
            ComparatorDataType::GREATER_THAN,
            ComparatorDataType::LESS_THAN_OR_EQUALS,
            ComparatorDataType::LESS_THAN
        ];
    }
    
    /**
     *
     * @return string
     */
    public function getComparatorFrom() : string
    {
        return $this->comparatorFrom;
    }
    
    /**
     * Comparison operator for the lower range value: e.g. >=.
     * 
     * @uxon-property comparator_from
     * @uxon-type [>,>=]
     * @uxon-default >=
     *
     * @param string $value
     * @return RangeFilter
     */
    public function setComparatorFrom(string $value) : RangeFilter
    {
        $comp = $this->validateComparator($value);
        $this->comparatorFrom = $comp;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getComparatorTo() : string
    {
        return $this->comparatorTo;
    }
    
    /**
     * Comparison operator for the upper range value: e.g. <=.
     * 
     * @uxon-property comparator_to
     * @uxon-type [<,<=]
     * @uxon-default <=
     *
     * @param string $value
     * @return RangeFilter
     */
    public function setComparatorTo(string $value) : RangeFilter
    {
        $comp = $this->validateComparator($value);
        $this->comparatorTo = $comp;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getValueFrom() : ?string
    {
        return $this->valueFrom !== null ? $this->valueFrom->toString() : null;
    }
    
    /**
     * 
     * @return ExpressionInterface|NULL
     */
    public function getValueFromExpression() : ?ExpressionInterface
    {
        return $this->valueFrom;
    }
     
    /**
     * Value of the lower range boundary: scalar, formula or widget link.
     * 
     * @uxon-property value_from
     * @uxon-type metamodel:expression|string
     * 
     * @param string $value
     * @return RangeFilter
     */
    public function setValueFrom($expression_or_string) : RangeFilter
    {
        if ($expression_or_string instanceof ExpressionInterface) {
            $this->valueFrom = $expression_or_string;
        } else {
            // FIXME #expression-syntax - see AbstractWidget::setValue()
            $this->valueFrom = ExpressionFactory::createFromString($this->getWorkbench(), $expression_or_string, $this->getMetaObject(), true);
        }
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getValueTo() : ?string
    {
        return $this->valueTo !== null ? $this->valueTo->toString() : null;
    }
    
    /**
     * 
     * @return ExpressionInterface|NULL
     */
    public function getValueToExpression() : ?ExpressionInterface
    {
        return $this->valueTo;
    }
    
    /**
     * Value of the upper range boundary: scalar, formula or widget link.
     * 
     * @uxon-property value_to
     * @uxon-type metamodel:expression|string
     * 
     * @param string|ExpressionInterface $value
     * @return RangeFilter
     */
    public function setValueTo($expression_or_string) : RangeFilter
    {
        if ($expression_or_string instanceof ExpressionInterface) {
            $this->valueTo = $expression_or_string;
        } else {
            // FIXME #expression-syntax - see AbstractWidget::setValue()
            $this->valueTo = ExpressionFactory::createFromString($this->getWorkbench(), $expression_or_string, $this->getMetaObject(), true);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::hasValue()
     */
    public function hasValue()
    {
        return $this->valueFrom !== null || $this->valueTo !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasValueFrom() : bool
    {
        return $this->valueFrom !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasValueTo() : bool
    {
        return $this->valueTo !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::getValue()
     */
    public function getValue()
    {
        return $this->getValueFrom() . ComparatorDataType::BETWEEN . $this->getValueTo();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::setValue()
     */
    public function setValue($value)
    {
        $dots = ComparatorDataType::BETWEEN;
        if (strpos($value, $dots) === false) {
            $from = $value;
            $to = $value;
        } else {
            list($from, $to) = explode($dots, $value);
        }
        
        $this->setValueFrom($from);
        $this->setValueTo($to);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Filter::getValueExpression()
     */
    public function getValueExpression()
    {
        if ($this->hasValue() === false) {
            return parent::getValueExpression();
        } else {
            throw new LogicException('Cannot get value expression for widget "' . $this->getWidgetType() . '" - please use getValueFromExpression() and getValueToExpression() instead!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getValueWidgetLink()
     */
    public function getValueWidgetLink()
    {
        if ($this->hasValue() === false) {
            return parent::getValueWidgetLink();
        } else {
            throw new LogicException('Cannot get value expression for widget "' . $this->getWidgetType() . '" - please use getValueFromExpression() and getValueToExpression() instead!');
        }
    }
    
    /**
     *
     * @return WidgetLinkInterface|NULL
     */
    public function getValueFromWidgetLink() : ?WidgetLinkInterface
    {
        $link = null;
        $expr = $this->getValueFromExpression();
        if ($expr && $expr->isReference()) {
            $link = $expr->getWidgetLink($this);
        }
        return $link;
    }
    
    /**
     *
     * @return WidgetLinkInterface|NULL
     */
    public function getValueToWidgetLink() : ?WidgetLinkInterface
    {
        $link = null;
        $expr = $this->getValueToExpression();
        if ($expr && $expr->isReference()) {
            $link = $expr->getWidgetLink($this);
        }
        return $link;
    }
}