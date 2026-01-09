<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

class TransitionFinal extends Transition
{
    public function perform(): AbstractState|bool
    {
        return true;
    }
}