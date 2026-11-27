<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\DataTypes\UUIDDataType;

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
        if ($this->isUid === null) {
            $this->isUid = UUIDDataType::isHexUID($this->toString());
        }
        return $this->isUid;
    }
}