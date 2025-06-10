<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataCheckNotApplicableError;

/**
 * Interface for checks to validate data sheets
 * 
 * Each check basically defines an error and a condition group to identify this error.
 * If a given DataSheet matches the condition group, the error will be raised.
 * 
 * @author Andrej Kabachnik
 *        
 */
interface DataCheckInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface, \Stringable
{
    /**
     * Performs the check and returns a textual explanation for what has been checked or throws an exception if the check fails.
     *
     * @param DataSheetInterface $sheet
     * @param LogBookInterface|null $logBook
     *
     * @throws DataCheckFailedError
     * @throws DataCheckNotApplicableError
     *
     * @return string
     */
    public function check(DataSheetInterface $sheet, LogBookInterface $logBook = null) : string;
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isViolatedIn(DataSheetInterface $data, ?LogBookInterface $logBook = null) : bool;
    
    /**
     * 
     * @param DataSheetInterface $data
     * 
     * @throws DataCheckFailedError
     * @throws DataCheckNotApplicableError
     * 
     * @return int[]
     */
    public function findViolations(DataSheetInterface $data, ?LogBookInterface $logBook = null) : array;
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isApplicable(DataSheetInterface $data) : bool;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return bool
     */
    public function isApplicableToObject(MetaObjectInterface $object) : bool;
    
    /**
     * 
     * @param MetaObjectInterface $baseObject
     * @return ConditionGroupInterface
     */
    public function getConditionGroup(MetaObjectInterface $baseObject = null) : ConditionGroupInterface;
}