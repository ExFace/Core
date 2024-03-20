<?php
namespace exface\Core\CommonLogic\Tasks;

/**
 * Task result containing HTML content.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultHTML extends ResultTextContent
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::getMimeType()
     */
    public function getMimeType($default = 'text/html'): string
    {
        return parent::getMimeType($default);
    }
}