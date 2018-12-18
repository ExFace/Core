<?php
namespace exface\Core\CommonLogic\Tasks;

/**
 * Task result containing JSON content.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultJSON extends ResultTextContent
{
    public function setContentJSON($arrayOrObject) : ResultJSON
    {
        return parent::setContent(json_encode($arrayOrObject));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::getMimeType()
     */
    public function getMimeType($default = 'application/json'): string
    {
        return parent::getMimeType($default);
    }
}