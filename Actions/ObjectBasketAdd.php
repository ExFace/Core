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
    }

    public function getScope()
    {
        if (! parent::getScope()) {
            $this->setScope('Window');
        }
        return parent::getScope();
    }

    protected function perform()
    {
        $this->getContext()->add($this->getInputDataSheet());
        $this->setResultMessage($this->translate('RESULT', array(
            '%number%' => $this->getInputDataSheet()->countRows()
        ), $this->getInputDataSheet()->countRows()));
        $this->setResult('');
    }
}
?>