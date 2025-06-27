<?php
namespace exface\Core\Interfaces\Mutations;

interface AppliedMutationRuleInterface extends MutationResultInterface
{
    public function getMutationRule() : MutationRuleInterface;
}