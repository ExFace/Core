<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Exception thrown if a behavior experiences an error at runtime (e.g. not detectable at compile time).
 *
 * @author Andrej Kabachnik
 *        
 */
class BehaviorRuntimeError extends AbstractBehaviorException
{
    private $logbook = null;
    
    public function __construct(BehaviorInterface $behavior, $message, $alias = null, $previous = null, LogBookInterface $logbook = null)
    {
        parent::__construct($behavior, $message, $alias, $previous);
        $this->logbook = $logbook;
    }
    
    /**
     * 
     * @return LogBookInterface|NULL
     */
    public function getLogbook() : ?LogBookInterface
    {
        return $this->logbook;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = parent::createDebugWidget($error_message);
        $logbook = $this->getLogbook() ?? new BehaviorLogBook($this->getBehavior()->getAlias(), $this->getBehavior());
        return $logbook->createDebugWidget($error_message);
    }
}