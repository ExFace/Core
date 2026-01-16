<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser\SimpleParserState;

/**
 * Base class for states.
 * 
 * A state is what makes an FSM tick. The FSM passes an input and some data to its current state via 
 * `process($input, &$data)` and fully hands off control. As such a state may perform arbitrarily complex operations.
 * Additionally, it contains any number of transitions that govern when and how the FSM transitions into other states.
 *
 * @see SimpleParserState
 */
abstract class AbstractState
{
    protected string $name;
    protected array $transitionsBefore = [];
    protected array $transitionsAfter = [];
    protected array $patterns;

    /**
     * Create a new state.
     * 
     * @param string $name
     * Names must be unique, since they are used to identify states.
     */
    function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Adds a new transition that will be checked (and performed) BEFORE processing
     * the current input.
     * 
     * @param AbstractTransition $transition
     * @return $this
     */
    public function addTransitionBefore(AbstractTransition $transition) : AbstractState
    {
        return $this->addTransition($transition, true);
    }

    /**
     * Adds a new transition that will be checked (and performed) AFTER processing
     *  the current input.
     * 
     * @param AbstractTransition $transition
     * @return $this
     */
    public function addTransitionAfter(AbstractTransition $transition) : AbstractState
    {
        return $this->addTransition($transition, false);
    }

    /**
     * Adds a new transition.
     * 
     * Serves as a unified "add" method to simplify overrides.
     * 
     * @param AbstractTransition $transition
     * @param bool               $before
     * @return $this
     */
    protected function addTransition(AbstractTransition $transition, bool $before) : AbstractState
    {
        if($before) {
            $this->transitionsBefore[] = $transition;
            
        } else {
            $this->transitionsAfter[] = $transition;
            
        }
        
        return $this;
    }

    /**
     * Checks all transitions in the specified group and returns the FIRST transition that matched the input.
     * 
     * @param      $input
     * @param bool $beforeProcessing
     * If TRUE, only transitions added via `addTransitionBefore()` are checked and vice versa.
     * @return AbstractTransition|null
     */
    protected function checkTransitions($input, bool $beforeProcessing) : ?AbstractTransition
    {
        $transitions = $beforeProcessing ? $this->transitionsBefore : $this->transitionsAfter;
        foreach ($transitions as $transition) {
            if($input === $transition->getTrigger()) {
                return $transition;
            }
        }
        
        return null;
    }

    /**
     * Processes both input and data.
     * 
     * @param $input
     * @param $data
     * @return AbstractState|bool
     */
    public abstract function process($input, &$data) : AbstractState|bool;

    /**
     * Perform any logic required to properly exit this state.
     * 
     * @param AbstractTransition|null $transition
     * @param                         $data
     * @param bool                    $before
     * @return AbstractState|bool
     */
    public abstract function exit(?AbstractTransition $transition, &$data, bool $before) : AbstractState|bool;
}