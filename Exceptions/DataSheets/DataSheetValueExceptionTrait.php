<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Exceptions\DataSheetValueExceptionInterface;

/**
 * Trait to help implement DataSheetValueExceptionInterface
 *
 * @see DataSheetValueExceptionInterface
 * @author Andrej Kabachnik
 *        
 */
trait DataSheetValueExceptionTrait
{
    /**
     * 
     * @param DataColumnInterface $col
     * @param array $rowNumbers
     * @return string|NULL
     */
    protected function generateMessageForColumn(DataColumnInterface $col, array $rowNumbers = null) : ?string
    {
        $colCaption = $col->getAttribute()->getName();
        if ($rowNumbers !== null && $col->getDataSheet()->countRows() > 1) {
            $rowNoList = implode(', ', $rowNumbers);
            try {
                $message = $col->getWorkbench()->getCoreApp()->getTranslator()->translate(
                    'DATASHEET.ERROR.INVALID_VALUES_ON_ROWS', 
                    [
                        '%object%'=> $col->getMetaObject()->getName(), 
                        '%column%' => $colCaption, 
                        '%rows%' => $rowNoList
                    ]
                );
            } catch (\Throwable $e) {
                $col->getWorkbench()->getLogger()->logException($e);
                $message = 'Invalid values for "' . $colCaption . '" on row(s) ' . $rowNoList;
            }
        } else {
            try {
                $message = $col->getWorkbench()->getCoreApp()->getTranslator()->translate(
                    'DATASHEET.ERROR.INVALID_VALUES', 
                    [
                        '%object%'=> $col->getMetaObject()->getName(), 
                        '%column%' => $colCaption
                    ]
                );
            } catch (\Throwable $e) {
                $col->getWorkbench()->getLogger()->logException($e);
                $message = 'Invalid values for "' . $colCaption . '"';
            }
        }
        
        if (null !== $msg = $col->getDataType()->getValidationErrorMessage()) {
            $message = StringDataType::endSentence($message) . ' ' . $msg->getTitle();
        }
        
        return $message;
    }

    /**
     * {@inheritDoc}
     * @see DataSheetValueExceptionInterface::getRowIndexes()
     */
    public abstract function getRowIndexes() : ?array;
    
    /**
     * {@inheritDoc}
     * @see DataSheetValueExceptionInterface::getRowNumbers()
     */
    public function getRowNumbers() : ?array
    {
        $idxs = $this->getRowIndexes();
        return $idxs === null ? null : array_map(function(int $rowIdx){ return $rowIdx + 1; }, $idxs);
    }
}