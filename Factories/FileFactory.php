<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Filesystem\DataSourceFileInfo;
use exface\Core\CommonLogic\Filesystem\LocalFileInfo;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Instantiates FileInfo objects
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class FileFactory extends AbstractStaticFactory
{
    /**
     *
     * @param MetaObjectSelectorInterface $selector
     * @return MetaObjectInterface
     */
    public static function createFileInfoFromPath(WorkbenchInterface $workbench, string $absolutePath) : FileInfoInterface
    {
        switch (true) { 
            case StringDataType::startsWith($absolutePath, DataSourceFileInfo::SCHEME):
                $fileInfo = new DataSourceFileInfo($absolutePath, $workbench);
                break;
            case FilePathDataType::isAbsolute($absolutePath):
                $fileInfo = new LocalFileInfo(new \SplFileInfo($absolutePath));
                break;
            default:
                throw new RuntimeException('Cannot treat "' . $absolutePath . '" as a file: invalid/unknown path syntax!');
        }
        return $fileInfo;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @param string $uid
     * @return \exface\Core\Interfaces\Filesystem\FileInfoInterface
     */
    public static function createFileInfoFromObjectAndUid(MetaObjectInterface $object, string $uid) : FileInfoInterface
    {
        return DataSourceFileInfo::fromObjectAndUID($object, $uid);
    }
}