<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\CommonLogic\Model\Expression;

/**
 * The DiffText widget compares two texts and shows a report highlighting the changes.
 * 
 * The base ("old") text is defined by `attribute_alias` or `value` just like in any other value widget,
 * and the text to compare to (the "new" text) is set by `attribute_alias_to_compare` or `value_to_compare`
 * respectively.
 * 
 * ## Examples
 * 
 * ### Compare the current value of a text attribute with a backup
 * 
 * The following widget will compare the current value of the `DESCRIPTION` attribute
 * of some object with the latest backup of it assuming the backup object is accessible
 * via relation `BACKUP`.
 * 
 * Since the base value is the backup, any changes since the last backup will be highlighted
 * as "new".
 * 
 * ```
 *  {
 *      "widget_type": "DiffText",
 *      "attribute_alias": "BACKUP__DESCRIPTION:MAX_OF(CREATED_ON)",
 *      "attribute_alias_to_compare": "DESCRIPTION"
 *  }
 * ```
 * 
 * @author Andrej Kabachnik
 *        
 */
class DiffText extends Value
{
    private $compareToAttributeAlias = null;

    private $compareToValue = null;
    
    private $comparetToExpr = null;

    private string $layout;

    const VALUE_TO_COMPARE_ALIAS = "value_to_compare";

    const DIFF_CLASS = "difftext-diff";

    const LAYOUT_OPTIONS = array(
        "left_old_right_diff", // Default
        "left_new_right_diff",
        "left_diff_right_new",
        "left_diff_right_old",
    );

    /**
     * @return string
     */
    public function getLayout(): string
    {
        if(!in_array($this->layout, self::LAYOUT_OPTIONS)) {
            $this->layout = self::LAYOUT_OPTIONS[0];
        }

        return $this->layout;
    }

    /**
     * Returns the layout options as key-value pairs.
     *
     * Pairs have the format:
     * - `["position"] => ["content"]`
     * - for example `["left"] => ["old"]`
     *
     * @return array
     */
    public function getLayoutArray(): array
    {
        $result = array();
        $components = explode("_", $this->getLayout());
        for($i = 0; $i < count($components) - 1; $i+=2) {
            $result[$components[$i]] = $components[$i+1];
        }

        return $result;
    }

    /**
     * Select one of four possible layouts:
     *
     * - `left_old_right_diff`: Render original ('value') on the left and changes on the right.
     * - `left_new_right_diff`: Render revision ('value_to_compare') on the left and changes on the right.
     * - `left_diff_right_old`: Render changes on the left and original ('value') on the right.
     * - `left_diff_right_new`: Render changes on the left and revision ('value_to_compare') on the right.
     *
     * NOTE: Rendering changes means that all changes from 'value' to 'value_to_compare' are displayed in one document.
     *
     * @uxon-property layout
     * @uxon-type [left_old_right_diff,left_new_right_diff,left_diff_right_old,left_diff_right_new]
     * @uxon-default left_old_right_diff
     *
     * @param string $layout
     * @return Object
     */
    public function setLayout(string $layout) : Object
    {
        $layout = strtolower($layout);
        if(in_array($layout, self::LAYOUT_OPTIONS)) {
            $this->layout = $layout;
        }

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getAttributeAliasToCompare() : ?string
    {
        return $this->compareToAttributeAlias;
    }
    
    /**
     *
     * @return bool
     */
    public function isValueToCompareBoundToAttribute() : bool
    {
        return $this->compareToAttributeAlias !== null;
    }
    
    /**
     *
     * @return bool
     */
    public function isValueToCompareBoundByReference() : bool
    {
        return ! $this->isValueToCompareBoundToAttribute() && $this->getValueToCompareExpression() && $this->getValueToCompareExpression()->isReference();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getValueToCompareDataColumnName()
    {
        return $this->isValueToCompareBoundToAttribute() ? DataColumn::sanitizeColumnName($this->getAttributeAliasToCompare()) : $this->getDataColumnName();
    }
    
    /**
     * Alias of the attribute containing the configuration for the form to be rendered
     *
     * @uxon-property attribute_alias_to_compare
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return DiffText
     */
    public function setAttributeAliasToCompare(string $value) : DiffText
    {
        $this->compareToAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getValueToCompare() : ?string
    {
        return $this->compareToValue;
    }
    
    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getValueToCompareExpression() : ?ExpressionInterface
    {
        if ($this->compareToExpr === null) {
            if ($this->isValueToCompareBoundToAttribute()) {
                $this->compareToExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->getAttributeAliasToCompare());
            }
            if ($this->compareToValue !== null && Expression::detectCalculation($this->compareToValue)) {
                $this->compareToExpr = ExpressionFactory::createForObject($this->getMetaObject(), $this->compareToValue);
            }
        }
        return $this->compareToExpr;
    }
    
    /**
     * Widget link or static value for the form configuration
     *
     * @uxon-property value_to_compare
     * @uxon-type metamodel:widget_link|string
     *
     * @param string $value
     * @return DiffText
     */
    public function setValueToCompare(string $value) : DiffText
    {
        $this->compareToValue = $value;
        $this->compareToExpr = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill($data_sheet)
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        
        if ($this->isValueToCompareBoundToAttribute() === true) {
            if (null !== $expr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getAttributeAliasToCompare())) {
                $this->doPrefillForExpression($data_sheet, $expr, self::VALUE_TO_COMPARE_ALIAS, function($value){
                    $this->setValueToCompare($value ?? '');
                });
            }
        }
        
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        if ($this->isValueToCompareBoundToAttribute() === true) {
            $expr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getAttributeAliasToCompare());
            if ($expr!== null) {
                $data_sheet->getColumns()->addFromExpression($expr);
            }
        }
        
        return $data_sheet;
    }
    
    /**
     * @deprecated use setValue() instead
     * @param string $value
     * @return DiffText
     */
    protected function setLeftValue($value) : DiffText
    {
        return $this->setValue($value);
    }
    
    /**
     * @deprecated use setValueToCompare() instead
     * @param string $value
     * @return DiffText
     */
    protected function setRightValue($value) : DiffText
    {
        return $this->setValueToCompare($value);
    }
    
    /**
     * @deprecated use setAttributeAlias() instead
     * @param string $value
     * @return DiffText
     */
    protected function setLeftAttributeAlias($value) : DiffText
    {
        return $this->setAttributeAlias($value);
    }
    
    /**
     * @deprecated use setAttributeAliasToCompare() instead
     * @param string $value
     * @return DiffText
     */
    protected function setRightAttributeAlias($value) : DiffText
    {
        return $this->setAttributeAliasToCompare($value);
    }
}