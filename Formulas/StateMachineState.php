<?php
namespace exface\Core\Formulas;

use exface\Core\Behaviors\StateMachineBehavior;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Takes a numeric state value ($state) as input and tries to resolve the states name.
 * If requested ($showId = true) the numeric state value is also part of the result.
 * If parameter $showProgressBar is set to true the result is passed on to the ProgessBar Formula together with min and
 * max values based on the available stat values.
 * Parameter $object references the object with the StateMachineBehavior (e.g. 'alexa.RMS.CUSTOMER_COMPLAINT').
 *
 * Formula example: =StateMachineState(STATE_ID, 'alexa.RMS.CUSTOMER_COMPLAINT', 'true', 'true')
 *
 * Class StateMachineState
 *
 * @package exface\Core\Formulas
 * @author Thomas Walter
 */
class StateMachineState extends Formula
{

    /**
     *
     * @param int $state            
     * @param string $object            
     * @param boolean|String $showId            
     * @param boolean|String $showProgressBar            
     *
     * @return string
     */
    function run($state, $object, $showId = true, $showProgressBar = true)
    {
        $showId = BooleanDataType::parse($showId);
        $showProgressBar = BooleanDataType::parse($showProgressBar);
        
        $workbench = $this->getWorkbench();
        /** @var StateMachineBehavior $smb */
        $smb = $workbench->model()->getObject($object)->getBehaviors()->getByAlias('exface.Core.Behaviors.StateMachineBehavior');
        if (! $smb) {
            // no StateMachineBehavior -> simply return state string
            return strval($state);
        }
        
        // work with StateMachineBehavior
        $states = $smb->getStates();
        $stateString = $this->getStateString($state, $states, $showId);
        
        if ($showProgressBar) {
            $statesKeys = array_keys($states);
            $minProgess = min($statesKeys);
            $maxProgess = max($statesKeys);
            
            $progressBar = new Progressbar($workbench);
            $colorMap = $smb->getProgressBarColorMap();
            if ($colorMap)
                return $progressBar->run($state, $stateString, $minProgess, $maxProgess, $colorMap);
            else
                return $progressBar->run($state, $stateString, $minProgess, $maxProgess);
        } else
            return $stateString;
    }

    /**
     *
     * @param int $state            
     * @param StateMachineState[] $states            
     * @param boolean $showId            
     *
     * @return string
     */
    protected function getStateString($state, $states, $showId)
    {
        $stateObject = $states[$state];
        if ($col = $this->getDataSheet()->getColumns()->get($this->getCurrentColumnName())) {
            $stateName = $stateObject->getStateName($col->getMetaObject()->getApp()->getTranslator());
            if ($stateName) {
                if ($showId)
                    return strval($state) . ' ' . $stateName;
                else
                    return $stateName;
            }
        } else {
            return strval($state);
        }
    }
}
