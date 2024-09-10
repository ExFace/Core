<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\TemplateRenderer\TemplateRendererRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 * TODO 
 * 
 * Each placeholder must contain a valid expression:
 * 
 * - An attribute alias with an aggregator
 * - A formula, that only uses aggregated values
 * 
 * ## Examples
 * 
 * TODO
 * 
 * @author Georg Bieger
 */
class DataAggregationPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;

    private ?DataSheetInterface $dataSheet = null;

    private ?string $prefix = null;

    /**
     *
     * @param DataSheetInterface $dataSheet
     * @param string $prefix
     */
    public function __construct(DataSheetInterface $dataSheet, string $prefix = "~data:")
    {
        $this->prefix = $prefix;
        $this->dataSheet = $dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $result = [];
        $aggrPlaceholders = $this->filterPlaceholders($placeholders, $this->prefix);

        if (empty($aggrPlaceholders)) {
            return [];
        }

        $inputSheet = $this->dataSheet;
        $aggrSheet = DataSheetFactory::createFromObject($inputSheet->getMetaObject());
        $aggrSheet->setFilters($inputSheet->getFilters());
        $phValsEmpty = [];
        $phCols = [];
        foreach ($aggrPlaceholders as $ph) {
            $phValsEmpty[$ph] = '';
            $exprString = $this->stripPrefix($ph, $this->prefix);
            $col = $aggrSheet->getColumns()->addFromExpression($exprString);
            $phCols[$ph] = $col;
            if ($col->isAttribute() && $col->hasAggregator() === false) {
                throw new DataSheetRuntimeError($aggrSheet, 'Cannot use placeholder "' . $ph . '" in template: only aggregated expressions like "ATTRIBUTE:MAX" allowed!');
            }
        }
        $aggrSheet->dataRead();

        switch ($aggrSheet->countRows() > 1) {
            case 0: return $phValsEmpty;
            case 1: 
                $result = [];
                foreach ($phCols as $ph => $col) {
                    $result[$ph] = $col->getValue(0);
                }
                break;
            default:
                throw new DataSheetRuntimeError($aggrSheet, 'TODO Cannot aggregate placeholders!');
        }

        return $result;
    }
}