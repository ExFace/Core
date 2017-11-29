<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\Actions\ActionInputTypeError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

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
        $input_data = $this->getInputDataSheet();
        
        if (! $input_data->getMetaObject()->is('exface.Core.MODEL_BUILDER_INPUT')) {
            throw new ActionInputInvalidObjectError($this, 'Action "' . $this->getAlias() . '" exprects exface.Core.MODEL_BUILDER_INPUT as input, "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        
        $obj_col = $input_data->getColumns()->getByExpression('OBJECT');
        $data_src_col = $input_data->getColumns()->getByExpression('DATA_SOURCE');
        $created = 0;
        $skipped = 0;
        if ($obj_col && ! $obj_col->isEmpty(true)) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateAttributesForObject($this->getWorkbench()->model()->getObject($row['OBJECT']));
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsAll() - $created_ds->countRows();
            }
            
            $this->addResultMessage('Created ' . $created . ' attributes, ' . $skipped . ' skipped as duplicates.');
            
        } elseif ($data_src_col && ! $data_src_col->isEmpty()) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $app = $this->getWorkbench()->getApp($row['APP']);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateObjectsForDataSource($app, $data_source);
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsAll() - $created_ds->countRows();
            }
            
            $this->addResultMessage('Created ' . $created . ' objects, ' . $skipped . ' skipped as duplicates.');
        }
        
        $this->setResult('');
        
        return;
    }
}
?>