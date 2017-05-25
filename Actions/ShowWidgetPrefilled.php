<?php

namespace exface\Core\Actions;

/**
 * This action is equivalent to ShowWidget except it requires at least one selected row.
 * To linke to another page or a widget
 * within another page there is also an action called "crosslink", that basically does the same thing but uses a better
 * understandable name.
 * 
 * @see crosslink
 * @author Andrej Kabachnik
 *        
 */
class ShowWidgetPrefilled extends ShowWidget
{

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setPrefillWithInputData(true);
    }
}
?>