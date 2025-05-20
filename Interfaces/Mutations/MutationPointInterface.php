<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface MutationPointInterface extends WorkbenchDependantInterface
{
    /**
     * @param MutationTargetInterface $target
     * @param $subject
     * @return AppliedMutationInterface[]
     */
    public function applyMutations(MutationTargetInterface $target, $subject) : array;

    /**
     * @param $subject
     * @return AppliedMutationInterface[]
     */
    public function getMutationsApplied($subject) : array;
}