<?php
namespace exface\Core\Actions;

use exface\Core\Contexts\ObjectBasketContext;

/**
 * Adds the input rows to the object basket in a specified context_scope (by default, the window scope)
 *
 * @method ObjectBasketContext getContext()
 *        
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketAdd extends SetContext
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIconName('basket');
        $this->setContextType('ObjectBasket');
        $this->setContextScope('Window');
    }

    public function getContextScope()
    {
        if (! parent::getContextScope()) {
            $this->setContextScope('Window');
        }
        return parent::getContextScope();
    }

    protected function perform()
    {
        $this->getContext()->add($this->getInputDataSheet());
        $this->setResultMessage($this->translate('RESULT', array(
            '%context_name%' => $this->getContext()->getName(),
            '%number%' => $this->getInputDataSheet()->countRows()
        ), $this->getInputDataSheet()->countRows()));
        $this->setResult('');
    }
}
?>