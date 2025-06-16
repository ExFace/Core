<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;

/**
 * Fills placehodlers with values from each data row - e.g. `~datarow:ATTRIBUTE` or `~datarow:=Formula()`.
 * 
 * This fills placeholders with data from a single data sheet row. The default placeholder prefix is
 * `~datarow:`, but it may be different in specific scenarios - please refer to the documentation of
 * the action or behavior, that handles the templates. Assuming, the prefix is `~datarow:`, the following
 * placeholder type would be available:
 * 
 * - `~datarow:MY_ATTRIBUTE` - resolves to the value of the data sheet column with an attribute. The
 * attribute must be present in the data sheet of course.
 * - `~datarow:MY_COLUMN_NAME` - explicitly references a column by its name - just in case the name
 * differs from the attribute alias
 * - `~datarow:=Formula()` - a formula, that will be resolved with data from this row
 *
 * @author Andrej Kabachnik
 */
class DataRowPlaceholders extends AbstractPlaceholderResolver
{
    use SanitizedPlaceholderTrait;
    
    /**
     * 
     * @var DataSheetInterface
     */
    private $dataSheet = null;
    
    private $rowNumber = 0;
    
    private $formatValues = true;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(DataSheetInterface $dataSheet, int $rowNumber, string $prefix = '~datarow:')
    {
        $this->setPrefix($prefix);
        $this->dataSheet = $dataSheet;
        $this->rowNumber = $rowNumber;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $phVals = [];
        $phs = $this->filterPlaceholders($placeholders);
        $phSheet = DataSheetFactory::createFromObject($this->dataSheet->getMetaObject());
        $needExtraData = false;
        foreach ($phs as $ph) {
            $expr = $this->stripPrefix($ph);
            $phSheet->getColumns()->addFromExpression($expr);
            if ($needExtraData === false && ! $this->dataSheet->getColumns()->getByExpression($expr)) {
                $needExtraData = true;
            }
        }
        // TODO #DataCollector needs to be used here instead of all the following logic
        if ($needExtraData === true && $this->dataSheet->hasUidColumn()) {
            if ($this->dataSheet->getUidColumn()->hasEmptyValues()) {
                throw new DataSheetMissingRequiredValueError($this->dataSheet, null, null, null, $this->dataSheet->getUidColumn(), $this->dataSheet->getUidColumn()->findEmptyRows());
            }
            $uidCol = $this->dataSheet->getUidColumn();
            $phSheet->getFilters()->addConditionFromExpression($uidCol->getExpressionObj(), $uidCol->getValue($this->rowNumber));
            $phSheet->dataRead();
            // Overwrite freshly read values by those in the input data (in case they were not saved yet)
            $phSheet->importRows($this->dataSheet->copy()->removeRows()->addRow($this->dataSheet->getRow($this->rowNumber)), false);
            $phRowNo = 0;
        } else {
            $phSheet = $this->dataSheet;
            $phRowNo = $this->rowNumber;
        }
        
        foreach ($phs as $ph) {
            $col = $phSheet->getColumns()->getByExpression($this->stripPrefix($ph));
            if ($col == false) {
                throw new DataSheetColumnNotFoundError($phSheet, "Column to replace placeholder '{$ph}' not found in data sheet and it could not be loaded automatically.");
            }
            $val = $col->getValue($phRowNo);
            // do not format aggregated values as the value type of the column and the value itself might conflict
            if ($col->hasAggregator()) {
                $formatted = $val;
            } else {
                $formatted = $this->isFormattingValues() ? $col->getDataType()->format($val) : $val;
            }
            $phVals[$ph] = $this->sanitizeValue($formatted);
        }
        
        return $phVals;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isFormattingValues() : bool
    {
        return $this->formatValues;
    }
    
    /**
     * Set to FALSE to use raw values instead formatting according to their data type.
     * 
     * @param bool $value
     * @return DataRowPlaceholders
     */
    public function setFormatValues(bool $value) : DataRowPlaceholders
    {
        $this->formatValues = $value;
        return $this;
    }
}