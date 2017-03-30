<?php
namespace exface\Core\Widgets;

class StateInputSelect extends InputSelect
{
    /** @var boolean $show_status_ids */
    private $show_status_ids = true;

    /**
     * @param boolean $show_status_ids
     */
    public function set_show_status_ids($show_status_ids) {
        $this->show_status_ids = $show_status_ids;
    }

    public function set_selectable_options($array_or_object, array $options_texts_array = NULL)
    {
        return parent::set_selectable_options($array_or_object, $options_texts_array); // TODO: Change the autogenerated stub
    }

    public function get_selectable_options()
    {
        if (!$this->show_status_ids)
            return parent::get_selectable_options();
        else
            return $this->addStateNumbers(parent::get_selectable_options());
    }

    protected function addStateNumbers($options)
    {
        $smb = $this->get_meta_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior');
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
}
