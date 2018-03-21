<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Removes meta object instances matching the input data from the object basket in the given context scope (window scope by default)
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketRemove extends ObjectBasketAdd
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ObjectBasketAdd::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIcon(Icons::MINUS);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ObjectBasketAdd::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input = $this->getInputDataSheet($task);
        $object = $input->getMetaObject();
        if ($input->isEmpty()) {
            $removed = 1;
            $this->getContext($task)->removeInstancesForObjectId($object->getId());
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT_ALL', array(
                '%context_name%' => $this->getContext($task)->getName(),
                '%object_name%' => $object->getName()
            ));
        } else {
            $removed = 0;
            foreach ($input->getUidColumn()->getValues(false) as $uid) {
                $this->getContext($task)->removeInstance($object->getId(), $uid);
                $removed ++;
            }
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT', array(
                '%context_name%' => $this->getContext($task)->getName(),
                '%number%' => $removed,
                '%object_name%' => $object->getName()
            ), $removed);
        }
        
        $result = ResultFactory::createMessageResult($task, $message);
        if ($removed > 0) {
            $result->setContextModified(true);
        }
        return $result;
    }
}
?>