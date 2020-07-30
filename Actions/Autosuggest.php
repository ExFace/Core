<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;

/**
 * The autosuggest action is similar to the general ReadData, but it does not affect the current window filter context because the user
 * does not really perform an explicit serch here - it's merely the system helping the user to speed up input.
 * The context, the user is
 * working it does not changed just because the system wishes to help him!
 *
 * Another difference is, that the autosuggest result also includes mixins like previously used entities, etc. - even if they are not
 * included in the regular result set of the ReadData action.
 *
 * @author Andrej Kabachnik
 *        
 */
class Autosuggest extends ReadData
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setUpdateFilterContext(false);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        // IDEA Include recently used objects in the autosuggest results. But where can we get those object from?
        // Another window context? The filter context?
        return parent::perform($task, $transaction);
    }
}