<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\VersionedSelectorInterface;

/**
 * Trait for versioned selectors - e.g. `my.App.ALIAS:1.0`
 *
 * @author Andrej Kabachnik
 *
 */
trait VersionedSelectorTrait
{
    private ?string $version = null;

    /**
     * @see VersionedSelectorInterface::getVersion()
     */
    public function getVersion() : string
    {
        if ($this->version === null) {
            $version = explode($this->toString(), VersionedSelectorInterface::VERSION_SEPARATOR, 2)[1];
            $this->version = $version;
        }
        return $this->version;
    }

    /**
     * @see VersionedSelectorInterface::hasVersion()
     */
    public function hasVersion() : bool
    {
        return $this->getVersion() !== '*';
    }

    /**
     * @param string $selector
     * @return string
     */
    protected function stripVersion(string $selector) : string
    {
        return StringDataType::substringBefore($selector, VersionedSelectorInterface::VERSION_SEPARATOR);
    }
}