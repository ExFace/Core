<?php
namespace exface\Core\Mutations;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationOnArrayInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;

class AppliedMutationOnUxon extends AppliedMutation implements AppliedMutationOnArrayInterface
{    
    private UxonObject $stateBefore;
    private UxonObject $stateAfter;
    private ?string $stringBefore = null;
    private ?string $stringAfter = null;

    /**
     * @param MutationInterface $mutation
     * @param $subject
     * @param array $stateBefore
     * @param array $stateAfter
     */
    public function __construct(MutationInterface $mutation, $subject, UxonObject $stateBefore, UxonObject $stateAfter)
    {
        parent::__construct($mutation, $subject);
        $this->stateBefore = $stateBefore;
        $this->stateAfter = $stateAfter;
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateBefore()
     */
    public function dumpStateBefore(): string
    {
        if ($this->stringBefore === null) {
            $this->stringBefore = $this->stateBefore->toJson(true);
        }
        return $this->stringBefore;
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateAfter()
     */
    public function dumpStateAfter(): string
    {
        if ($this->stringAfter === null) {
            $this->stringAfter = $this->stateAfter->toJson(true);
        }
        return $this->stringAfter;
    }

    /**
     * {@inheritDoc}
     * @see AppliedMutationOnArrayInterface::dumpStateBeforeAsArray()
     */
    public function dumpStateBeforeAsArray(): array
    {
        return $this->stateBefore->toArray();
    }

    /**
     * {@inheritDoc}
     * @see AppliedMutationOnArrayInterface::dumpStateAfterAsArray()
     */
    public function dumpStateAfterAsArray(): array
    {
        return $this->stateAfter->toArray();
    }
}