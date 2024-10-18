<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

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
class DataAggregationPlaceholders extends AbstractPlaceholderResolver
{
    private ?DataSheetInterface $dataSheet = null;

    /**
     *
     * @param DataSheetInterface $dataSheet
     * @param string $prefix
     */
    public function __construct(DataSheetInterface $dataSheet, string $prefix = "~data:")
    {
        $this->prefix = $prefix ?? '';
        $this->dataSheet = $dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
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
            $expr = $col->getExpressionObj();
            if (! $expr->isMetaAttribute() && ! $expr->isFormula()) {
                throw new DataSheetRuntimeError($aggrSheet, 'Cannot use placeholder "' . $ph . '" in template: only aggregated attributes and formulas allowed!');
            }
            if ($col->isAttribute() && $col->hasAggregator() === false) {
                throw new DataSheetRuntimeError($aggrSheet, 'Cannot use placeholder "' . $ph . '" in template: only aggregated expressions like "ATTRIBUTE:MAX" allowed!');
            }
            $phCols[$ph] = $col;
        }

        $aggrSheet->dataRead();
        switch ($aggrSheet->countRows()) {
            case 0:
                return $phValsEmpty;
            case 1:
                $result = [];
                foreach ($phCols as $ph => $col) {
                    $result[$ph] = $col->getValue(0);
                }
                break;
            default:
                throw new DataSheetRuntimeError($aggrSheet, 'Could not aggregate placeholders!');
        }

        return $result;
    }
}