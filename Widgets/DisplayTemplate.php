<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Widgets\iCanBeBoundToCalculation;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Widgets\iSupportAggregators;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Widgets\Traits\AttributeCaptionTrait;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Traits\iHaveAttributeGroupTrait;
use exface\Core\Widgets\Traits\PrefillValueTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * HTML or text template with placeholders replaced by values from a data row
 * 
 * This widget works similar to other templates in the system - it replaces `[#placeholders#]` of different types with 
 * their respective values from its prefill data row.
 * 
 * ```
 * {
 *  "widget_type": "DisplayTemplate",
 *  "template": "
 *      <div>[#attr#]</div>
 *      <div>[#=Formula()#]</div>
 *  "
 * }
 * 
 * ```
 *
 * It parses the template and produces widget property bindings for every placeholder.
 *
 * ## Table cells
 *
 * When used inside of data widget cells, this widget will make the Facade load all attributes required for the 
 * template and place them in the server data.
 *
 * ## Placeholders
 *
 * The following types of placeholders are supported:
 *
 * - `[#attribute_alias#]`
 * - `[#=Formula()#]`
 * - `[#='sclar'#]`
 *
 * @author Andrej Kabachnik
 *
 */
class DisplayTemplate extends AbstractWidget implements iShowSingleAttribute, iHaveValue, iShowDataColumn
{
    use AttributeCaptionTrait;
    
    const BINDING_PROPERTY_TEMPLATE = 'template';
    
    private ?string $template = null;
    private ?string $emptyText = null;
    private ?array $bindings = null;
    
    public function getTemplate() : string
    {
        if ($this->template === null) {
            throw new WidgetConfigurationError($this, 'No template defined for widget ' . $this->getWidgetType());
        }
        return $this->template;
    }
    
    protected function setTemplate(string $template) : DisplayTemplate
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return WidgetPropertyBinding[]
     */
    public function getBindings() : array
    {
        if ($this->bindings === null) {
            $this->bindings = [];
            $phs = StringDataType::findPlaceholders($this->getTemplate());
            foreach ($phs as $ph) {
                $uxon = new UxonObject();
                if (Expression::detectFormula($ph)) {
                    $uxon->setProperty('calculation', $ph);
                } else {
                    $uxon->setProperty('attribute_alias', $ph);
                }
                $this->bindings[] = new WidgetPropertyBinding($this, self::BINDING_PROPERTY_TEMPLATE, $uxon);
            }
        }
        return $this->bindings;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttributeAlias()
     */
    public function getAttributeAlias()
    {
        $firstBinding = null;
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToAttribute()) {
                return $binding->getAttributeAlias();
            }
            if ($firstBinding === null) {
                $firstBinding = $binding->getValueExpression()->__toString();
            }
        }
        return $firstBinding;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        foreach ($this->getBindings() as $binding) {
            $data_sheet = $binding->prepareDataSheetToRead($data_sheet);
        }
        return $data_sheet;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        // Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return $data_sheet;
        }
        foreach ($this->getBindings() as $binding) {
            $data_sheet = $binding->prepareDataSheetToPrefill($data_sheet);
        }
        return $data_sheet;
    }
    
    /**
     * A text widget is prefillable if it does not have a value or it's value
     * is a reference (live reference formula).
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::isPrefillable()
     */
    public function isPrefillable()
    {
        if (! parent::isPrefillable()) {
            return false;
        }
        return true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        foreach ($this->getBindings() as $binding) {
            $data_sheet = $binding->prefill($data_sheet);
        }
        return $data_sheet;
    }
    
    /**
     * Returns TRUE if this widget references a meta attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function isBoundToAttribute() : bool
    {
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToAttribute()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttribute()
     */
    public function getAttribute() : ?MetaAttributeInterface
    {
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToAttribute()) {
                return $binding->getAttribute();
            }
        }
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::isBoundToDataColumn()
     */
    public function isBoundToDataColumn() : bool
    {
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToDataColumn()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Returns the data type of the widget.
     *
     * The data type can either be set explicitly by UXON, or is derived from the shown meta attribute.
     * If there is neither an attribute bound to the column, nor an explicit data_type, the base data
     * type is returned.
     *
     * @return DataTypeInterface
     */
    public function getValueDataType()
    {
        return $this->getBindings()[0]->getDataType();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('template', $this->getTemplate());
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWithDefaults()
     */
    public function getValueWithDefaults()
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getEmptyText()
     */
    public function getEmptyText()
    {
        return $this->emptyText;
    }
    
    /**
     * Defines the placeholder text to be used if the widget has no value.
     * Set to blank string to remove the placeholder.
     *
     * The default placeholder is defined by the core translation of WIDGET.TEXT.EMPTY_TEXT.
     *
     * @uxon-property empty_text
     * @uxon-type string|metamodel:formula
     * @uxon-translatable true
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setEmptyText()
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getDataColumnName()
    {
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToDataColumn()) {
                return $binding->getDataColumnName();
            }
        }
        return null;
    }
    
    /**
     * 
     *
     * @uxon-property data_column_name
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::setDataColumnName()
     */
    public function setDataColumnName($value)
    {
        // TODO what to do here? 
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue() : bool
    {
        foreach ($this->getBindings() as $binding) {
            if ($binding->hasValue()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Returns TRUE if the widget represents a cell in a data widget.
     *
     * This way, in-table editors and display widgets can be easily detected.
     *
     * @see Data::setCellWidget()
     *
     * @return bool
     */
    public function isInTable() : bool
    {
        // DataColumn namespace needed here because the DataSheet columns are used in this file too! 
        return $this->getParent() instanceof \exface\Core\Widgets\DataColumn;
    }
    
    /**
     * Explicitly sets the value of the widget: static value, widget link or formula.
     * 
     * **WARNING:** If a calculated expression (link of formula) is used as `value`, the widget
     * will not be able to read the value of its attribute or prefill data. In fact, it will not
     * get prefilled at all: the formula replaces the own value. However, if the widget is interactive,
     * the value will still change on user input as long as the widget is not disabled. 
     * 
     * If you want the widget to have its own value in addition to a calculation use the `calculation`
     * option explicitly. In this case, the calculation will only be performed if the widget has no
     * explicit value.
     *
     * @uxon-property value
     * @uxon-type metamodel:expression
     * @uxon-translatable true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setValue()
     */
    public function setValue($expressionOrString, bool $parseStringAsExpression = true)
    {
        return $this->getBindings()[0]->setValue($expressionOrString);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValue()
     */
    public function getValue()
    {
        return $this->getBindings()[0]->getValue();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueExpression()
     */
    public function getValueExpression() : ?ExpressionInterface
    {
        return $this->getBindings()[0]->getValueExpression();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWidgetLink()
     */
    public function getValueWidgetLink() : ?WidgetLinkInterface
    {
        return null;
    }

    /**
     * Value widgets are affected by their own object and any objects in the relation path
     * if the value belongs to a relation attribute.
     * 
     * @see AbstractWidget::getMetaObjectsEffectingThisWidget()
     */
    public function getMetaObjectsEffectingThisWidget() : array
    {
        // Main object
        $objs = parent::getMetaObjectsEffectingThisWidget();
        // Objects used in columns
        
        foreach ($this->getBindings() as $binding) {
            if ($binding->isBoundToAttribute()) {
                $attr = $binding->getAttribute();
                $relPath = $attr->getRelationPath();
                if (!$relPath->isEmpty()) {
                    foreach ($relPath->getRelations() as $rel) {
                        $objs[] = $rel->getLeftObject();
                        $objs[] = $rel->getRightObject();
                    }
                }
            }
        }
        return array_unique($objs);
    }

    /**
     * TODO do we need this? Why was this not part of any interfaces?
     * @return WidgetLinkInterface|null
     */
    public function getCalculationWidgetLink() : ?WidgetLinkInterface
    {
        return null;
    }

    /**
     * TODO do we need this? Why was this not part of any interfaces?
     * @return bool
     */
    public function hasAggregator() : bool
    {
        return false;
    }
}