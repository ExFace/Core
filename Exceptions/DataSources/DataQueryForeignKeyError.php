<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a query fails due to a foreign key violation within the data source.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryForeignKeyError extends DataQueryConstraintError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '8557RS3';
    }

    protected function generateMessage(MetaObjectInterface $obj) : ?string
    {
        $attrAliases = array_keys($this->getAttributeValues());
        $attrNames = [];

        foreach ($attrAliases as $attrAlias) {
            try {
                $attrNames[] = $obj->getAttribute($attrAlias)->getName();
            } catch (\Throwable $e) {
                // Skip invalid aliases
            }
        }

        $translator = $obj->getWorkbench()->getCoreApp()->getTranslator();

        if (empty($attrNames)) {
            $msg = $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE', [
                '%object_name%' => '"' . $obj->getName() . '"'
            ]);
        } elseif (count($attrNames) === 1) {
            $msg = $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_ATTR', [
                '%object_name%' => '"' . $obj->getName() . '"',
                '%attr_name%' => '"' . $attrNames[0] . '"'
            ]);
        } else {
            $lastName = array_pop($attrNames);
            $msg = $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_ATTRS', [
                '%object_name%' => '"' . $obj->getName() . '"',
                '%attr_list%' => '"' . implode('", "', $attrNames) . '"',
                '%attr_last%' => '"' . $lastName . '"'
            ]);
        }

        return $msg;
    }
}