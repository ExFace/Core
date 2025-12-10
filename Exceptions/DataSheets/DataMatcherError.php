<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSheets\DataMatchInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Exceptions\DataMatcherExceptionInterface;

/**
 * Exception thrown if a data matcher encounters an error.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMatcherError extends RuntimeException implements DataMatcherExceptionInterface
{
    private DataMatcherInterface $matcher;
    private ?DataMatchInterface $match = null;
    private ?DataLogBookInterface $logbook = null;

    /**
     *
     * @param DataMatcherInterface $matcher
     * @param string $message
     * @param string|null $alias
     * @param string|null $previous
     * @param DataLogBookInterface|null $logbook
     * @param DataMatchInterface|null $match
     */
    public function __construct(DataMatcherInterface $matcher, $message, $alias = null, $previous = null, ?DataLogBookInterface $logbook = null, ?DataMatchInterface $match = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->matcher = $matcher;
        $this->match = $match;
        $this->logbook = $logbook;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see DataMatcherExceptionInterface::getMatcher()
     */
    public function getMatcher() : DataMatcherInterface
    {
        return $this->matcher;
    }
    
    
    public function getMatch() : ?DataMatchInterface
    {
        return $this->match;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see DataMatcherExceptionInterface::getLogbook()
     */
    public function getLogbook() : ?DataLogBookInterface
    {
        return $this->logbook;
    }
}