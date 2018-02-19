<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

/**
 * Trait with shared logic for the FileSelectorInterface
 *
 * @author Andrej Kabachnik
 *
 */
trait UidSelectorTrait
{
    private $isUid = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UidSelectorInterface::isUid()
     */
    public function isUid()
    {
        if (is_null($this->isUid)) {
            $this->isUid = (substr($this->toString(), 0, 2) == '0x' ? true : false);
        }
        return $this->isUid;
    }
}