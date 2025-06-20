<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface AppliedMutationInterface extends iCanGenerateDebugWidgets
{
    public function getMutation() : MutationInterface;

    public function getSubject() : mixed;

    public function hasChanges() : bool;

    public function dumpStateBefore() : string;

    public function dumpStateAfter() : string;
}