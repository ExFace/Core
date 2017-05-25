<?php

namespace exface\Core\Actions;

/**
 * Removes meta object instances matching the input data from the object basket in the given context scope (window scope by default)
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketRemove extends ObjectBasketFetch
{

    private $return_basket_content = null;

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIconName('remove');
    }

    protected function perform()
    {
        $input = $this->getInputDataSheet();
        $object = $input->getMetaObject();
        if ($input->isEmpty()) {
            $this->getContext()->removeInstancesForObjectId($object->getId());
            $this->setResultMessage($this->getWorkbench()
                ->getCoreApp()
                ->getTranslator()
                ->translate('ACTION.OBJECTBASKETREMOVE.RESULT_ALL', array(
                '%object_name%' => $object->getName()
            )));
        } else {
            $removed = 0;
            foreach ($input->getUidColumn()->getValues(false) as $uid) {
                $this->getContext()->removeInstance($object->getId(), $uid);
                $removed ++;
            }
            $this->setResultMessage($this->getWorkbench()
                ->getCoreApp()
                ->getTranslator()
                ->translate('ACTION.OBJECTBASKETREMOVE.RESULT', array(
                '%number%' => $removed,
                '%object_name%' => $object->getName()
            ), $removed));
        }
        if ($this->getReturnBasketContent()) {
            $this->setResult($this->getFavoritesJson());
        } else {
            $this->setResult('');
        }
    }

    public function getReturnBasketContent()
    {
        if (is_null($this->return_basket_content)) {
            $this->return_basket_content = $this->getWorkbench()->getRequestParam('fetch') ? true : false;
        }
        return $this->return_basket_content;
    }

    public function setReturnBasketContent($value)
    {
        $this->return_basket_content = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }
}
?>