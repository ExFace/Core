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

/**
 * This Action adds all files of a designated folder into a ZIP Archive.
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
        $zip = new ArchiveManager($this->getWorkbench(), $this->getZipPathAbsolute($folderPath));
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
        return $this->makeAbsolutePath($path);
    }
    
    /**
     * Sets a constant path to the folder to zip and download.
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
     * Subfolder path between the base installation folder and the folder used as base of a relativ path.
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