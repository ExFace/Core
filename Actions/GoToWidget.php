<?php
namespace exface\Core\Actions;

/**
 * This action is just a better understandable alias for ShowWidgetPrefilled
 * 
 * @see ShowWidgetPrefilled
 * @author Andrej Kabachnik
 *        
 */
class GoToWidget extends ShowWidgetPrefilled
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
    }
}
?>