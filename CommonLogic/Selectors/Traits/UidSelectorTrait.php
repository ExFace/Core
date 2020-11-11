<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\DataTypes\HexadecimalNumberDataType;

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
            $this->isUid = (stripos($this->toString(), HexadecimalNumberDataType::HEX_PREFIX) === 0 && strlen($this->toString()) == 34 ? true : false);
        }
        return $this->isUid;
    }
}