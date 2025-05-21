<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface MutationTargetInterface extends \Stringable
{
    public function getTargetKey() : string;

    public function getTargetValue() : string;
}