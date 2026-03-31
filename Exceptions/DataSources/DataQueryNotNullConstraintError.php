<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a query fails due to a NOT NULL violation within the data source.
 *
 * @author Andrej Kabachnik
 */
class DataQueryNotNullConstraintError extends DataQueryConstraintError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSources\DataQueryFailedError::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '8K2N4L1';
    }

    /**
     * @param MetaObjectInterface $obj
     * @return string|null
     */
    protected function generateMessage(MetaObjectInterface $obj): ?string
    {
        $attrAliases = array_keys($this->getAttributeValues());
        $attrNames = [];

        foreach ($attrAliases as $attrAlias) {
            try {
                $attrNames[] = $obj->getAttribute($attrAlias)->getName();
            } catch (\Throwable $e) {
                // Ungültige Aliases ignorieren
            }
        }

        $translator = $obj->getWorkbench()->getCoreApp()->getTranslator();
        $objectName = '"' . $obj->getName() . '"';

        $attrCount = count($attrNames);

        if ($attrCount === 0) {
            return $translator->translate('DATASHEET.ERROR.REQUIRED_VALUE_MISSING', [
                '%object_name%' => $objectName,
            ]);
        }

        if ($attrCount === 1) {
            return $translator->translate('DATASHEET.ERROR.REQUIRED_VALUE_MISSING_WITH_ATTR', [
                '%object_name%' => $objectName,
                '%attr_name%'   => '"' . $attrNames[0] . '"',
            ]);
        }

        return $translator->translate('DATASHEET.ERROR.REQUIRED_VALUE_MISSING_WITH_ATTRS', [
            '%object_name%' => $objectName,
            '%attr_list%'   => '"' . implode('", "', $attrNames) . '"',
        ]);
    }
}