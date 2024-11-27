<?php

namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * This type of error is thrown whenever a data check encounters a runtime issue that prevents it
 * from performing correctly.
 */
class DataCheckRuntimeError 
    extends \UnexpectedValueException 
    implements DataCheckExceptionInterface
{
    use DataSheetExceptionTrait;

    private ?DataCheckInterface $check = null;
    private ?DataSheetInterface $badData = null;

    /**
     *
     * @param DataSheetInterface      $data_sheet
     * @param string                  $message
     * @param null                    $alias
     * @param null                    $previous
     * @param DataCheckInterface|null $check
     * @param DataSheetInterface|null $badData
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
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias() : string
    {
        return '7L98V2P';
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\UnexpectedValueException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel() : string
    {
        return LoggerInterface::ERROR;
    }
}