<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;


/**
 * A condition compares it's `value_left` with `value_right` using the `comparator`.
 * 
 * Widget property conditions are used in so-called conditional widget properties, where
 * the value is calculated live by evaluating the condition every time it's left or right
 * side changes.
 * 
 * Both sides of the condition can be either scalar values (i.e. numbers or strings) or 
 * widget links (starting with `=`).
 * 
 * @author Andrej Kabachnik
 * 
 */
class ConditionalPropertyCondition implements WidgetPartInterface
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
    public function __construct(ConditionalProperty $conditionGroup)
    {
        $this->conditionGroup = $conditionGroup;
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
        return $this->getWidget()->getWorkbench();
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
                $widget = $this->getWidget();
                $this->valueLeftExpr = ExpressionFactory::createFromString($widget->getWorkbench(), $this->valueLeft, $widget->getMetaObject());
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
                $widget = $this->getWidget();
                $this->valueRightExpr = ExpressionFactory::createFromString($widget->getWorkbench(), $this->valueRight, $widget->getMetaObject());
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
}