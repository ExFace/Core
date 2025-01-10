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
     *
     * @param DataSheetInterface    $sheet
     * @param LogBookInterface|null $logBook
     * @return DataSheetInterface
     * @throws DataCheckFailedError
     * @throws DataCheckNotApplicableError
     */
    public function check(DataSheetInterface $sheet, LogBookInterface $logBook = null) : DataSheetInterface;
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isViolatedIn(DataSheetInterface $data) : bool;
    
    /**
     * 
     * @param DataSheetInterface $data
     * 
     * @throws DataCheckFailedError
     * @throws DataCheckNotApplicableError
     * 
     * @return DataSheetInterface
     */
    public function findViolations(DataSheetInterface $data) : DataSheetInterface;
    
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
     * @return string|NULL
     */
    public function getErrorText() : ?string;
    
    /**
     * 
     * @param MetaObjectInterface $baseObject
     * @return ConditionGroupInterface
     */
    public function getConditionGroup(MetaObjectInterface $baseObject = null) : ConditionGroupInterface;
}