<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

class TransitionError extends Transition
{
    protected \Throwable $error;

    public function __construct(mixed $trigger, AbstractState $target, \Throwable $error)
    {
        parent::__construct($trigger, $target);
        $this->error = $error;
    }

    public function getError() : \Throwable
    {
        return $this->error;
    }

    /**
     * @throws \Throwable
     */
    public function perform(): AbstractState|bool
    {
        throw $this->error;
    }
}