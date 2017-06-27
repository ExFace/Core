<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\AbstractAction;

/**
 * This is the base action to modify context data.
 *
 * @author Andrej Kabachnik
 *        
 */
class SetContext extends AbstractAction
{

    private $context_type = null;

    private $scope = null;

    public function getContextType()
    {
        return $this->context_type;
    }

    public function setContextType($value)
    {
        $this->context_type = $value;
        return $this;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function setScope($value)
    {
        $this->scope = $value;
        return $this;
    }

    /**
     * Returns the context addressed in this action
     *
     * @return AbstractContext
     */
    public function getContext()
    {
        return $this->getApp()->getWorkbench()->context()->getScope($this->getScope())->getContext($this->getContextType());
    }

    protected function perform()
    {
        // TODO
        return;
    }
}
?>