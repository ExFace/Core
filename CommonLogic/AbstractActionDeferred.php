<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;

/**
 * Base class for deferred actions - where the actual logic is executed after the handle() method returns a result.
 * 
 * Deferred action are often used as CLI commands because they allow streaming the results line-by-line
 * in real time. A deferred action return a result object without actually executing its logic. Instead 
 * the logic is incapsulated in a generator which is passed to the result object. The latter triggers
 * the logic once its content is required for the first time via `getMessage()` or similar. This allows
 * facades to react to every generated part of the result: e.g. to stream output of a generator line-by-line.
 * 
 * This class provides two distinct abstract methods instead of the regular `AbstractAction::perform()`:
 * 
 * - `performImmediately()` allows to execute some logic at the same time as `AbstractAction::perform()`
 * would do. You can use this for preparation, reading parameters, etc. if this logic relies on some state
 * that may change in future. The method returns a simple array, which will be used as arguments for 
 * calling `perfromDeferred()` later on. This way you can pass results of the preparation logic.
 * - `performDeferred()` should contain the actual business logic. Will will be triggered once the
 * result is rendered for the first time. It MUST return a generator.
 * 
 * This class makes sure, the default action postprocessing (i.e. firing the `OnActionPerformedEvent`), 
 * that is normally automatically triggered by calling `AbstractAction::performAfter()` inside the 
 * `AbstractAction::handle()` method, is postponed until `performDeferred()` finishes. 
 * 
 * **NOTE:** You can define any number and type of parameters for `perforDeferred()` according to its
 * inner logic, however they all MUST be optional! Otherwise you will get a compilation error in PHP.
 * This seems to be a limitation of abstract methods... 
 *  
 * Here is an example for a deferred action: first some commands are initialized in `performImmediately()`, 
 * then they are executed in `performDeferred()`.
 * 
 * ```
 * class GenerateNumbers extends \exface\Core\CommonLogic\AbstractActionDeferred
 * {
 *  protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
 *  {
 *      $commands = ['Do 1', 'Do 2', 'Do 3'];
 *      return [$commands];
 *  }
 *  protected function performDeferred(array $commands = []) : \Generator
 *  {
 *      foreach ($commands as $cmd) {
 *          yield $cmd;
 *          // Some heavy logic here!
 *          sleep 1;
 *      }
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
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected final function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $result = new ResultMessageStream($task);
        $result->setMessageStreamGenerator(
            function($callbackArgs) use ($result, $transaction) {
                
                yield from call_user_func_array([$this, 'performDeferred'], $callbackArgs);
                
                // IMPORTANT: don't forget to trigger the postprocessing!!!
                $this->performAfterDeferred($result, $transaction);
                
            }, 
            [
                $this->performImmediately($task, $transaction, $result)
            ]
        );
        return $result;
    }
    
    /**
     * Perparation logic returning arguments for `performDeferred()` as an array.
     * 
     * This method is executed within `AbstractAction::perform()`. It allows to prepare
     * and save things that might change until `performDeferred()` is actually called.
     * For example, you could do `return [$task]` to make sure the $task is still
     * available in the deferred logic.
     * 
     * If you don't need it - just `return []`
     * 
     * @param TaskInterface $task
     * @param DataTransactionInterface $transaction
     * @param ResultMessageStreamInterface $result
     * 
     * @return mixed[]
     */
    protected abstract function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array;
    
    /**
     * The actual business logic goes here - it will be executed whenever the actions result will be rendered.
     * 
     * **NOTE:** You can define any number and type of parameters for `perforDeferred()` according to its
     * inner logic, however they all MUST be optional! Otherwise you will get a compilation error in PHP.
     * This seems to be a limitation of abstract methods... 
     * 
     * @return \Generator
     */
    protected abstract function performDeferred() : \Generator;
    
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