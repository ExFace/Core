<?php

namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * This type of error is thrown whenever a data check encounters a runtime issue that prevents it
 * from performing correctly.
 */
class DataCheckRuntimeError extends RuntimeException implements DataCheckExceptionInterface
{
    use DataSheetExceptionTrait;
    use DataSheetValueExceptionTrait;

    private ?DataCheckInterface $check = null;
    private $badRowIndexes = null;

    /**
     *
     * @param DataSheetInterface      $data_sheet
     * @param string                  $message
     * @param null                    $alias
     * @param null                    $previous
     * @param DataCheckInterface|null $check
     * @param DataSheetInterface|null $badData
     */
    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null, DataCheckInterface $check = null, array $badRowIndexes = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
        $this->check = $check;
        $this->badRowIndexes = $badRowIndexes;
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
        if ($this->badRowIndexes === null) {
            return null;
        }
        $badRows = $this->getDataSheet()->getRowsByIndex($this->badRowIndexes);
        return $this->getDataSheet()->copy()->removeRows()->addRows($badRows, false, false);
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

    /**
     * @inheritDoc
     * @see DataSheetValueExceptionInterface::getMessageTitleWithoutLocation()
     */
    public function getMessageTitleWithoutLocation(): string
    {
        return parent::getMessage();
    }

    /**
     * @inheritDoc
     * @see DataSheetValueExceptionInterface::getRowIndexes()
     */
    public function getRowIndexes(): ?array
    {
        return $this->badRowIndexes;
    }
}