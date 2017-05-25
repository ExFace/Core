<?php
namespace exface\Core\Widgets;

class InputPropertyTable extends Input
{

    private $allow_add_properties = true;

    private $allow_remove_properties = true;

    public function getAllowAddProperties()
    {
        return $this->allow_add_properties;
    }

    public function setAllowAddProperties($value)
    {
        $this->allow_add_properties = $value;
    }

    public function getAllowRemoveProperties()
    {
        return $this->allow_remove_properties;
    }

    public function setAllowRemoveProperties($value)
    {
        $this->allow_remove_properties = $value;
    }
}
?>