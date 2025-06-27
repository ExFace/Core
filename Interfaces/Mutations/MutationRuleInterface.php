<?php
namespace exface\Core\Interfaces\Mutations;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * A mutation rule is a class, that can mutate a model instance.
 *
 * Although rules can be applied to their subject directly, they are intended to be used inside mutations,
 * that consist of one or multiple rules and provide additional functionality like debug output, etc.
 *
 * @author Andrej Kabachnik
 */
interface MutationRuleInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function apply($subject) : AppliedMutationInterface;

    public function supports($subject) : bool;

    public function isDisabled() : bool;

    public function setDisabled(bool $trueOrFalse) : MutationRuleInterface;
}