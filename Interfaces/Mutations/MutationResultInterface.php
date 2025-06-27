<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface MutationResultInterface extends iCanGenerateDebugWidgets
{
    public function getSubject() : mixed;

    public function hasChanges() : bool;

    public function dumpStateBefore() : string;

    public function dumpStateAfter() : string;
}