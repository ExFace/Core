<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * Base class for deferred actions, where the actual logic is executed after the handle() method returns a result.
 * 
 * Deferred actions return a result object without actually executing their logic. Instead the logic is
 * preconfigured and incapsulated inside the result in the form of a callable or generator. Once the facade
 * attempt to output the result, it triggers the execution of the logic, thus allowing to the facade to
 * react to it: e.g. to stream output of a generator bit-by-bit.
 * 
 * This class makes sure, the default action postprocessing (i.e. firing the `OnActionPerformedEvent`), that
 * is normally automatically triggered by calling `performAfter()` inside the `handle()` method, is skipped. 
 * Instead, you must perform it by manually by calling `performAfterDeferred()` at the end of the actual action
 * logic.
 * 
 * IMPORTANT: make sure to invoke `performAfterDeferred()` whenever the action is finished successfully (not
 * throwing the exception) and be sure to call it only once. Failing to do so may result in unforseeable
 * behavior!
 * 
 * Here is an example for a deferred action: 
 * 
 * ```
 * use exface\Core\CommonLogic\AbstractActionDeferred;
 * use exface\Core\CommonLogic\Tasks\ResultMessageStream;
 *  
 * class GenerateNumbers extends AbstractActionDeferred
 * {
 *  protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
 *  {
 *      $result = new ResultMessageStream($task);
 *      $generator = function() use ($result, $transaction) {
 *          // replace with some heavy logic
 *          for ($i = 1; $i <= 5; $i++) {
 *              yield $i . '...';
 *          }
 *          sleep(1);
 *      };
 *      $result->setMessageStreamGenerator($generator);
 *      
 *      // IMPORTANT: don't forget to trigger the postprocessing!!!
 *      $this->performAfterDeferred($result, $transaction);
 *      
 *      return $result;
 *  }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractActionDeferred extends AbstractAction
{
    /**
     * Deferred action rely on the implementation to call `performAfterDeferred()` after the action
     * actually finishes it's business.
     * 
     * @see \exface\Core\CommonLogic\AbstractAction::performAfter($result, $transaction)
     */
    protected final function performAfter(ResultInterface $result, DataTransactionInterface $transaction) : ActionInterface
    {
        return $this;
    }
    
    /**
     * The deferred version of performAfter().
     * 
     * @param ResultInterface $result
     * @param DataTransactionInterface $transaction
     * @return ActionInterface
     */
    protected function performAfterDeferred(ResultInterface $result, DataTransactionInterface $transaction) : ActionInterface
    {
        return parent::performAfter($result, $transaction);
    }
}