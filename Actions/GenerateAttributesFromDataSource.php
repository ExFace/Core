<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\Actions\ActionInputTypeError;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action runs one or more selected test steps
 *
 * @author Andrej Kabachnik
 *        
 */
class GenerateAttributesFromDataSource extends AbstractAction
{

    protected function init()
    {
        $this->setIconName(Icons::COGS);
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
    }

    protected function perform()
    {
        if (! $this->getInputDataSheet()->getMetaObject()->is('exface.Core.OBJECT')) {
            throw new ActionInputInvalidObjectError($this, 'Action "' . $this->getAlias() . '" exprects an exface.Core.OBJECT as input, "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        
        $result_data_sheet = $this->getWorkbench()->data()->createDataSheet($this->getWorkbench()->model()->getObject('exface.Core.ATTRIBUTE'));
        $skipped_columns = 0;
        foreach ($this->getInputDataSheet()->getRows() as $input_row) {
            /* @var $target_obj \exface\Core\CommonLogic\Model\Object */
            $target_obj = $this->getWorkbench()->model()->getObject($input_row[$this->getInputDataSheet()->getUidColumn()->getName()]);
            $target_obj_connection = $target_obj->getDataConnection();
            if (! ($target_obj_connection instanceof SqlDataConnectorInterface)) {
                throw new ActionInputTypeError($this, 'Cannot create attributes from SQL table for data connection "' . $target_obj_connection->getAliasWithNamespace() . '": only SQL connections supported (must implement the SqlDataConnectorInterface!).');
            }
            foreach ($target_obj_connection->getModelizer()->getAttributePropertiesFromTable($target_obj, $input_row['DATA_ADDRESS']) as $row) {
                if ($target_obj->findAttributesByDataAddress($row['DATA_ADDRESS'])) {
                    $skipped_columns ++;
                    continue;
                }
                $result_data_sheet->addRow($row);
            }
        }
        
        if (! $result_data_sheet->isEmpty()) {
            $result_data_sheet->dataCreate();
        }
        
        // Save the result and output a message for the user
        $this->setResultDataSheet($result_data_sheet);
        $this->setResult('');
        $this->setResultMessage('Created ' . $result_data_sheet->countRows() . ' attribute(s) for ' . $this->getInputDataSheet()->countRows() . ' object(s). ' . $skipped_columns . ' attributes skipped as duplicates!');
        
        return;
    }
}
?>