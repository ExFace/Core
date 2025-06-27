<?php
namespace exface\Core\CommonLogic\Mutations;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Mutations\MutationRuleInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class AbstractMutationRule implements MutationRuleInterface
{
    use ImportUxonObjectTrait;

    private WorkbenchInterface $workbench;
    private UxonObject $uxon;
    private bool $disabled = false;

    /**
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon);
    }

    /**
     * {@inheritDoc}
     * @see MutationInterface::isDisabled()
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * {@inheritDoc}
     * @see MutationInterface::setDisabled()
     */
    public function setDisabled(bool $trueOrFalse) : MutationInterface
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon;
    }
}