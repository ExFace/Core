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
class GenerateModelFromDataSource extends AbstractAction
{

    protected function init()
    {
        $this->setIcon(Icons::COGS);
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
    }

    protected function perform()
    {
        if (! $this->getInputDataSheet()->getMetaObject()->is('exface.Core.OBJECT')) {
            throw new ActionInputInvalidObjectError($this, 'Action "' . $this->getAlias() . '" exprects an exface.Core.OBJECT as input, "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        
        $skipped = 0;
        $created = 0;
        $objects_sheet = $this->getInputDataSheet();
        foreach ($objects_sheet->getRows() as $objects_sheet_row) {
            /* @var $target_obj \exface\Core\Interfaces\Model\MetaObjectInterface */
            $target_obj = $this->getWorkbench()->model()->getObject($objects_sheet_row[$this->getInputDataSheet()->getUidColumn()->getName()]);
            $modelBuilder = $target_obj->getDataConnection()->getModelBuilder();
            $modelBuilder->generateModelForObject($target_obj);
            $skipped += $modelBuilder->countSkippedEntities();
            $created += $modelBuilder->countCreatedEntities();
        }
        
        
        // Save the result and output a message for the user
        $objects_sheet->addFilterFromColumnValues($objects_sheet->getUidColumn())->dataRead();
        $this->setResultDataSheet($objects_sheet);
        $this->setResult('');
        $this->setResultMessage($this->translate('RESULT_FOR_OBJECT', ['%created_counter%' => $created, '%object_counter%' => $objects_sheet->countRows(), '%skipped_counter%' => $skipped]));
        
        return;
    }
}
?>