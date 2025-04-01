<?php
namespace exface\Core\Actions;

use exface\Core\Behaviors\PreventDuplicatesBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Interfaces\Actions\iUpdateData;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * Merges input data with data in the data source(s).
 * 
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class MergeData extends CreateData implements iCreateData, iUpdateData
{
    private $updateIfMatchingAttributeAliases = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        
        if ($this->isUpdateIfMatchingAttributes()) {
            $this->addDuplicatePreventingBehavior($this->getMetaObject());
        }

        return parent::perform($task, $transaction);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     */
    protected function addDuplicatePreventingBehavior(MetaObjectInterface $object)
    {
        $behavior = BehaviorFactory::createFromUxon($object, PreventDuplicatesBehavior::class, new UxonObject([
            'compare_attributes' => $this->getUpdateIfMatchingAttributeAliases(),
            'on_duplicate_multi_row' => PreventDuplicatesBehavior::ON_DUPLICATE_UPDATE,
            'on_duplicate_single_row' => PreventDuplicatesBehavior::ON_DUPLICATE_UPDATE
        ]));
        $object->getBehaviors()->add($behavior);
        return;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getUpdateIfMatchingAttributeAliases() : array
    {
        return $this->updateIfMatchingAttributeAliases;
    }
    
    /**
     * If values in these attibutes are found in the data source, the corresponding rows will be updated instead of a create.
     * 
     * If an existing item of the to-object with exact the same values in all of these attributes
     * is found, the step will perform an update and will not create a new item.
     * 
     * **NOTE:** this will overwrite data in all the attributes affected by the `mapper`.
     *
     * @uxon-property update_if_matching_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CreateData
     */
    protected function setUpdateIfMatchingAttributes(UxonObject $uxon) : CreateData
    {
        $this->updateIfMatchingAttributeAliases = $uxon->toArray();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isUpdateIfMatchingAttributes() : bool
    {
        return empty($this->updateIfMatchingAttributeAliases) === false;
    }
}