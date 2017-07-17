<?php
namespace exface\Core\Actions;

use exface\Core\Contexts\ObjectBasketContext;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;

/**
 * Adds the input rows to the object basket in a specified context_scope (by default, the window scope)
 *
 * @method ObjectBasketContext getContext()
 *        
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketAdd extends AbstractAction implements iModifyContext
{
    use ContextActionTrait {
        getContextScope as parentGetContextScope;
    }
    
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIconName(Icons::SHOPPING_BASKET);
        $this->setContextAlias('exface.Core.ObjectBasketContext');
        $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_WINDOW);
    }

    public function getContextScope()
    {
        if (! $this->parentGetContextScope()) {
            $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_WINDOW);
        }
        return $this->parentGetContextScope();
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