<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\ArchiveManager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\AbstractAction;

/**
 * This Action adds all files of a designated folder into a ZIP Archive.
 * 
 * @author Andrej Kabachnik
 *
 */
class DownloadZippedFolder extends AbstractAction
{
    private $folderPath = null;
    
    private $zipfilePath = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::FILE_ARCHIVE_O);
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
        $zip = new ArchiveManager($this->getWorkbench(), $this->getZipPath());
        $zip->addFolder($this->getFolderPathAbsolute());
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
    public function getZipPathAbsolute() : string
    {
        if ($this->zipfilePath === null) {
            $this->zipfilePath = $this->getZipPathDefault();
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
     * @return string|null
     */
    public function getFolderPathAbsolute()
    {
        return $this->folderPath;
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
        $filemanager = $this->getWorkbench()->filemanager();
        if ($filemanager::pathIsAbsolute($pathAbsoluteOrRelativeToBase)) {
            $this->folderPath = $pathAbsoluteOrRelativeToBase;
        } else {
            $this->folderPath = $filemanager::pathJoin([$filemanager->getPathToBaseFolder(), $pathAbsoluteOrRelativeToBase]);
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getZipPathDefault() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'Downloads' . DIRECTORY_SEPARATOR . $this->getZipNameDefault();
    }
    
    /**
     * 
     * @return string
     */
    protected function getZipNameDefault() : string
    {
        return date('Ymd_Hmi') . '.zip';
    }
}
?>