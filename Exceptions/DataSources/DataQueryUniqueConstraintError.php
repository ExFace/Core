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
        $lastName = array_pop($attrNames);
        $translator = $obj->getWorkbench()->getCoreApp()->getTranslator();
        if (empty($attrNames)) {
            $msg = $translator->translate('DATASHEET.ERROR.DATA_OBJECT_EXISTS', [
                '%object_name%' =>  '"' . $obj->getName() . '"'
            ]);
        } else {
            $msg = $translator->translate('DATASHEET.ERROR.DATA_OBJECT_EXISTS_WITH_ATTRS', [
                '%object_name%' => '"' . $obj->getName() . '"',
                '%attr_list%' => '"' . implode('", "', $attrNames) . '"',
                '%attr_last%' => '"' . $lastName . '"'
            ]);
        }
        return $msg;
    }
}