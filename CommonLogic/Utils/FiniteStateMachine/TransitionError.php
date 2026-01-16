<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

/**
 * This transition throws an error when triggered.
 */
class TransitionError extends AbstractTransition
{
    protected \Throwable $error;

    /**
     * @param mixed              $trigger
     * @param AbstractState|null $target
     * @param \Throwable         $error
     */
    public function __construct(mixed $trigger, ?AbstractState $target, \Throwable $error)
    {
        parent::__construct($trigger, $target);
        $this->error = $error;
    }

    /**
     * @return \Throwable
     */
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