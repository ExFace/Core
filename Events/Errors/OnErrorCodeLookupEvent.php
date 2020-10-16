<?php
namespace exface\Core\Events\Errors;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Events\ErrorEventInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\DataSheets\DataSheet;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Event fired after an error code was resolved providing title, hint and description of the error.
 * 
 * Listeners to this even can hook in additional logic describing the error - e.g.
 * looking up the error code in external source, changing the title or hint, etc.
 * 
 * @event exface.Core.Errors.OnOnErrorCodeLookup
 *
 * @author Andrej Kabachnik
 *
 */
class OnErrorCodeLookupEvent extends AbstractEvent implements ErrorEventInterface
{
    
    private $e = null;
    
    private $workbench = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     */
    public function __construct(WorkbenchInterface $workbench, ExceptionInterface $e)
    {
        $this->e = $e;
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ErrorEventInterface::getException()
     */
    public function getException() : ExceptionInterface
    {
        return $this->e;   
    }
}