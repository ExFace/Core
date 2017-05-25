<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\ExfaceClassInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use ZipArchive;

class ArchiveManager implements ExfaceClassInterface
{

    private $exface = null;

    private $filePath = '';

    private $archive = null;

    private $mode = ZipArchive::CREATE;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
        $this->archive = new \ZipArchive();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    public function archiveOpen()
    {
        $this->archive->open($this->filePath, $this->mode);
    }

    public function archiveClose()
    {
        $this->archive->close();
    }

    public function addFileFromSource($source)
    {
        $dirPath = pathinfo($this->filePath)['dirname'] . DIRECTORY_SEPARATOR;
        $this->archive->addFile($source, str_replace($dirPath, "", $source));
    }

    public function addFolder($name)
    {
        $this->archive->addEmptyDir(str_replace($this->filePath, "", $name));
        return $name;
    }

    public function addFolderFromSource($sourcePath)
    {
        try {
            $dirPath = pathinfo($this->filePath)['dirname'] . DIRECTORY_SEPARATOR;
            $dir = opendir($sourcePath);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($sourcePath . DIRECTORY_SEPARATOR . $file)) {
                        $this->addFolderFromSource($sourcePath . DIRECTORY_SEPARATOR . $file);
                    } else {
                        $this->addFileFromSource($sourcePath . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            closedir($dir);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function fileAdd()
    {}

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
     * @param string $filePath            
     */
    public function setFilePath($filePath)
    {
        if ($filePath)
            $this->filePath = $filePath;
        $this->archiveOpen();
    }

    /**
     *
     * @return null
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     *
     * @param null $archive            
     */
    public function setArchive($archive)
    {
        $this->archive = $archive;
    }
}