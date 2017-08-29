<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\CommonLogic\Filemanager;

class FileContentsDataQuery extends AbstractDataQuery
{

    private $base_path = null;

    private $path_absolute = null;

    private $path_relative = null;

    public function getFileInfo()
    {
        return new \SplFileInfo($this->getPathAbsolute());
    }

    public function getBasePath()
    {
        return $this->base_path;
    }

    public function setBasePath($absolute_path)
    {
        $this->base_path = Filemanager::pathNormalize($absolute_path);
        return $this;
    }

    public function getPathAbsolute()
    {
        if (is_null($this->path_absolute)) {
            if (! is_null($this->path_relative) && $this->getBasePath()) {
                return Filemanager::pathJoin(array(
                    $this->getBasePath(),
                    $this->getPathRelative()
                ));
            }
        }
        return $this->path_absolute;
    }

    public function setPathAbsolute($value)
    {
        $this->path_absolute = Filemanager::pathNormalize($value);
        return $this;
    }

    public function getPathRelative()
    {
        if (is_null($this->path_relative)) {
            return $this->getPathAbsolute() && $this->getBasePath() ? str_replace($this->getBasePath() . '/', '', $this->getPathAbsolute()) : null;
        }
        return $this->path_relative;
    }

    public function setPathRelative($value)
    {
        $this->path_relative = Filemanager::pathNormalize($value);
        return $this;
    }
}
?>