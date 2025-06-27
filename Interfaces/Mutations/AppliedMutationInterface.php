<?php
namespace exface\Core\Interfaces\Mutations;

interface AppliedMutationInterface extends MutationResultInterface
{
    public function getMutation() : MutationInterface;
}