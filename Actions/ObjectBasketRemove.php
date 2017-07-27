<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;

/**
 * Removes meta object instances matching the input data from the object basket in the given context scope (window scope by default)
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketRemove extends ObjectBasketAdd
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIconName(Icons::MINUS);
    }

    protected function perform()
    {
        $input = $this->getInputDataSheet();
        $object = $input->getMetaObject();
        if ($input->isEmpty()) {
            $this->getContext()->removeInstancesForObjectId($object->getId());
            $this->setResultMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT_ALL', array(
                '%context_name%' => $this->getContext()->getName(),
                '%object_name%' => $object->getName()
            )));
        } else {
            $removed = 0;
            foreach ($input->getUidColumn()->getValues(false) as $uid) {
                $this->getContext()->removeInstance($object->getId(), $uid);
                $removed ++;
            }
            $this->setResultMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT', array(
                '%context_name%' => $this->getContext()->getName(),
                '%number%' => $removed,
                '%object_name%' => $object->getName()
            ), $removed));
        }
        
        $this->setResult('');
    }
}
?>