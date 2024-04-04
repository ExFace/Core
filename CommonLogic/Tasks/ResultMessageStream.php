<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Task result for actions producing multiple messages as a stream: e.g. complex CLI processes.
 * 
 * This type of task result makes it possible to stream output of long-running actions to different
 * facades. The action simply incapsulates it's output logic in a generator callable and passes this 
 * callable to `setMessageStreamGenerator()`. The facade, that will stream the output will
 * then iterate over the generator and process it's output bit-by-bit.
 * 
 * IMPORTANT: keep in mind, that the logic within the generator will be performed much later than
 * normally - right at the moment of generating facade output. If that logic is what the action actually
 * does (which is mostly the case!) you will end up with a deferred action, that is actually performed
 * asynchronously to it's handle() method. If you extend from the `AbstractAction`, you will need to take 
 * special care of that - otherwise, the action postprocessors bound to the `OnActionPerformedEvent` will 
 * be triggered immediately after the task result was created and before it's generator was run! Same 
 * goes to the action's autocommit: the generator code will be run after the transaction was committed!
 * 
 * To deal with this problem, use `AbstractActionDeferred` instead of `AbstractAction` as your base class.
 * Refer to the class description for details instructioins.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultMessageStream extends ResultMessage implements ResultMessageStreamInterface
{
    private $generatorCallable;
    
    private $generatorArgs = [];
    
    private $generatorWasRun = false;
    
    private $generatorResult = null;
    
    /**
     * 
     * {@inheritdoc}
     * @see ResultMessageStreamInterface::getMessageStreamGenerator()
     */
    public function getMessageStreamGenerator() : \Traversable
    {
        if ($this->generatorWasRun === true) {
            throw new LogicException('Cannot run the generator for a message stream multiple times! Use ResultMessageStream::getMessage() instead?');
        }
        
        if ($this->generatorCallable === null) {
            return new \EmptyIterator();
        }
        
        $this->generatorWasRun = true;
        return call_user_func_array($this->generatorCallable, $this->generatorArgs);
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see ResultMessageStreamInterface::setMessageStreamGenerator()
     */
    public function setMessageStreamGenerator(callable $closure, array $arguments = []) : ResultMessageStreamInterface
    {
        $this->generatorCallable = $closure;
        $this->generatorArgs = $arguments;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return $this->generatorCallable === null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::getMessage()
     */
    public function getMessage() : string
    {
        $message = parent::getMessage();
        if ($this->generatorWasRun === false) {
            foreach ($this->getMessageStreamGenerator() as $line) {
                $message .= $line . "\n";
            }
            $this->setMessage($message);
        }
        return $message;
    }
}