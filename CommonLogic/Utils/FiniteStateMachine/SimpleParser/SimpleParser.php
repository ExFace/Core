<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Model\SymfonyLexer;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractStateMachine;
use exface\Core\Factories\AttributeGroupFactory;

/**
 * This is a simple string parser. Simple, because it is not aware of locale or any complex pattern matching. It uses
 * the symfony lexer to tokenize its input.
 * 
 * NOTE: This parser uses a stack, which means transitions with target `null` act as RETURN transitions. Whenever
 * you enter a new state, the old state will be put on the stack. If you exit a state via RETURN the parser will
 * continue with whatever state is on top of the stack.
 * 
 * ### Usage
 * 
 * If you have a fully configured parser at hand, simply call `process($string)` and use the return value:
 * 
 * ```
 * 
 * $parser = $this->buildParser();
 * $parsedString = $parser->process($string);
 * 
 * ```
 * 
 * ### Building a parser
 * 
 * Building a parser is more involved. Let's look at the attribute alias parser as an example. 
 * 
 * Think of it as a little program that you are writing. Clearly envision what it's meant to do and mentally walk 
 * through its processing. Then, start by defining all the possible states it can be in. Bear in mind that states are 
 * identified by their names, so make sure that they are unique within the same parser.
 * 
 * Attribute groups in our example are strings with the following features:
 * - Aliases can be sequenced with '&' and '~': `alias1&alias2~VISIBLE` => [alias1, alias2, ~VISIBLE]
 * - Aliases can be grouped with '(' and ')': `alias1(alias2~VISIBLE)` => [0 => [alias1, 1], 1 => [alias2, ~VISIBLE]]
 * - Alias groups can have a modifier denoted by '[' and ']': `alias1[Modifier1]` => ["aliases" => [alias1], "modifiers" => [Modifier1]]
 * - Modifiers may contain any symbols, but are split on ',': `alias[Modifier1(arg,arg),Modifier2(arg,arg)]` => alias1[Modifier1] => ["aliases" => [alias1], "modifiers" => [Modifier1(arg,arg),Modifier2(arg,arg)]]
 * 
 * From these rules we can derive three states:
 * 
 * ```
 * 
 * // This is the base state. It collects aliases in groups, which will be bounded by '(' and ')'.
 * // NOTE: The name will be used as key in the output array.
 * $stateAliases = new SimpleParserState('aliases');
 * 
 * // This state collects the modifiers bounded by '[' and ']'.
 * $stateModifiers = new SimpleParserState('modifiers');
 * 
 * // This state ensures that modifiers only split on first level ','.
 * $stateModifierArgs = new SimpleParserState('modifierArgs', true);
 * 
 * ```
 * 
 * Now we need to configure each states with all the rules and transitions it needs:
 * 
 * ```
 * 
 * // ALIASES
 * // Transition into the ALIASES state, but create a new group, when encountering '('.
 * $stateAliases->addTransitionAfter(new SimpleParserTransition('(', $stateAliases, [SimpleParserTransition::GROUP]));
 * // Transition into the MODIFIERS state when encountering '['.
 * $stateAliases->addTransitionAfter(new SimpleParserTransition('[', $stateModifiers));
 * // RETURN from this state and continue with whatever state is on top of the stack. If the stack is empty, the parser is done.
 * $stateAliases->addTransitionAfter(new SimpleParserTransition(')', null, [SimpleParserTransition::GROUP]));
 * // Token rules allow you to fine-tune the state behavior for certain tokens. Here we define, that aliases should
 * // be split on encountering '&' and '~', but '~' will still be written to the output.
 * $stateAliases->addTokenRule('&', true, true);
 * $stateAliases->addTokenRule('~', true, false);
 *
 * // MODIFIERS
 * // First we define some special options, to make this more readable. 
 * // `WRITE_TOKEN` means the token that triggers the transition will still be written to the output.
 * // `CONCAT` means that the output from this state will be concatenated to the state it RETURNS to, instead of
 * // creating a new array element. Think of it as "continuing the sentence".
 * $optionsWriteConcat = [SimpleParserTransition::WRITE_TOKEN, SimpleParserTransition::CONCAT];
 * // Transition into the ARGUMENTS state and continue the sentence.
 * $stateModifiers->addTransitionBefore(new SimpleParserTransition('(', $stateModifierArgs, $optionsWriteConcat));
 * // RETURN from this state.
 * $stateModifiers->addTransitionAfter(new SimpleParserTransition(']', null));
 * // Split on ',' and don't output the token.
 * $stateModifiers->addTokenRule(',', true, true);
 *
 * // ARGUMENTS
 * // Transition into the ARGUMENTS state and continue the sentence. We do this to deal with recursive brackets.
 * $stateModifierArgs->addTransitionBefore(new SimpleParserTransition('(', $stateModifierArgs, $optionsWriteConcat));
 * // RETURN from this state and continue the sentence.
 * $stateModifierArgs->addTransitionAfter(new SimpleParserTransition(')', null, $optionsWriteConcat));
 * 
 * ```
 * 
 * And now we just need to call the constructor and pass our states as arguments. By default, the first state will be
 * used as the initial state. Since we want the ALIASES state to be our base-line, we pass that as the first. The order
 * on the remaining elements does not matter.
 * 
 * ```
 * 
 * self::$parser = new SimpleParser([
 *      $stateAliases, // Initial state.
 *      $stateModifiers,
 *      $stateModifierArgs
 * ]);
 * 
 * ```
 * 
 * @see SymfonyLexer, AttributeGroupFactory::getParser()
 */
class SimpleParser extends AbstractStateMachine
{
    public function process(string $data = null) : ?array
    {
        if($data === null || $data === '') {
            return [];
        }

        $this->dataRaw = $data;
        $this->data = new SimpleParserData($data);
        return parent::process()->getOutputAll();
    }

    protected function getDataForProcessing(): SimpleParserData
    {
        return $this->data;
    }

    protected function getInput($data): int
    {
        return $data->getCursor();
    }
    
    public function getOutput() : array
    {
        return $this->data->getOutputAll();
    }
    
    public function getDebugInfo() : array
    {
        return array_merge(parent::getDebugInfo(), [
            'Token' => $this->data->getToken($this->data->getCursor()),
            'Stack' => $this->data->getStackInfo(),
            'Buffer' => $this->data->getOutputAll()
        ]);
    }
}