<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

class WriteFilesToSqlRows extends AbstractSqlInstallerPlugin
{
    /**
     * @inheritDoc
     */
    public function run(
        string $selectStatement = null,
        string $updateStatement = null,
        string $uniqueKeyColumn = null,
        string $filePathColumn = null,
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
                throw new FormulaError('Cannot save to files: SELECT does not contain column "' . $uniqueKeyColumn . '" (should provide unique keys)!');
            }

            if(!key_exists($filePathColumn, $row)) {
                throw new FormulaError('Cannot save to files: SELECT does not contain column "' . $filePathColumn . '" (should provide file paths)!');
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
        
        foreach ($readQuery->getFiles() as $file) {
            $key = $connector->escapeString($uKeyArray[$i++]);
            $rowStatement = preg_replace($uKeyToken, "'" . $key . "'", $updateStatement);
            
            $content = $connector->escapeString($file->openFile()->read());
            $rowStatement = preg_replace($contentToken, "'" . $content . "'", $rowStatement);
            
            $sqlStatement .= $rowStatement  . ' ';
        }
        
        $this->getConnector()->runSql($sqlStatement, true);
    }

    /**
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