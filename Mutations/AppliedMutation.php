<?php
namespace exface\Core\Mutations;

use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;

class AppliedMutation implements AppliedMutationInterface
{
    private MutationInterface $mutation;
    private mixed $subject;
    private string $stateBefore;
    private string $stateAfter;

    /**
     * @param MutationInterface $mutation
     * @param object $subject
     * @param string $stateBefore
     * @param string $stateAfter
     */
    public function __construct(MutationInterface $mutation, $subject, string $stateBefore, string $stateAfter)
    {
        $this->mutation = $mutation;
        $this->subject = $subject;
        $this->stateBefore = $stateBefore;
        $this->stateAfter = $stateAfter;
    }

    /**
     * 
     * @see AppliedMutationInterface::getMutation()
     */
    public function getMutation(): MutationInterface
    {
        return $this->mutation;
    }

    /**
     * 
     * @see AppliedMutationInterface::getSubject()
     */
    public function getSubject(): mixed
    {
        return $this->subject;
    }

    /**
     * 
     * @see AppliedMutationInterface::hasChanges()
     */
    public function hasChanges(): bool
    {
        return $this->stateAfter !== $this->stateBefore;
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateBefore()
     */
    public function dumpStateBefore(): string
    {
        return $this->stateBefore;
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateAfter()
     */
    public function dumpStateAfter(): string
    {
        return $this->stateAfter;
    }
}