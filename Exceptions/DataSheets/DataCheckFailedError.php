<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\Interfaces\Exceptions\DataSheetValueExceptionInterface;
use exface\Core\Interfaces\Model\MessageInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Exception thrown if a check is violated by data sheet contents.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCheckFailedError extends UnexpectedValueException implements DataCheckExceptionInterface
{
    use DataSheetExceptionTrait;
    use DataSheetValueExceptionTrait;
    
    private $check = null;
    
    private $badRowIndexes = null;
    
    /**
     *
     * @param DataSheetInterface $data_sheet
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
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
     * {@inheritDoc}
     * @see ExceptionTrait::getMessageModel()
     */
    public function getMessageModel(WorkbenchInterface $workbench): MessageInterface
    {
        $message = parent::getMessageModel($workbench);
        if (! empty($this->badRowIndexes) && $this->getUseExceptionMessageAsTitle() === true) {
            if ($this->getDataSheet()->countRows() > 1) {
                $title = $message->getTitle();
                $title = StringDataType::endSentence($title) . ' ' . $workbench->getCoreApp()->getTranslator()->translate('DATASHEET.ERROR.AFFECTED_ROWS', [
                        '%row_numbers%' => implode(', ', $this->getRowNumbers())
                    ]);
                $message->setTitle($title);
            }
        }
        return $message;
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
}