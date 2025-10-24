<?php
namespace exface\Core\Mutations;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationOnArrayInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;

class AppliedMutationOnArray extends AppliedMutation implements AppliedMutationOnArrayInterface
{    
    private array $stateBefore;
    private array $stateAfter;
    private ?string $stringBefore = null;
    private ?string $stringAfter = null;

    /**
     * @param MutationInterface $mutation
     * @param $subject
     * @param array $stateBefore
     * @param array $stateAfter
     */
    public function __construct(MutationInterface $mutation, $subject, array $stateBefore, array $stateAfter)
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
            $this->stringBefore = JsonDataType::encodeJson($this->stateBefore, true);
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
            $this->stringAfter = JsonDataType::encodeJson($this->stateAfter, true);
        }
        return $this->stringAfter;
    }
    
    /**
     * {@inheritDoc}
     * @see AppliedMutationOnArrayInterface::dumpStateBeforeAsArray()
     */
    public function dumpStateBeforeAsArray(): array
    {
        return $this->stateBefore;
    }

    /**
     * {@inheritDoc}
     * @see AppliedMutationOnArrayInterface::dumpStateAfterAsArray()
     */
    public function dumpStateAfterAsArray(): array
    {
        return $this->stateAfter;
    }
}