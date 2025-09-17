<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\VersionSelectorInterface;

/**
 * Trait for versioned aliases - e.g. `my.App.ALIAS:1.0`
 *
 * @author Andrej Kabachnik
 *
 */
trait VersionedAliasSelectorTrait
{
    use VersionedSelectorTrait;
    use AliasSelectorTrait;

    /**
     * @see AliasSelectorTrait::splitAlias()
     */
    protected function splitAlias(string $string) : array
    {
        list($alias, $version) = explode(VersionSelectorInterface::VERSION_DELIMITER, $string, 2);
        $this->version = $version ?? '*';
        if (mb_substr($string, 0, 1) === '.' || mb_substr($string, -1) === '.') {
            throw new SelectorInvalidError('"' . $string . '" is not a valid alias selector!');
        }
        return explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $alias);
    }

    /**
     * Returns the namespaced alias without the version
     * @return string
     */
    public function getAliasWithNamespace() : string
    {
        return $this->stripVersion($this->toString());
    }
}