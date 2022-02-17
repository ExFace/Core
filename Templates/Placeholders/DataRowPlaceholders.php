<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;

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
            $phSheet->importRows($this->dataSheet->copy()->removeRows()->addRow($this->dataSheet->getRow($this->rowNumber)));
        } else {
            $phSheet = $this->dataSheet;
        }
        
        foreach ($phs as $ph) {
            $col = $phSheet->getColumns()->getByExpression($this->stripPrefix($ph, $this->prefix));
            $val = $col->getValue($this->rowNumber);
            $phVals[$ph] = $this->isFormattingValues() ? $col->getDataType()->format($val) : $val;
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