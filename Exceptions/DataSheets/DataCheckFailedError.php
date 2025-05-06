<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\ExceptionWithValuesTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\Interfaces\Exceptions\ExceptionWithValuesInterface;

/**
 * Exception thrown if a check is violated by data sheet contents.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCheckFailedError 
    extends UnexpectedValueException 
    implements DataCheckExceptionInterface, ExceptionWithValuesInterface
{
    use DataSheetExceptionTrait;
    use ExceptionWithValuesTrait;
    
    private $check = null;
    
    private $badData = null;
    
    /**
     *
     * @param DataSheetInterface $data_sheet
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null, DataCheckInterface $check = null, DataSheetInterface $badData = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
        if ($check !== null) {
            $this->check = $check;
        }
        if ($badData !== null) {
            $this->badData = $badData;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface::getCheck()
     */
    public function getCheck() : ?DataCheckInterface
    {
        return $this->check;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface::getBadData()
     */
    public function getBadData() : ?DataSheetInterface
    {
        return $this->badData;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7L98V2P';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\UnexpectedValueException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return Logger::ERROR;
    }
}