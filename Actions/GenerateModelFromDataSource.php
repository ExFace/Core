<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Actions\ActionEffect;
use exface\Core\CommonLogic\UxonObject;

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
        $this->setInputObjectAlias('exface.Core.MODEL_BUILDER_INPUT');
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input_data = $this->getInputDataSheet($task);
        
        $obj_col = $input_data->getColumns()->getByExpression('OBJECT');
        $data_src_col = $input_data->getColumns()->getByExpression('DATA_SOURCE');
        $message = '';
        $created = 0;
        $skipped = 0;
        if ($obj_col && ! $obj_col->isEmpty(true)) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateAttributesForObject($this->getWorkbench()->model()->getObject($row['OBJECT']), $row['OBJECT_DATA_ADDRESS_MASK'] ?? '');
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsInDataSource() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' attributes, ' . $skipped . ' skipped as duplicates.';
            
        } elseif ($data_src_col && ! $data_src_col->isEmpty()) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $app = $this->getWorkbench()->getApp($row['APP']);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateObjectsForDataSource($app, $data_source, $row['OBJECT_DATA_ADDRESS_MASK']);
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsInDataSource() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' objects, ' . $skipped . ' skipped as duplicates.';
        }
        
        return ResultFactory::createMessageResult($task, $message);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getEffects()
     */
    public function getEffects() : array
    {
        $effects = parent::getEffects();
        $effects[] = new ActionEffect($this, new UxonObject(['effected_object' => 'exface.Core.OBJECT']));
        $effects[] = new ActionEffect($this, new UxonObject(['effected_object' => 'exface.Core.ATTRIBUTE']));
        $effects[] = new ActionEffect($this, new UxonObject(['effected_object' => 'exface.Core.DATATYPE']));
        $effects[] = new ActionEffect($this, new UxonObject(['effected_object' => 'exface.Core.OBJECT_ACTION']));
        return $effects;
    }
}