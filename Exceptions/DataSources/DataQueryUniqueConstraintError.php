<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a query fails due to a constraint violation within the data source.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryUniqueConstraintError extends DataQueryConstraintError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSources\DataQueryFailedError::getDefaultLogLevel()
     */
    public function getDefaultLogLevel(){
        return LoggerInterface::ERROR;
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
            return $translator->translate('DATASHEET.ERROR.DATA_OBJECT_EXISTS', [
                '%object_name%' => $objectName,
            ]);
        }

        if ($attrCount === 1) {
            return $translator->translate('DATASHEET.ERROR.DATA_OBJECT_EXISTS_WITH_ATTR', [
                '%object_name%' => $objectName,
                '%attr_name%'   => '"' . $attrNames[0] . '"',
            ]);
        }

        return $translator->translate('DATASHEET.ERROR.DATA_OBJECT_EXISTS_WITH_ATTRS', [
            '%object_name%' => $objectName,
            '%attr_list%'   => '"' . implode('", "', $attrNames) . '"',
        ]);
    }
}