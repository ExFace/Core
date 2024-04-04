<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\DataTypes\FilePathDataType;

/**
 * Contains the address of a single file and allows the data connector to load its info and contents 
 * conceiling the file system, etc.
 * 
 * The query builer sets the address of the file it needs and the connector resolves it to
 * an splFileInfo and/or contents reader. Thus, the query builder does not need to bother,
 * how exactly the files are stored - in a file system, in a DB, or wherever else. The latter
 * is only known to the data connector used.
 * 
 * @author Andrej Kabachnik
 *
 */
class FileContentsDataQuery extends AbstractDataQuery
{

    private $base_path = null;

    private $path_absolute = null;

    private $path_relative = null;
    
    /**
     * 
     * @var \SplFileInfo|NULL
     */
    private $file_info = null;
    
    private $file_contents = null;
    
    private $file_reader = null;
    
    private $file_exists = null;

    /**
     * 
     * @return string|NULL
     */
    public function getBasePath() : ?string
    {
        return $this->base_path;
    }

    /**
     * 
     * @param string $absolute_path
     * @return FileContentsDataQuery
     */
    public function setBasePath(string $absolute_path) : FileContentsDataQuery
    {
        $this->base_path = Filemanager::pathNormalize($absolute_path);
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    public function getPathAbsolute() : ?string
    {
        if ($this->path_absolute === null) {
            if ($this->path_relative !== null && $this->getBasePath() !== null) {
                return Filemanager::pathJoin(array(
                    $this->getBasePath(),
                    $this->getPathRelative()
                ));
            }
            if ($this->file_info !== null) {
                return $this->file_info->getPathname();
            }
        }
        return $this->path_absolute;
    }
    
    /**
     * 
     * @param string $relativeOrAbsolute
     * @return FileContentsDataQuery
     */
    public function setPath(string $relativeOrAbsolute) : FileContentsDataQuery
    {
        if (UrlDataType::isAbsolute($relativeOrAbsolute) || FilePathDataType::isAbsolute($relativeOrAbsolute)) {
            $this->setPathAbsolute($relativeOrAbsolute);
        } else {
            $this->setPathRelative($relativeOrAbsolute);
        }
        return $this;
    }

    /**
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\DataQueries\FileContentsDataQuery
     */
    public function setPathAbsolute(string $value) : FileContentsDataQuery
    {
        $this->path_absolute = Filemanager::pathNormalize($value);
        $this->file_exists = null;
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    public function getPathRelative() : ?string
    {
        if ($this->path_relative === null) {
            if ($this->getBasePath() !== null && $this->getPathAbsolute() !== null) {
                return str_replace($this->getBasePath() . '/', '', $this->getPathAbsolute());
            }
        }
        return $this->path_relative;
    }

    /**
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\DataQueries\FileContentsDataQuery
     */
    public function setPathRelative(string $value) : FileContentsDataQuery
    {
        $this->path_relative = Filemanager::pathNormalize($value);
        $this->file_exists = null;
        return $this;
    }
    
    /**
     *
     * @return \SplFileInfo|NULL
     */
    public function getFileInfo() : ?\SplFileInfo
    {
        return $this->file_info;
    }
    
    /**
     * 
     * @param \SplFileInfo $fileInfo
     * @return FileContentsDataQuery
     */
    public function setFileInfo(\SplFileInfo $fileInfo) : FileContentsDataQuery
    {
        $this->file_info = $fileInfo;
        $this->file_exists = true;
        return $this;
    }
    
    /**
     * 
     * @throws DataQueryFailedError
     * @return bool
     */
    public function getFileExists() : bool
    {
        if ($this->file_exists === null) {
            throw new DataQueryFailedError($this, 'Cannot request contents of file query before running the query in through a connector!');
        }
        return $this->file_exists;
    }
    
    public function setFileExists(bool $trueOrFalse) : FileContentsDataQuery
    {
        $this->file_exists = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @throws FileNotReadableError
     * @return string|NULL
     */
    public function getFileContents() : ?string
    {
        if ($this->file_contents !== null) {
            return $this->file_contents;
        }
        if ($this->file_reader !== null) {
            $func = $this->file_reader;
            return $func($this);
        }
        if ($this->getFileExists() === false) {
            return null;
        }
        return null;
    }
    
    /**
     * 
     * @param mixed|callable $scalarOrCallback
     * @return FileContentsDataQuery
     */
    public function setFileContents($scalarOrCallback) : FileContentsDataQuery
    {
        $this->file_contents = null;
        $this->file_reader = null;
        $this->file_exists = true;
        if (is_callable($scalarOrCallback)) {
            $this->file_reader = $scalarOrCallback;
        } else {
            $this->file_contents = $scalarOrCallback;
        }
        return $this;
    }
}