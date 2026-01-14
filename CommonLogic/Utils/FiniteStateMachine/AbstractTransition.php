<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser\SimpleParserTransition;

/**
 * Base class for transitions.
 * 
 * A transition is essentially a trigger with some extra information. They always belong to a state and govern when
 * state is going to transition into another. They can also perform basic logic and provide additional data.
 *
 * @see SimpleParserTransition
 */
abstract class AbstractTransition
{
    protected ?AbstractState $target;
    protected mixed $trigger;
    protected array $options = [];

    /**
     * @param mixed              $trigger
     * This transition will be performed whenever its state encounters this value.
     * @param AbstractState|null $target
     * When this transition is triggered, the FSM will transition into this state. If set to `null` this transition
     * will simply exit the current state akin to a RETURN.
     * @param array              $options
     * Pass additional options.
     */
    function __construct(mixed $trigger, ?AbstractState $target, array $options = [])
    {
        $this->target = $target;
        $this->trigger = $trigger;
        $this->options = $options;
    }

    /**
     * @return AbstractState|null
     */
    public function getTarget() : ?AbstractState
    {
        return $this->target;
    }

    /**
     * @return mixed
     */
    public function getTrigger() : mixed
    {
        return $this->trigger;
    }

    /**
     * Performs any logic on this transition and returns the state it wants to transition to or `null` if it wants
     * to exit the current state.
     * 
     * @return AbstractState|bool
     */
    public function perform() : AbstractState|bool
    {
        return $this->target ?? true;
    }

    /**
     * @return array
     */
    public function getOptions() : array
    {
        return $this->options;
    }
}