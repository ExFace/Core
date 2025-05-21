<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface MutationInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function getName() : ?string;

    public function apply($subject) : AppliedMutationInterface;

    public function supports($subject) : bool;

    public function setDisabled(bool $trueOrFalse) : MutationInterface;
}