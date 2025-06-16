<?php
namespace exface\Core\CommonLogic\Mutations;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class AbstractMutation implements MutationInterface
{
    use ImportUxonObjectTrait;

    private $workbench = null;
    private $uxon = null;
    private $disabled = false;
    private $name = null;

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
     * @see MutationInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return MutationInterface
     */
    protected function setName(string $name) : MutationInterface
    {
        $this->name = $name;
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