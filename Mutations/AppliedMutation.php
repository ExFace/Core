<?php
namespace exface\Core\Mutations;

use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;

class AppliedMutation implements AppliedMutationInterface
{
    private $mutation = null;
    private $subject = null;
    private $stateBefore = null;
    private $stateAfter = null;

    public function __construct(MutationInterface $mutation, $subject, string $stateBefore, string $stateAfter)
    {
        $this->mutation = $mutation;
        $this->subject = $subject;
        $this->stateBefore = $stateBefore;
        $this->stateAfter = $stateAfter;
    }

    public function getMutation(): MutationInterface
    {
        return $this->mutation;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function dumpStateBefore(): string
    {
        return $this->stateBefore;
    }

    public function dumpStateAfter(): string
    {
        return $this->stateAfter;
    }
}