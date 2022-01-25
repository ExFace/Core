<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

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
        foreach ($phs as $ph) {
            $expr = $this->stripPrefix($ph, $this->prefix);
            if ($col = $this->dataSheet->getColumns()->getByExpression($expr)) {
                $phSheet->getColumns()->add($col->copy(), $col->getName());
            } else {
                $phSheet->getColumns()->addFromExpression($ph);
            }
        }
        
        if ($phSheet->isEmpty()) {
            return [];
        }
        
        if (! $phSheet->isFresh() && $this->dataSheet->hasUidColumn()) {
            $uidCol = $this->dataSheet->getUidColumn();
            $phSheet->getFilters()->addConditionFromExpression($uidCol->getExpressionObj(), $uidCol->getValue($this->rowNumber));
            $phSheet->dataRead();
            $valSheet = $phSheet;
        } else {
            $valSheet = $this->dataSheet;
        }
        
        foreach ($phs as $ph) {
            $col = $valSheet->getColumns()->getByExpression($this->stripPrefix($ph, $this->prefix));
            $val = $col->getValue($this->rowNumber);
            // TODO $val muss Datentyp-Spezifisch formattiert werden. DataType::formatValue()???
            $phVals[$ph] = $val;
        }
        
        return $phVals;
    }
}