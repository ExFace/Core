<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\DataConnectors\FileContentsConnector;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Reads the CSV log files of the workbench in every format version known to `CsvLogConfig`.
 * 
 * Differences to the regular `CsvBuilder`:
 * 
 * - The format (columns, delimiter, enclosure, escape) is taken from `CsvLogConfig` for the version in the file
 * name (e.g. `2026-11-03.v1.log`). Data address properties of the object are ignored.
 * - Attribute data addresses are column names as in `CsvLogConfig` (e.g. `id`, `level_name`, `datetime`)
 * instead of column indexes. Columns, that do not exist in the version of the file, are read as empty.
 * - If the requested file does not exist, the newest existing version of the same base name is read instead
 * (e.g. `logs/2026-11-03.log` => `logs/2026-11-03.v1.log`). This keeps paths built from a date alone working
 * (e.g. `MONITOR_ERROR.LOG_FILE`). File properties like `_FILEPATH_RELATIVE` refer to the file actually read.
 * 
 * @author Georg Bieger
 *
 */
class LogCsvBuilder extends CsvBuilder
{
    private string $version = CsvLogConfig::CURRENT_VERSION;
    
    private static ?array $allColumns = null;
    
    /**
     * Resolves the log file (see class description), performs the query and remembers the version of the file.
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\CsvBuilder::queryFile()
     */
    protected function queryFile(DataConnectionInterface $data_connection) : FileContentsDataQuery
    {
        $query = $this->buildQuery();
        
        // The base path of the connection is only known after connecting, but the path must be resolved before
        // querying: the connector might be configured to throw an error if the requested file does not exist.
        if ($data_connection instanceof FileContentsConnector) {
            if (! $data_connection->isConnected()) {
                $data_connection->connect();
            }
            if (! $query->getBasePath() && $data_connection->getBasePath() !== null) {
                $query->setBasePath($data_connection->getBasePath());
            }
        }
        
        $absolutePath = $query->getPathAbsolute();
        if ($absolutePath !== null && ! is_file($absolutePath) && null !== $fallback = $this->findFallbackFile($absolutePath)) {
            $requestedPath = $this->getPathForObject($this->getMainObject());
            $basePath = $query->getBasePath();
            $query = new FileContentsDataQuery();
            if ($basePath !== null) {
                $query->setBasePath($basePath);
            }
            // Only replace the file name, so the path stays relative if it was relative.
            $query->setPath(substr($requestedPath, 0, -strlen(basename($requestedPath))) . basename($fallback));
        }
        
        $query = $data_connection->query($query);
        $this->version = CsvLogConfig::getVersionFromFileName($query->getPathAbsolute() ?? '');
        return $query;
    }
    
    /**
     * Returns the newest existing log file with the same base name and extension in the same folder or NULL if there is none.
     * 
     * @param string $absolutePath
     * @return string|NULL
     */
    protected function findFallbackFile(string $absolutePath) : ?string
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);
        if ($extension === '') {
            return null;
        }
        $files = CsvLogConfig::findFiles(dirname($absolutePath), CsvLogConfig::getBaseName($absolutePath), $extension);
        return empty($files) ? null : end($files);
    }
    
    /**
     * Returns the format version of the file read by the last query.
     * 
     * @return string
     */
    protected function getVersion() : string
    {
        return $this->version;
    }
    
    /**
     * The columns of the file version followed by all other known columns, so that every record has every
     * known column (missing ones are filled with NULL by the CSV reader).
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\Traits\CsvBuilderTrait::getCsvHeader()
     */
    protected function getCsvHeader() : array
    {
        $columns = CsvLogConfig::getColumns($this->getVersion());
        return array_merge(
            $columns, 
            array_values(
                array_diff(
                    self::getAllColumns(),
                    $columns
                )
            )
        );
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\Traits\CsvBuilderTrait::isCsvColumnAddress()
     */
    protected function isCsvColumnAddress(string $dataAddress) : bool
    {
        return in_array($dataAddress, self::getAllColumns(), true);
    }
    
    /**
     * 
     * @return string[]
     */
    private static function getAllColumns() : array
    {
        if (self::$allColumns === null) {
            $all = [];
            foreach (array_keys(CsvLogConfig::VERSIONS) as $version) {
                $all = array_merge($all, CsvLogConfig::getColumns((string) $version));
            }
            self::$allColumns = array_values(array_unique($all));
        }
        return self::$allColumns;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\CsvBuilder::getDelimiter()
     */
    protected function getDelimiter(MetaObjectInterface $object) : string
    {
        return CsvLogConfig::getDelimiter($this->getVersion());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\CsvBuilder::getEnclosure()
     */
    protected function getEnclosure(MetaObjectInterface $object) : string
    {
        return CsvLogConfig::getEnclosure($this->getVersion());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\Traits\CsvBuilderTrait::getEscape()
     */
    protected function getEscape(MetaObjectInterface $object) : ?string
    {
        return CsvLogConfig::getEscape($this->getVersion());
    }
    
    /**
     * Log files never have a header row.
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\CsvBuilder::getHeaderRowsNumber()
     */
    protected function getHeaderRowsNumber(MetaObjectInterface $object) : int
    {
        return 0;
    }
}