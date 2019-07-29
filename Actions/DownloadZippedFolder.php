<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\ArchiveManager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * This action packs all files of a given folder into a ZIP archive lets the user download it.
 * 
 * The folder to zip can be either defined statically (`folder_path`) or derived from from
 * the input data of the action (`folder_path_attribute_alias`).
 * 
 * All path properties accept either an absolute path or a path relative to the base installation
 * folder. If you need to define a path relatively to another folder, use the `folder_path_subfolder`
 * property to specify the path between the installation folder and the base folder of your path.
 * 
 * The ZIP file will contain the name of the zipped folder followed by a timestamp.
 * 
 * ## Examples:
 * 
 * Zip and download an app. The path to the app (relative to the `vendor` folder corresponds to
 * it's package name. So we use the package name as a relative path and use the `folder_path_subfolder`
 * property to make sure, it is resolved relatively to the `vendor` folder. Exporting 
 * 
 * ```
 * {
 *  "object_alias": "exface.Core.APP",
 *  "folder_path_attribute_alias": "PACKAGE",
 *  "folder_path_subfolder": "vendor",
 *  "input_rows_min": 1,
 *  "input_rows_max": 1
 * }
 * 
 * ```
 * 
 * Zip and download all config files of the current installation.
 * 
 * ```
 * {
 *  "folder_path": "config"
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DownloadZippedFolder extends AbstractAction
{
    private $folderPath = null;
    
    private $folderPathAttributeAlias = null;
    
    private $folderPathSubfolder = null;
    
    private $zipfilePath = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::DOWNLOAD);
        $this->setInputRowsMax(1);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $zip = $this->createZip($task);
        return ResultFactory::createDownloadResult($task, $zip->getFilePath());
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @return ArchiveManager
     */
    protected function createZip(TaskInterface $task) : ArchiveManager
    {
        $folderPath = $this->getFolderPathAbsolute($task);
        if (empty(StringDataType::findPlaceholders($folderPath)) === false) {
            $folderPath = StringDataType::replacePlaceholders($folderPath, $this->getInputDataSheet($task)->getRow(0));
        }
        $zipPath = $this->getZipPathAbsolute($folderPath);
        if (empty(StringDataType::findPlaceholders($zipPath)) === false) {
            $zipPath = StringDataType::replacePlaceholders($zipPath, $this->getInputDataSheet($task)->getRow(0));
        }
        $zip = new ArchiveManager($this->getWorkbench(), $zipPath);
        $zip->addFolder($folderPath);
        $zip->close();
        return $zip;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasStaticFolderPath() : bool
    {
        return $this->folderPath !== null;
    }
    
    /**
     * 
     * @return string
     */
    public function getZipPathAbsolute(string $srcFolderPath) : string
    {
        if ($this->zipfilePath === null) {
            $this->zipfilePath = $this->getZipPathDefault($srcFolderPath);
        }
        return $this->zipfilePath;
    }
    
    /**
     * Defines a custom path and filename for the created zip file.
     * 
     * The path may include placeholders with names of input data columns.
     * 
     * If not set, the ZIP file will be saved in the cache folder.
     * 
     * @uxon-property zip_path
     * @uxon-type string
     * 
     * @param string $pathAbsoluteOrRelativeToBase
     * @return DownloadZippedFolder
     */
    public function setZipPath(string $pathAbsoluteOrRelativeToBase) : DownloadZippedFolder
    {
        $filemanager = $this->getWorkbench()->filemanager();
        if ($filemanager::pathIsAbsolute($pathAbsoluteOrRelativeToBase)) {
            $this->zipfilePath = $pathAbsoluteOrRelativeToBase;
        } else {
            $this->zipfilePath = $filemanager::pathJoin([$filemanager->getPathToBaseFolder(), $pathAbsoluteOrRelativeToBase]);
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getFolderPathAbsolute(TaskInterface $task) : string
    {
        if ($this->folderPath !== null) {
            return $this->folderPath;
        }
        
        if ($this->isFolderPathBoundToAttribute() === true) {
            $inputData = $this->getInputDataSheet($task);
            $path = $inputData->getColumns()->getByAttribute($inputData->getMetaObject()->getAttribute($this->getFolderPathAttributeAlias()))->getCellValue(0);
        }
        return $this->makeAbsolutePath($path ?? '');
    }
    
    /**
     * Sets a static path to the folder to zip and download.
     * 
     * The path can be either static or relative to the installation folder of the plattform.
     * It also may include placeholders with names of input data columns.
     * 
     * @uxon-property folder_path
     * @uxon-type string
     * 
     * @param string $pathAbsoluteOrRelativeToBase
     * @return DownloadZippedFolder
     */
    public function setFolderPath(string $pathAbsoluteOrRelativeToBase) : DownloadZippedFolder
    {
        $this->folderPath = $this->makeAbsolutePath($pathAbsoluteOrRelativeToBase);
        return $this;
    }
    
    protected function makeAbsolutePath(string $pathAbsoluteOrRelativeToBase) : string
    {
        $filemanager = $this->getWorkbench()->filemanager();
        if ($filemanager::pathIsAbsolute($pathAbsoluteOrRelativeToBase)) {
            return $pathAbsoluteOrRelativeToBase;
        } else {
            return $filemanager::pathJoin([$filemanager->getPathToBaseFolder(), $this->getFolderPathSubfolder() ?? '', $pathAbsoluteOrRelativeToBase]);
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getZipPathDefault(string $srcFolderPath) : string
    {
        return $this->getWorkbench()->filemanager()->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'Downloads' . DIRECTORY_SEPARATOR . $this->getZipNameDefault($srcFolderPath);
    }
    
    /**
     * 
     * @return string
     */
    protected function getZipNameDefault(string $srcFolderPath) : string
    {
        $srcFolderName = pathinfo($srcFolderPath, PATHINFO_BASENAME);
        return $srcFolderName . '_' . date('YmdHis') . '.zip';
    }
    
    /**
     *
     * @return string
     */
    protected function getFolderPathAttributeAlias() : string
    {
        return $this->folderPathAttributeAlias;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isFolderPathBoundToAttribute() : bool
    {
        return $this->folderPathAttributeAlias !== null;
    }
    
    protected function getFolderPathAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFolderPathAttributeAlias());
    }
    
    /**
     * Alias of the attribute, that holds the relative or absolute path to the folder to zip.
     * 
     * The path can be either static or relative to the installation folder of the plattform.
     * 
     * @uxon-property folder_path_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return DownloadZippedFolder
     */
    public function setFolderPathAttributeAlias(string $value) : DownloadZippedFolder
    {
        $this->folderPathAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getFolderPathSubfolder() : ?string
    {
        return $this->folderPathSubfolder;
    }
    
    /**
     * Subfolder path between the installation folder and the base folder when using relative paths.
     * 
     * E.g. `vendor` if you use folder paths relative to the vendor folder.
     * 
     * @uxon-property folder_path_subfolder
     * @uxon-type string
     * 
     * @param string $value
     * @return DownloadZippedFolder
     */
    public function setFolderPathSubfolder(string $value) : DownloadZippedFolder
    {
        $this->folderPathSubfolder = $value;
        return $this;
    }
}