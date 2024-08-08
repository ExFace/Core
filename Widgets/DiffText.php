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

    private string $versionToRender = "diff_new";

    const VALUE_TO_COMPARE_ALIAS = "value_to_compare";

    const DIFF_CLASS = "difftext-diff";

    const RENDER_OPTIONS = array(
        "old",
        "new",
        "diff"
    );

    /**
     * @return string
     */
    public function getVersionToRender(): string
    {
        return $this->versionToRender;
    }

    /**
     * Selects which document version to render
     *
     * - **old**: Render original revision, without changes.
     * - **new**: Render current revision, without changes.
     * - **diff**: Render changes.
     *
     * @uxon-property version_to_render
     * @uxon-type [old,new,diff]
     * @uxon-default diff
     *
     * @param string $versionToRender
     * @return void
     */
    public function setVersionToRender(string $versionToRender)
    {
        $versionToRender = strtolower($versionToRender);
        if(in_array($versionToRender, self::RENDER_OPTIONS)) {
            $this->versionToRender = $versionToRender;
        }
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