<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\ActionEffectFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\Interfaces\Actions\iModifyData;

/**
 * Generates metaobjects and attributes from a data source.
 * 
 * This action has basically two modes of operation:
 * 
 * - object update (if an object is specified in the input) will create attributes for this object if they are present
 * in the data source but not yet in the model. This will also update the data type of existing attributes if they
 * differ from the data source.
 * - object creation (if no object is specified in the input) will create objects for all addresses in the data source
 * 
 * ## CLI usage
 * 
 * This action can be called from CLI with the following parameters:
 * 
 * - `--source` - the UID or namespaced alias of the data source to inspect
 * - `--app` - the UID or namespaced alias of the app in which to generate objects
 * - `--object` - the UID or namespaced alias of the object for which to generate attributes
 * - `--address` - a data address mask used to restrict imported objects or attributes (use data source specific syntax, 
 * e.g. "order%" for all addresses starting with "order" for typical SQL data sources)
 * - `--config` - a JSON configuration for the model builder
 * 
 * ### Examples
 *
 * - `vendor/bin/action exface.Core:GenerateModelFromDataSource --object=my.App.my_object` - generate missing attributes 
 * for the given object
 * - `vendor/bin/action exface.Core:GenerateModelFromDataSource --source=exface.Core.my_data_source --app=my.App --address="order%"` - 
 * Generate objects from all addresses in the data source starting with "order" and create them in the app "my.App".
 * 
 * ## Model usage
 * 
 * When called from a widget, behavior or other action, the input data must be based on the `exface.Core.MODEL_BUILDER_INPUT`.
 *
 * @author Andrej Kabachnik
 *        
 */
class GenerateModelFromDataSource extends AbstractAction implements iCanBeCalledFromCLI, iModifyData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::COGS);
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setInputObjectAlias('exface.Core.MODEL_BUILDER_INPUT');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
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
                $model_builder = $this->getModelBuilder($data_source, UxonObject::fromAnything($row['BUILDER_CONFIG_UXON']));
                
                $created_ds = $model_builder->generateAttributesForObject($this->getWorkbench()->model()->getObject($row['OBJECT']), $row['OBJECT_DATA_ADDRESS_MASK'] ?? '');
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsInDataSource() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' attributes, ' . $skipped . ' skipped as duplicates.';
            
        } elseif ($data_src_col && ! $data_src_col->isEmpty()) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $app = $this->getWorkbench()->getApp($row['APP']);
                $model_builder = $this->getModelBuilder($data_source, UxonObject::fromAnything($row['BUILDER_CONFIG_UXON']));
                
                $created_ds = $model_builder->generateObjectsForDataSource($app, $data_source, $row['OBJECT_DATA_ADDRESS_MASK']);
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsInDataSource() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' objects, ' . $skipped . ' skipped as duplicates.';
        }
        $message .= "\n" . $model_builder->getLogbook()->__toString();
        
        return ResultFactory::createMessageResult($task, $message);
    }

    /**
     * Returns the UI input or creates equivalent input from CLI parameters.
     *
     * @param TaskInterface $task
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function getInputDataSheet(TaskInterface $task) : DataSheetInterface
    {
        if ($task->hasInputData()) {
            return parent::getInputDataSheet($task);
        }

        $appUidOrAlias = $task->hasParameter('app') ? $task->getParameter('app') : null;
        $dataSourceUidOrAlias = $task->hasParameter('source') ? $task->getParameter('source') : null;
        $objectUid = $task->hasParameter('object') ? $task->getParameter('object') : null;
        if ($task->hasParameter('object')) {
            $object = MetaObjectFactory::createFromString($this->getWorkbench(), $task->getParameter('object'));
            if (! $dataSourceUidOrAlias) {
                $dataSourceUidOrAlias = $object->getDataSource()->getId();
            }
            if (! $appUidOrAlias) {
                $appUidOrAlias = $object->getApp()->getUid();
            }
        }
        
        if (! $dataSourceUidOrAlias) {
            throw new ActionInputMissingError($this, 'Missing data source for action ' . $this->getAliasWithNamespace() . '!');
        }
        if (! $appUidOrAlias) {
            throw new ActionInputMissingError($this, 'Specify either an app or an object for action ' . $this->getAliasWithNamespace() . '!');
        }

        $inputData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.MODEL_BUILDER_INPUT');
        $inputData->addRow([
            'DATA_SOURCE' => $dataSourceUidOrAlias,
            'APP' => $appUidOrAlias,
            'OBJECT' => $objectUid,
            'OBJECT_DATA_ADDRESS_MASK' => $task->hasParameter('address') ? $task->getParameter('address') : '',
            'BUILDER_CONFIG_UXON' => $task->hasParameter('config') ? $task->getParameter('config') : null,
        ]);
        return $inputData;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return [];
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return [
            (new ServiceParameter($this))
                ->setName('source')
                ->setDescription('UID or namespaced alias of the data source to inspect.'),
            (new ServiceParameter($this))
                ->setName('app')
                ->setDescription('UID or namespaced alias of the app in which to generate objects.'),
            (new ServiceParameter($this))
                ->setName('object')
                ->setDescription('UID or namespaced alias of the object for which to generate attributes.'),
            (new ServiceParameter($this))
                ->setName('address')
                ->setDescription('Data address mask used to restrict imported objects or attributes.'),
            (new ServiceParameter($this))
                ->setName('config')
                ->setDescription('JSON configuration for the model builder.')
        ];
    }

    /**
     * @param DataSourceInterface $data_source
     * @param UxonObject|null $configUxon
     * @return ModelBuilderInterface
     */
    protected function getModelBuilder(DataSourceInterface $data_source, ?UxonObject $configUxon = null) : ModelBuilderInterface
    {
        $model_builder = $data_source->getConnection()->getModelBuilder();
        if ($configUxon) {
            $configArray = $configUxon->toArray();
            $configArray = array_filter($configArray);
            $configUxon = new UxonObject($configArray);
            $model_builder->importUxonObject($configUxon);
        }
        return $model_builder;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getEffects()
     */
    public function getEffects() : array
    {
        $effects = parent::getEffects();
        $effects[] = ActionEffectFactory::createForEffectedObjectAliasOrUid($this, 'exface.Core.OBJECT');
        $effects[] = ActionEffectFactory::createForEffectedObjectAliasOrUid($this, 'exface.Core.ATTRIBUTE');
        $effects[] = ActionEffectFactory::createForEffectedObjectAliasOrUid($this, 'exface.Core.DATATYPE');
        $effects[] = ActionEffectFactory::createForEffectedObjectAliasOrUid($this, 'exface.Core.OBJECT_ACTION');
        return $effects;
    }
}