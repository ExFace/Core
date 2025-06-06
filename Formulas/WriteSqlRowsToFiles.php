<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\DataQueries\FileWriteDataQuery;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;

class WriteSqlRowsToFiles extends AbstractSqlInstallerPlugin
{
    /**
     * @inheritDoc
     */
    public function run(
        string $selectStatement = null, 
        string $dataSourceAlias = null,
        string $filePathColumn = null,
        string $contentColumn = null,
        string $directorySeparator = '/')
    {
        if( $selectStatement === null ||
            $dataSourceAlias === null ||
            $filePathColumn === null ||
            $contentColumn === null ||
            !$this->hasConnector()) {
            
            return null;
        }
        
        $selectResult = $this->getConnector()->runSql($selectStatement)->getResultArray();
        $dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $dataSourceAlias);
        
        $pathArray = [];
        $contentArray = [];
        foreach ($selectResult as $row) {
            if(!key_exists($filePathColumn, $row)) {
                throw new FormulaError('Cannot save to files: SELECT does not contain column "' . $filePathColumn . '" (should provide file paths)!');
            } else {
                $pathArray[] = $row[$filePathColumn];
            }

            if(!key_exists($contentColumn, $row)) {
                throw new FormulaError('Cannot save to files: SELECT does not contain column "' . $contentColumn . '" (should provide contents)!');
            } else {
                $contentArray[] = $row[$contentColumn];
            }
        }
        
        $this->saveToFiles(
            $dataSource->getConnection(),
            $pathArray,
            $contentArray,
            $directorySeparator
        );
    }

    protected function saveToFiles(
        DataConnectionInterface $dataConnection,
        array $pathArray,
        array $contentArray,
        string $directorySeparator) : void
    {
        if (empty($pathArray)) {
            throw new FormulaError('Cannot save to files: no paths specified!');
        }
        
        foreach ($pathArray as $path) {
            if ($path === null || $path === '') {
                throw new FormulaError('Cannot save to file: path is empty!');
            }
        }
        
        $query = new FileWriteDataQuery($directorySeparator);
        
        if (count($pathArray) !== count($contentArray)) {
            throw new FormulaError('Cannot update files: Only ' . count($contentArray) . ' of ' . count($pathArray) . ' have content!');
        }
        
        foreach ($pathArray as $i => $path) {
            if ($path === null) {
                continue;
            }

            $content = $contentArray[$i];
            // See if empty content is feasible for the expected mime type!
            // E.g. empty text files are OK, but an empty jpeg cannot be correct.
            if (empty($content)) {
                $ext = FilePathDataType::findExtension($path);
                if ($ext) {
                    $type = MimeTypeDataType::guessMimeTypeOfExtension($ext);
                    if (MimeTypeDataType::isBinary($type)) {
                        throw new FormulaError('Cannot create empty file "' . $path . '" of type "' . $type . '" - files of this type may not be empty!');
                    }
                }
            }
            
            $query->addFileToSave($path, $content);
        }

        $dataConnection->query($query);
    }
}