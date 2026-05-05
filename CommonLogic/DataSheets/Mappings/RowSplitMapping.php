<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * Splits list values from one from-expression into multiple rows in the to-sheet.
 * 
 * This mapping works like `DataColumnMapping`, but expands each input row by splitting
 * the `from` value into parts and writing one output row per part.
 * 
 * ## Most important UXON properties
 * 
 * - `from` - expression in the from-sheet to read values from.
 * - `to` - expression in the to-sheet where each split part is written to.
 * - `if_missing_from_column` - behavior when `from` is missing (`error`, `ignore`, `use_default`).
 * - `default_value` - fallback expression for missing or empty `from` values.
 * 
 * ## Delimiter behavior
 *
 * - If `from` is a meta attribute, the attribute delimiter (`value_list_delimiter`) is used.
 * - Otherwise, the global default delimiter `EXF_LIST_SEPARATOR` is used.
 *
 * ## Simplest meaningful configuration
 *
 * ```
 * {
 *   "from": "TAGS",
 *   "to": "TAG"
 * }
 * 
 * ```
 * 
 * ## Examples
 * 
 * ```
 * {
 *   "from": "RELATED_IDS",
 *   "to": "RELATED_ID",
 *   "if_missing_from_column": "use_default",
 *   "default_value": "=''"
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 */
class RowSplitMapping extends DataColumnMapping
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr);
        $logbook?->addLine("Row split `{$fromExpr->__toString()}` -> `{$toExpr->__toString()}`.");

        switch (true) {
            case $fromExpr->isStatic():
                $fromValues = [$fromExpr->evaluate()];
                break;
            case $fromExpr->isFormula():
                $fromValues = $fromExpr->evaluate($fromSheet);
                break;
            case $this->useDefaultValue():
                $defaultExpr = $this->getDefaultExpression();
                $logbook->continueLine('Using default value `' . $defaultExpr->__toString() . '`.');
                if (! $fromCol) {
                    // From column is missing, add it with default values.
                    $fromCol = $fromSheet->getColumns()->addFromExpression($fromExpr);
                    $fromCol->setValues($defaultExpr->isStatic() ?
                        $defaultExpr->evaluate() :
                        $defaultExpr->evaluate($fromSheet)
                    );
                } else {
                    // From column is present, replace empty values with defaults.
                    $dataType = $fromCol->getDataType();
                    foreach ($fromCol->getValues() as $rowNr => $value) {
                        if (! $dataType::isValueEmpty($value)) {
                            continue;
                        }

                        $fromCol->setValue($rowNr, $defaultExpr->isStatic() ?
                            $defaultExpr->evaluate() :
                            $defaultExpr->evaluate($fromSheet, $rowNr)
                        );
                    }
                }
            case $fromCol:
                $fromValues = $fromCol->getValues(false);
                break;
            case $fromSheet->getColumns()->isEmpty() && ! $fromExpr->isReference():
                $logbook?->continueLine(' Not required because from-sheet is empty.');
                return $toSheet;
            case $this->getIgnoreIfMissingFromColumn() === true && ($fromExpr->isMetaAttribute() || $fromExpr->isFormula() || $fromExpr->isUnknownType()):
                $logbook?->continueLine(' Ignored because `ignore_if_missing_from_column` is `true` and no from-data was found.');
                return $toSheet;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->toString() . '" in a row-split mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() . ' has no UID attribute)!', '7H6M243');
                }
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->__toString() . '" as from-expression in a row-split mapping: only data column names, constants and formulas allowed!', '7H6M243');
        }

        if ($toSheet->isEmpty()) {
            if (empty($fromValues)) {
                return $toSheet;
            }
            $toRowsBefore = array_fill(0, count($fromValues), []);
        } else {
            $toRowsBefore = $toSheet->getRows();
        }

        if ($fromExpr->isStatic() && count($fromValues) === 1 && count($toRowsBefore) > 1) {
            $fromValues = array_fill(0, count($toRowsBefore), $fromValues[0]);
        }

        if (count($toRowsBefore) !== count($fromValues)) {
            throw new DataMappingFailedError(
                $this,
                $fromSheet,
                $toSheet,
                'Cannot split-map "' . $fromExpr->__toString() . '": from-values count (' . count($fromValues) . ') does not match target rows (' . count($toRowsBefore) . ')!',
            );
        }

        $toCol = $toSheet->getColumns()->addFromExpression($toExpr, null, $fromCol ? $fromCol->getHidden() : false);
        $delimiter = $this->getSplitDelimiter($fromExpr);
        $toRowsAfter = [];

        foreach ($toRowsBefore as $rowNr => $toRow) {
            foreach ($this->splitValue($fromValues[$rowNr], $fromExpr, $delimiter) as $partValue) {
                $toRow[$toCol->getName()] = $this->mapValue($partValue);
                $toRowsAfter[] = $toRow;
            }
        }

        $toSheet->removeRows()->addRows($toRowsAfter);

        if ($logbook !== null) {
            $logbook?->continueLine(' Split ' . count($toRowsBefore) . ' rows into ' . count($toRowsAfter) . ' rows using delimiter `' . $delimiter . '`.');
        }

        return $toSheet;
    }

    /**
     * @param mixed $value
     * @param ExpressionInterface $fromExpr
     * @param string $delimiter
     * @return array
     */
    protected function splitValue($value, ExpressionInterface $fromExpr, string $delimiter) : array
    {
        if ($value === null) {
            return [null];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if ($fromExpr->isMetaAttribute()) {
            return $fromExpr->getAttribute()->explodeValueList((string) $value);
        }

        if (! is_string($value)) {
            return [$value];
        }

        $parts = explode($delimiter, $value);
        return array_map('trim', $parts);
    }

    /**
     * @param ExpressionInterface $fromExpr
     * @return string
     */
    protected function getSplitDelimiter(ExpressionInterface $fromExpr) : string
    {
        if ($fromExpr->isMetaAttribute()) {
            return $fromExpr->getAttribute()->getValueListDelimiter();
        }

        return EXF_LIST_SEPARATOR;
    }
}