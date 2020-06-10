<?php

namespace exface\Core\ModelLoaders;



use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\DataConnectors\MsSqlConnector;

/**
 * 
 * @author Ralf Mulansky
 *
 */
class MsSqlModelLoader extends SqlModelLoader
{
    /**
     * Ensures that binary UID fields are selected as 0xNNNNN to be compatible with the internal binary notation in ExFace
     *
     * @param string $field_name
     * @return string
     */
    protected function buildSqlUuidSelector($field_name)
    {
        return "CONVERT(VARCHAR(200), {$field_name}, 1)";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::buildSqlGroupConcat()
     */
    protected function buildSqlGroupConcat(string $sqlColumn, string $sqlFrom, string $sqlWhere)
    {
        return <<<SQL
        
        SELECT STUFF(CAST(( SELECT [text()] = ', ' + {$sqlColumn}
        FROM {$sqlFrom}
        WHERE {$sqlWhere}
        FOR XML PATH(''), TYPE) AS VARCHAR(1000)), 1, 2, '')
SQL;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::setDataConnection()
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        if (! ($connection instanceof MsSqlConnector)) {
            throw new \RuntimeException('Incompatible connector "' . $connection->getPrototypeClassName() . '" used for the model loader "' . get_class($this) . '": expecting a MsSqlConnector or a drivative.');
        }
        return parent::setDataConnection($connection);
    }
}

?>