<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a query fails due to a foreign key violation within the data source.
 *
 * @author Andrej Kabachnik
 */
class DataQueryForeignKeyError extends DataQueryConstraintError
{
    private ?MetaObjectInterface $referencingObject = null;

    public function __construct(DataQueryInterface $query, DataConnectionInterface $connection, $message, $alias = null, $previous = null, ?MetaObjectInterface $obj = null, ?array $attributeValues = null, ?MetaObjectInterface $referencingObject = null) 
    {
        $this->referencingObject = $referencingObject;

        parent::__construct($query, $connection, $message, $alias, $previous, $obj, $attributeValues);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '8557RS3';
    }

    protected function generateMessage(MetaObjectInterface $obj): ?string
    {
        $translator = $obj->getWorkbench()->getCoreApp()->getTranslator();

        $targetObjectName = '"' . $obj->getName() . '"';
        $refObjectName = $this->referencingObject !== null
            ? '"' . $this->referencingObject->getName() . '"'
            : null;

        $attrAliases = array_keys($this->getAttributeValues() ?? []);
        $attrNames = [];
        $attributeObject = $this->referencingObject ?? $obj;

        foreach ($attrAliases as $attrAlias) {
            try {
                $attrNames[] = $attributeObject->getAttribute($attrAlias)->getName();
            } catch (\Throwable $e) {
                // Skip invalid aliases
            }
        }

        if (empty($attrNames)) {
            if ($refObjectName !== null) {
                return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_OBJECT', [
                    '%object_name%'     => $targetObjectName,
                    '%ref_object_name%' => $refObjectName,
                ]);
            }

            return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE', [
                '%object_name%' => $targetObjectName,
            ]);
        }

        if (count($attrNames) === 1) {
            if ($refObjectName !== null) {
                return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_OBJECT_AND_ATTR', [
                    '%object_name%'     => $targetObjectName,
                    '%ref_object_name%' => $refObjectName,
                    '%attr_name%'       => '"' . $attrNames[0] . '"',
                ]);
            }

            return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_ATTR', [
                '%object_name%' => $targetObjectName,
                '%attr_name%'   => '"' . $attrNames[0] . '"',
            ]);
        }

        $lastName = array_pop($attrNames);

        if ($refObjectName !== null) {
            return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_OBJECT_AND_ATTRS', [
                '%object_name%'     => $targetObjectName,
                '%ref_object_name%' => $refObjectName,
                '%attr_list%'       => '"' . implode('", "', $attrNames) . '"',
                '%attr_last%'       => '"' . $lastName . '"',
            ]);
        }

        return $translator->translate('DATASHEET.ERROR.FOREIGN_KEY_IN_USE_WITH_ATTRS', [
            '%object_name%' => $targetObjectName,
            '%attr_list%'   => '"' . implode('", "', $attrNames) . '"',
            '%attr_last%'   => '"' . $lastName . '"',
        ]);
    }
}