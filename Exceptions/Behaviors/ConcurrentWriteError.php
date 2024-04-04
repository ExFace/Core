<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Factories\DataSheetFactory;

/**
 * Exception thrown a concurrent write attemt (racing condition) is detected.
 *
 * @author Andrej Kabachnik
 *        
 */
class ConcurrentWriteError extends BehaviorRuntimeError implements DataSheetExceptionInterface
{
    private $dataSheet = null;
    
    public function __construct(BehaviorInterface $behavior, $message, $alias = null, $previous = null, LogBookInterface $logbook = null, DataSheetInterface $dataSheet = null)
    {
        parent::__construct($behavior, $message, $alias, $previous, $logbook);
        $this->dataSheet = $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6T6HZLF';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface::getDataSheet()
     */
    public function getDataSheet(): DataSheetInterface
    {
        if ($this->dataSheet === null) {
            if ($this->getLogbook() instanceof DataLogBookInterface) {
                $sheets = $this->getLogbook()->getDataSheets();
                if (! empty($sheets)) {
                    return $sheets[array_key_first($sheets)];
                }
            }
            $this->dataSheet = DataSheetFactory::createFromObject($this->getBehavior()->getObject());
        }
        return $this->dataSheet;
    }
}
