<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;

/**
 * Resolves placeholders to facade propertis: `~facade:property`.
 * 
 * Technically this resolver calls the getter method of the property - e.g.
 * `~facade:theme` is resolved by calling `getTheme()` on the facade.
 *
 * @author Andrej Kabachnik
 */
class DataRowPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    use SanitizedPlaceholderTrait;
    
    private $prefix = null;
    
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
        $this->prefix = $prefix;
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
        $phs = $this->filterPlaceholders($placeholders, $this->prefix);
        $phSheet = DataSheetFactory::createFromObject($this->dataSheet->getMetaObject());
        $needExtraData = false;
        foreach ($phs as $ph) {
            $expr = $this->stripPrefix($ph, $this->prefix);
            $phSheet->getColumns()->addFromExpression($expr);
            if (! $this->dataSheet->getColumns()->getByExpression($expr)) {
                $needExtraData = true;
            }
        }
        
        if ($needExtraData === true && $this->dataSheet->hasUidColumn()) {
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
            $col = $phSheet->getColumns()->getByExpression($this->stripPrefix($ph, $this->prefix));
            if ($col == false) {
                throw new DataSheetColumnNotFoundError($phSheet, "Column to replace placeholder '{$ph}' not found in data sheet and it could not be loaded automatically.");
            }
            $val = $col->getValue($phRowNo);
            $formatted = $this->isFormattingValues() ? $col->getDataType()->format($val) : $val;
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