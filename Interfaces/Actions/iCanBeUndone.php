<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\CommonLogic\UxonObject;

interface iCanBeUndone extends ActionInterface
{

    /**
     * Performs the actual undo operation.
     * One can say, it is the opposite of perform().
     * In most cases undo() will use undo data from the context history.
     *
     * @return DataSheetInterface
     */
    public function undo(DataTransactionInterface $transaction) : DataSheetInterface;

    /**
     * Returns a serializable UXON object with all the data neede to perform an undo operation later.
     * The output of this method will be saved in the context history.
     *
     * @return UxonObject
     */
    public function getUndoDataUxon();

    /**
     * Imports the undo data from get_undo_data_serializable(), that is saved in the context history, back
     * to an action instance.
     * This method should work with whatever get_undo_data_serializable() returns for
     * the same action.
     *
     * @param UxonObject $uxon_object   
     * @return iCanBeUndone         
     */
    public function setUndoData(UxonObject $uxon_object) : iCanBeUndone;

    /**
     *
     * @see ActionInterface::isUndoable()
     */
    public function isUndoable() : bool;

    /**
     *
     * @return ActionInterface
     */
    public function getUndoAction() : ActionInterface;
}