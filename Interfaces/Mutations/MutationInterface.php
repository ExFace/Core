<?php
namespace exface\Core\Interfaces\Mutations;

/**
 * Mutations are sets of mutation rules to be applied to certain model instances
 *
 * A mutation can also be used as a rule inside another mutation, so the differences between mutations and rules
 * is not very big: mutations basically rules, that can be used stand-alone.
 *
 * @author Andrej Kabachnik
 */
interface MutationInterface extends MutationRuleInterface
{
    public function getName() : ?string;
}