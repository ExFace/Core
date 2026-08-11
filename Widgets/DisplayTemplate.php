<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iHaveMultipleBindings;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Widgets\Parts\WidgetPropertyBinding;
use exface\Core\Widgets\Traits\AttributeCaptionTrait;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;

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
 * ## Rendering as formatted text
 * 
 * For UI5 tables, you can set `render_as_formatted_text` to render the template via `sap.m.FormattedText`
 * instead of `sap.ui.core.HTML`. This typically improves scroll performance because the control is lighter.
 * 
 * `sap.m.FormattedText` supports only a safe subset of HTML. If the template depends on complex layout markup or unsupported tags, keep this option disabled.
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
class DisplayTemplate extends AbstractWidget implements iShowSingleAttribute, iHaveValue, iShowDataColumn, iHaveMultipleBindings
{
    use AttributeCaptionTrait;
    
    const BINDING_PROPERTY_TEMPLATE = 'template';
    
    private ?string $template = null;
    private ?string $emptyText = null;
    private ?array $bindings = null;
    private bool $render_as_formatted_text = false;
    
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
        $bindings = $this->getBindings();
        if (empty($bindings)) {
            // if there are no bindings, return string datatype, and assume its just html (?)
            return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
        }
        return $bindings[0]->getDataType();
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
     * Returns TRUE if the template should be rendered as `sap.m.FormattedText` where supported.
     *
     * @return bool
     */
    public function getRenderAsFormattedText() : bool
    {
        return $this->render_as_formatted_text;
    }

    /**
     * Set to TRUE to render this template as `sap.m.FormattedText` in UI5 table cells.
     * 
     * This can improve performance in large tables compared to `sap.ui.core.HTML`, but only
     * a limited/safe subset of HTML is supported by `sap.m.FormattedText`.
     * 
    * Text in HTML format. The following tags are supported:
    * 
    * - `a`
    * - `abbr`
    * - `bdi`
    * - `blockquote`
    * - `br`
    * - `cite`
    * - `code`
    * - `em`
    * - `h1`
    * - `h2`
    * - `h3`
    * - `h4`
    * - `h5`
    * - `h6`
    * - `p`
    * - `pre`
    * - `strong`
    * - `span`
    * - `u`
    * - `s`
    * - `dl`
    * - `dt`
    * - `dd`
    * - `ul`
    * - `ol`
    * - `li`
    * 
    * `style`, `dir` and `target` attributes are allowed.
     * 
     * @uxon-property render_as_formatted_text
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DisplayTemplate
     */
    public function setRenderAsFormattedText(bool $value) : DisplayTemplate
    {
        $this->render_as_formatted_text = $value;
        return $this;
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
        // Return the first non-null expression across all bindings, similar to hasValue()
        // If we only return binding[0]->getValueExpression(), this causes issues with mixed template (alias and formulas)
        // attribute-first mixed template would return null while hasValue() returns true, which causes reference issues
        foreach ($this->getBindings() as $binding) {
            if (null !== $expr = $binding->getValueExpression()) {
                return $expr;
            }
        }
        return null;
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

    /**
     * TODO do we need this? Why was this not part of any interfaces?
     * @return bool
     */
    public function hasColorScale() : bool
    {
        return false;
    }

    /**
     * TODO, was needed for DataColumn methods in Matrix??
     * @param mixed $value
     * @return static
     */
    public function setAttributeAlias($value)
    {
        return $this;
    }
    
}