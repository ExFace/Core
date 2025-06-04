<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\CommonLogic\DataSheets\DataSheetList;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;

/**
 * Allows to gather data expressions required for some process and collect that from a given data sheet
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataCollectorInterface extends WorkbenchDependantInterface
{
    /**
     * Returns a datasheet containing all required data: either the given one (if sufficient) or a new one
     *
     * This does NOT modify the original data sheet!
     *
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    public function collect(DataSheetInterface $dataSheet) : DataSheetInterface;

    /**
     * Same as collect(), but returns an array of the required columns
     *
     * @param DataSheetInterface $dataSheet
     * @param LogBookInterface|null $logBook
     * @return DataSheetInterface[]
     */
    public function collectColumns(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : array;

    /**
     * Makes sure, the given data sheet has all required data, eventually adding missing columns
     *
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    public function enrich(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataSheetInterface;

    /**
     * Reads the required data into a new empty data sheet and returns it
     *
     * @param DataSheetInterface $dataSheet
     * @param LogBookInterface|null $logBook
     * @return DataSheetInterface
     */
    public function readFor(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataSheetInterface;

    /**
     * Reads the required data into a new empty data sheet and returns an array of required columns with their expressions for keys
     *
     * @param DataSheetInterface $dataSheet
     * @param LogBookInterface|null $logBook
     * @return array
     */
    public function readColumnsFor(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : array;

    /**
     * Returns an array of columns for required data in given data sheet or NULL if not all columns are found
     *
     * @param DataSheetInterface $dataSheet
     * @return array|null
     */
    public function getRequiredColumns(DataSheetInterface $dataSheet) : ?array;

    /**
     * @return ExpressionInterface[]
     */
    public function getRequiredExpressions() : array;

    /**
     * @param ExpressionInterface|string|bool|int|float $expressionOrString
     * @return DataCollectorInterface
     */
    public function addExpression($expressionOrString) : DataCollectorInterface;

    /**
     * @param array $expressionsOrStrings
     * @return DataCollectorInterface
     */
    public function addExpressions(array $expressionsOrStrings) : DataCollectorInterface;

    /**
     * @param MetaAttributeInterface $attribute
     * @return DataCollectorInterface
     */
    public function addAttribute(MetaAttributeInterface $attribute) : DataCollectorInterface;

    /**
     * @param string $alias
     * @return DataCollectorInterface
     */
    public function addAttributeAlias(string $alias) : DataCollectorInterface;
}