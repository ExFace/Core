<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\VersionSelectorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\SemanticVersionDataType;

/**
 * Trait with shared logic for the VersionSelectorTrait
 *
 * @author Andrej Kabachnik
 *
 */
trait VersionSelectorTrait
{
    private $version = null;
    
    /**
     * 
     * @return string
     */
    public function getVersion() : string
    {
        if ($this->version === null) {
            $string = $this->toString();
            $this->version = explode(VersionSelectorInterface::VERSION_DELIMITER, $string)[1] ?? SemanticVersionDataType::WILDCARD;
        }
        return $this->version;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasVersion() : bool
    {
        return $this->getVersion() !== SemanticVersionDataType::WILDCARD;
    }
    
    public function stripVersion() : string
    {
        $str = $this->__toString();
        return StringDataType::substringBefore($str, VersionSelectorInterface::VERSION_DELIMITER, $str);
    }
}