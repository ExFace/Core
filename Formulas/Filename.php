<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\FilePathDataType;

/**
 * Extracts the filename from a given path - with or without the extension
 *
 * @author Andrej Kabachnik
 *        
 */
class Filename extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * @param string $filePath
     * @param bool $withExtension
     * @return string|null
     */
    function run(string $filePath = null, bool $withExtension = false)
    {
        if ($filePath === '' || $filePath === null) {
            return $filePath;
        }

        return FilePathDataType::findFileName($filePath, $withExtension);
    }
}