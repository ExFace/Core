<?php
namespace exface\Core\CommonLogic\Mutations;

use exface\Core\Interfaces\Mutations\MutationInterface;

abstract class AbstractMutation extends AbstractMutationRule implements MutationInterface
{
    private $name = null;

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
}