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
     * Makes sure, the given data sheet has all required data, eventually adding missing columns
     *
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    public function enrich(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataCollectorInterface;

    /**
     * Loads all required data into the collector eventually reading missing columns - but NOT modifying the given sheet
     *
     * After this method was called, you can use getRequiredData() and getRequiredColumns() to actually
     * access the values of all the required expressions.
     *
     * @param DataSheetInterface $dataSheet
     * @return DataCollectorInterface
     */
    public function collectFrom(DataSheetInterface $dataSheet) : DataCollectorInterface;

    /**
     * Returns a data sheet, that contains all the required data
     *
     * Depending on the method called to collect data, it may be the data sheet provided as base or a different
     * one created especially to load the data.
     *
     * @return DataSheetInterface
     */
    public function getRequiredData() : DataSheetInterface;

    /**
     * Returns an array of columns for required data in given data sheet with their expressions as keys
     *
     * @param bool $strict
     * @return DataColumnInterface[]
     */
    public function getRequiredColumns() : array;

    /**
     * @return ExpressionInterface[]
     */
    public function getRequiredExpressions() : array;

    /**
     * @param bool $trueOrFalse
     * @return DataCollectorInterface
     */
    public function setReadMissingData(bool $trueOrFalse) : DataCollectorInterface;

    /**
     * @param bool $trueOrFalse
     * @return DataCollectorInterface
     */
    public function setIgnoreUnreadableColumns(bool $trueOrFalse) : DataCollectorInterface;

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