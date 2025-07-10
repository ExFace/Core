<?php

namespace exface\Core\CommonLogic\AppInstallers\Plugins;

use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * Reads a list of files, specified by a SELECT statement, and writes their contents to the database according
 * to a specified UPDATE statement.
 * 
 * ## Parameters
 * 
 * - `selectStatement`:Provide a quoted SELECT statement that fetches both the file paths and the unique keys required for the UPDATE statement.
 * - `updateStatement`: Provide a quoted UPDATE statement that writes the contents read from the files into the database. Use the `uKeyToken` and `contentToken` placeholders
 * to control where in your statement the respective data should be inserted (see examples below).
 * - `filePathColumn`: Specify the name of the SELECT column that contains the file paths.
 * - `uniqueKeyColumn`: Specify the name of the SELECT column that contains the unique keys.
 * - `dataSourceAlias`: Files will be accessed via this data source.
 * - `uKeyToken` = '[#key#]' (optional): Use this placeholder to insert unique keys from your SELECT into your UPDATE statement.
 * - `contentToken` = '[#content#]' (optional): Use this placeholder to insert the file contents into your UPDATE statement.
 * - `directorySeparator` = '/' (optional): Specify the directory separator for your file system.
 * 
 * ## Usage
 * 
 * This plugin only works for specific App-Installers (as of 2025-06-06 only for SqlDataBaseInstallers). To call a plugin you
 * must enclose it within a multi-line comment, begin with the @ `[at]` symbol and use the following syntax `[at]plugin.PLUGINCALL(ARG1, ARG2, ..., ARG N);`. 
 * 
 * ```
 * 
 * /*[at]plugin.WriteFilesToSqlRows(
 *      'SELECT body_file_path, HEX(oid) as oid FROM etl_webservice_request;',
 *      'UPDATE etl_webservice_request SET http_body = [#content#] WHERE HEX(oid) = [#key#];',
 *      'body_file_path',
 *      'oid',
 *      'axenox.ETL.dataflow_upload_storage',
 *      '[#key#]',
 *      '[#content#]'
 * );*\/
 * 
 * ```
 * 
 */
class WriteFilesToSqlRows extends AbstractSqlInstallerPlugin
{
    /**
     * @inheritDoc
     */
    public function run(
        string $selectStatement = null,
        string $updateStatement = null,
        string $filePathColumn = null,
        string $uniqueKeyColumn = null,
        string $dataSourceAlias = null,
        string $uKeyToken = '[#key#]',
        string $contentToken = '[#content#]',
        string $directorySeparator = '/')
    {
        if( $selectStatement === null ||
            $updateStatement === null ||
            $dataSourceAlias === null ||
            $uniqueKeyColumn === null ||
            $filePathColumn === null ||
            !$this->hasConnector()) {
            
            return null;
        }
        
        $uKeyToken = '/' . preg_quote($uKeyToken) . '/';
        $contentToken = '/' . preg_quote($contentToken) . '/';
        
        if( !preg_match($uKeyToken, $updateStatement) ||
            !preg_match($contentToken, $updateStatement)) {
            return null;
        }
        
        $selectResult = $this->getConnector()->runSql($selectStatement)->getResultArray();
        $dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $dataSourceAlias);
        
        $pathArray = [];
        $uKeyArray = [];
        foreach ($selectResult as $row) {
            if(!key_exists($uniqueKeyColumn, $row)) {
                throw new FormulaError('Cannot load from files: SELECT does not contain column "' . $uniqueKeyColumn . '" (should provide unique keys)!');
            }

            if(!key_exists($filePathColumn, $row)) {
                throw new FormulaError('Cannot load from files: SELECT does not contain column "' . $filePathColumn . '" (should provide file paths)!');
            }

            $uKeyArray[] = $row[$uniqueKeyColumn];
            $pathArray[] = $row[$filePathColumn];
        }
        
        $readQuery = $this->loadFiles(
            $dataSource->getConnection(),
            $pathArray,
            $directorySeparator
        );

        $i = 0;
        $sqlStatement = '';
        $connector = $this->getConnector();

        // Assemble the UPDATE statement.
        // Results from the load data query will be matched to unique keys according to their index.
        foreach ($readQuery->getFiles() as $file) {
            $key = $connector->escapeString($uKeyArray[$i++]);
            $rowStatement = preg_replace($uKeyToken, "'" . $key . "'", $updateStatement);

            $content = $connector->escapeString($file->openFile()->read());
            $rowStatement = preg_replace($contentToken, "'" . $content . "'", $rowStatement);

            $sqlStatement .= $rowStatement  . ' ';
        }
        
        if(empty($sqlStatement)) {
            throw new FormulaError('Cannot load from files: No files found at the specified paths! Check your paths or data source.');
        }

        $this->getConnector()->runSql($sqlStatement, true);
    }

    /**
     * Loads files via the specified connection, using the path array and returns
     * the performed query.
     * 
     * @param DataConnectionInterface $dataConnection
     * @param array                   $pathArray
     * @param string                  $directorySeparator
     * @return DataQueryInterface
     */
    protected function loadFiles(
        DataConnectionInterface $dataConnection,
        array $pathArray,
        string $directorySeparator) : DataQueryInterface
    {
        if (empty($pathArray)) {
            throw new FormulaError('Cannot save to files: no paths specified!');
        }
        
        foreach ($pathArray as $path) {
            if ($path === null || $path === '') {
                throw new FormulaError('Cannot save to file: path is empty!');
            }
        }
        
        $query = new FileReadDataQuery($directorySeparator);
        
        foreach ($pathArray as $path) {
            if ($path === null) {
                continue;
            }
            
            $query->addFilePath($path);
        }

        return $dataConnection->query($query);
    }
}