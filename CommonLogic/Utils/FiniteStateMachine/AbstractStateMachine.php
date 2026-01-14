<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser\SimpleParser;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser\SimpleParserState;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser\SimpleParserTransition;

/**
 * A generic implementation of a finite state machine (FSM). 
 * 
 * An FSM consists of any number of states with unique names that are connected via transitions. When given an input
 * and some data to process, the FSM will feed both into its current state, which may perform transformations and
 * other tasks. Once the active state meets a trigger condition defined in any of its transitions, it will perform
 * said transition. 
 * 
 * Think of states as defining a certain behavior for the machine and transitions as the rules for when to apply 
 * said behavior. Since every state is completely independent of the others and can be arbitrarily complex, an FSM can
 * solve complex problems via relatively simple configuration.
 * 
 * Once started, the FSM will continue to run until any of its states signals an EXIT or until it encounters an error. 
 * As such a poorly configured FSM may result in an infinite loop.
 * 
 * ### Implementing a specialized case
 * 
 * This abstract FSM is not suited to solve anything. Instead, you have to adapt it to your use-case.
 * Apart from implementing all abstract methods, you should also consider the following:
 * 
 * 1. Decide when and how input data is passed to the state machine. The easiest way is to override `process` with
 * an optional parameter and handle any wrapper conversions there. Assign the processed data to `$this->data` and 
 * remember to assign the raw input data to `$this->dataRaw` to enable better debugging info. 
 * 2. Create a useful data structure to wrap your input data. You will have to handle buffers and many other concerns,
 * which is much easier if you're a dedicated data class.
 * 3. Override `getDebugInfo()` with `array_merge(parent::getDebugInfo(), [yourInfo])` to include more detailed info 
 * for your specialized case.
 * 4. Implement specialized versions for `AbstractTransition` and `AbstractState`.
 * 
 * @see AbstractState, AbstractTransition, SimpleParser, SimpleParserState, SimpleParserTransition
 */
abstract class AbstractStateMachine
{
    protected array $states = [];
    protected ?AbstractState $initial = null;
    protected ?AbstractState $current = null;
    protected int $maxIterations;
    protected mixed $dataRaw = null;
    protected mixed $data;

    /**
     * @param array $states
     * @param int   $maxIterations
     */
    function __construct(array $states,  int $maxIterations = 100000)
    {
        $this->states = $states;
        $this->current = $states[0];
        $this->maxIterations = $maxIterations;
    }

    /**
     * Adds a state. State names must be unique.
     *
     * Returns TRUE, if the state was added or an existing state was overwritten.
     * The first state added to this FSM will also be its initial state.
     *
     * @param AbstractState $state
     * @param bool          $overwrite
     * @return bool
     */
    protected function addState(AbstractState $state, bool $overwrite = false) : bool
    {
        $name = $state->getName();

        if($overwrite || !key_exists($name, $this->states)) {
            $this->states[$name] = $state;
            return true;
        }

        return false;
    }

    /**
     * Explicitly set a state as initial state.
     * 
     * It will be the first state to be run, when `process()` is called. By default, the first state added via
     * `addState()` is also the initial state.
     * 
     * @see AbstractStateMachine::process(), AbstractStateMachine::addState()
     * @param string $name
     * @return $this
     */
    protected function setInitialState(string $name) : AbstractStateMachine
    {
        $this->initial = $this->states[$name];
        return $this;
    }

    /**
     * Run this FSM with its current configuration.
     * 
     * @return mixed
     * The processed data.
     */
    public function process() : mixed
    {
        if(empty($this->states) || $this->current === null) {
            return null;
        }
        
        $data = $this->getDataForProcessing();
        $iterations = $this->maxIterations;
        
        while ($iterations > 0) {
            $nextState = $this->current->process($this->getInput($data), $data);
            
            if($nextState === true) {
                break;
            } else {
                $this->current = $nextState;
            }
            
            $iterations = $this->maxIterations < 0 ? $iterations : $iterations - 1;
        }
        
        return $data;
    }

    /**
     * Returns debug info on the current state of this FSM.
     * 
     * @return array
     */
    public function getDebugInfo() : array
    {
        return [
            'Raw Input Data' => $this->dataRaw,
            'Active State' => $this->current->getName(),
            'Input' => $this->getInput($this->data)
        ];
    }

    /**
     * Retrieve input data ready for processing.
     * 
     * If you are using a wrapper, override the return type as well.
     * 
     * @return mixed
     */
    protected abstract function getDataForProcessing() : mixed;

    /**
     * Return the next input for the current state.
     * 
     * For example, if you are processing an array, this should return
     * the next index in that array.
     * 
     * @param $data
     * @return mixed
     */
    protected abstract function getInput($data) : mixed;
}