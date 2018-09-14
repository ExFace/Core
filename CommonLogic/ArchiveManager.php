<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use ZipArchive;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\RuntimeException;

class ArchiveManager implements WorkbenchDependantInterface
{

    private $exface = null;

    private $filePath = '';

    private $archive = null;

    public function __construct(Workbench $exface, string $absolutePath)
    {
        $this->exface = $exface;
        $this->archive = new \ZipArchive();
        if ($absolutePath === '' || ! Filemanager::pathIsAbsolute($absolutePath)) {
            throw new UnexpectedValueException('Cannot create archive: invalid or empty path given!');
        }
        $this->filePath = $absolutePath;
        $this->create();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    public function create()
    {
        $folder = pathinfo($this->filePath, PATHINFO_DIRNAME);
        if (! file_exists($folder)) {
            Filemanager::pathConstruct($folder);
        }
        $code = $this->archive->open($this->filePath, ZipArchive::CREATE);
        if ($code !== true) {
            throw new RuntimeException('Failed to open ZIP archive "' . $this->filePath . '": code ' . $code . ' - ' . $this->getErrorMessage($code));
        }
        return $this;
    }

    public function close()
    {
        $this->archive->close();
        return $this;
    }

    public function addFile($path, $subfolder = '')
    {
        $name = pathinfo($path, PATHINFO_BASENAME);
        $this->archive->addFile($path, ($subfolder === '' ? $name : $subfolder . '/' . $name));
        return $this;
    }

    public function createFolder($name, $subfolder = '')
    {
        $this->archive->addEmptyDir(($subfolder === '' ? $name : $subfolder . '/' . $name));
        return $this;
    }

    public function addFolder($path, $archiveSubfolder = '')
    {
        $dir = opendir($path);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                    $this->createFolder($file, $archiveSubfolder);
                    $this->addFolder($path . DIRECTORY_SEPARATOR . $file, ($archiveSubfolder === '' ? $file : $archiveSubfolder . '/' . $file));
                } else {
                    $this->addFile($path . DIRECTORY_SEPARATOR . $file, $archiveSubfolder);
                }
            }
        }
        closedir($dir);
        return $this;
    }

    /**
     * This method is experimental
     */
    public function download()
    {
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=" . basename($this->filePath));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile($this->filePath);
        exit();
    }

    /**
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     *
     * @return \ZipArchive
     */
    protected function getArchive()
    {
        return $this->archive;
    }
    
    protected function getErrorMessage($code)
    {
        switch ($code)
        {
            case 0:
                return 'No error';
                
            case 1:
                return 'Multi-disk zip archives not supported';
                
            case 2:
                return 'Renaming temporary file failed';
                
            case 3:
                return 'Closing zip archive failed';
                
            case 4:
                return 'Seek error';
                
            case 5:
                return 'Read error';
                
            case 6:
                return 'Write error';
                
            case 7:
                return 'CRC error';
                
            case 8:
                return 'Containing zip archive was closed';
                
            case 9:
                return 'No such file';
                
            case 10:
                return 'File already exists';
                
            case 11:
                return 'Can\'t open file';
                
            case 12:
                return 'Failure to create temporary file';
                
            case 13:
                return 'Zlib error';
                
            case 14:
                return 'Malloc failure';
                
            case 15:
                return 'Entry has been changed';
                
            case 16:
                return 'Compression method not supported';
                
            case 17:
                return 'Premature EOF';
                
            case 18:
                return 'Invalid argument';
                
            case 19:
                return 'Not a zip archive';
                
            case 20:
                return 'Internal error';
                
            case 21:
                return 'Zip archive inconsistent';
                
            case 22:
                return 'Can\'t remove file';
                
            case 23:
                return 'Entry has been deleted';
                
            default:
                return 'An unknown error has occurred('.intval($code).')';
        }
    }
}