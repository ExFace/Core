<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\ExfaceClassInterface;

/**
 * Common interface for all task results basically only returning a message and a code.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ResultInterface extends ExfaceClassInterface
{
    /**
     * 
     * @param TaskInterface $task
     */
    public function __construct(TaskInterface $task);

    /**
     * @return TaskInterface
     */
    public function getTask() : TaskInterface;    
    
    /**
     * 
     * @return string
     */
    public function getMessage() : string;
    
    /**
     * 
     * @param string $string
     * @return ResultInterface
     */
    public function setMessage(string $string) : ResultInterface;
    
    /**
     * 
     * @return bool
     */
    public function isUndoable() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return ResultInterface
     */
    public function setUndoable(bool $trueOrFalse) : ResultInterface;
    
    /**
     * Returns TRUE, if the action modifies data in a data source or FALSE otherwise.
     * By default all actions capable of modifying data return TRUE,
     * but the flag may change, if there had been no data actually modified while performing the action. Assuming TRUE if a data modification is
     * possible, makes sure, no modifications actually remains undiscovered because of developers forgetting to set the appropriate flag of an action.
     *
     * @return bool
     */
    public function isDataModified() : bool;
    
    /**
     *
     * @param bool $value
     * @return ResultInterface
     */
    public function setDataModified(bool $value) : ResultInterface;
    
    /**
     * 
     * @return bool
     */
    public function isContextModified() : bool;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return ResultInterface
     */
    public function setContextModified(bool $trueOrFalse) : ResultInterface;
    
    
    /**
     *
     * @return int|null
     */
    public function getResponseCode();
    
    /**
     *
     * @param int $number
     */
    public function setReponseCode(int $number) : ResultInterface;
    
    /**
     * Returns TRUE if the result does not contain any data.
     * 
     * NOTE: The result may still contain a meaningful response code.
     * 
     * @return bool
     */
    public function isEmpty() : bool;
}