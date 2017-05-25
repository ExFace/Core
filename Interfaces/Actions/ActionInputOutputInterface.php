<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface ActionInputOutputInterface extends iCanBeConvertedToUxon
{

    /**
     *
     * @return DataSheetInterface
     */
    public function getDataSheet();

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @return ActionInputOutputInterface
     */
    public function setDataSheet(DataSheetInterface $data_sheet);

    public function addMessage($string);

    public function getMessages();

    public function printMessages();

    public function printOutput();
}
?>