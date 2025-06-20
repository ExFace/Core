<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface MutatorInterface extends WorkbenchDependantInterface
{
    /**
     * @return MutationPointInterface[]
     */
    public function getMutationPoints() : array;

    /**
     * @param $selectorOrString
     * @return MutationPointInterface
     */
    public function getMutationPoint($selectorOrString) : MutationPointInterface;

    /**
     * @param $subject
     * @return AppliedMutationInterface[]
     */
    public function getMutationsApplied($subject) : array;
}