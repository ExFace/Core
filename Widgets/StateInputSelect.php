<?php
namespace exface\Core\Widgets;

/**
 * 
 * The
 * 
 * @author Thomas Walter
 *
 */
class StateInputSelect extends InputSelect
{
    /** @var boolean $show_status_ids */
    private $show_status_ids = true;
    
    protected function init(){
    	$this->set_multi_select(true);
    }

    /**
     * Set to FALSE to not show state ids in the dropdown - just the state names. TRUE by default.
     * 
     * @uxon-property show_status_ids
     * @uxon-type boolean
     * 
     * @param boolean $show_status_ids
     * @return StateInputSelect
     */
    public function set_show_status_ids($show_status_ids) {
        $this->show_status_ids = $show_status_ids;
        return $this;
    }

    public function get_selectable_options()
    {
        $options = $this->applyStateNames(parent::get_selectable_options());
        if ($this->show_status_ids)
            return $this->addStateNumbers($options);
        else
            return $options;
    }

    protected function addStateNumbers($options)
    {
        $smb = $this->get_attribute()->get_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior');
        if (!$smb)
            return parent::get_selectable_options();

        $states = $smb->get_states();
        $appliedOptions = array();
        foreach ($options as $stateNum => $optionValue) {
            /** @var StateMachineState $stateObject */
            $stateObject = $states[$stateNum];
            if (!$stateObject) {
                $appliedOptions[$stateNum] = $optionValue;
                continue;
            }

            $appliedOptions[$stateNum] = $stateNum . ' ' . $optionValue;
        }
        return $appliedOptions;
    }

    /**
     * Uses possibly existing name and name_translation_key attributes of StateMachineStates for displaying options.
     *
     * @param $options
     * @return array
     */
    protected function applyStateNames($options)
    {
        if (!($smb = $this->get_attribute()->get_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior'))) {
            return $options;
        }

        $states = $smb->get_states();
        $appliedOptions = $options;
        
        foreach ($states as $stateNum => $stateObject){
        	if ($appliedOptions[$stateNum]) continue;
        	$name = $stateObject->getStateName($this->get_attribute()->get_object()->get_app()->get_translator());
        	$appliedOptions[$stateNum] = $name;
        }

        return $appliedOptions;
    }
}
