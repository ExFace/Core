<?php
namespace exface\Core\CommonLogic\DataQueries;;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSources\FileDataQueryInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\DataTypes\FilePathDataType;

class FileReadDataQuery extends AbstractDataQuery implements FileDataQueryInterface
{
    private $folders = [];
    
    private $folderDepth = null;

    private $basePath = null;

    private $fullScanRequired = false;
    
    private $filenamePatterns = null;
    
    private $resultGenerator = null;
    
    private $resultCache = null;
    
    private $directorySeparator = null;
    
    /**
     * 
     * @param string $directorySeparator
     */
    public function __construct(string $directorySeparator = '/')
    {
        $this->directorySeparator = $directorySeparator;
    }
    
    /**
     * 
     * @param iterable $fileInfoGenerator
     * @return FileReadDataQuery
     */
    public function withResult(iterable $fileInfoGenerator) : FileReadDataQuery
    {
        $instance = clone $this;
        $instance->resultGenerator = $fileInfoGenerator;
        return $instance;
    }
    
    /**
     * 
     * @return bool
     */
    public function isPerformed() : bool
    {
        return $this->resultGenerator !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\FileDataQueryInterface::getFiles()
     */
    public function getFiles() : iterable
    {
        if ($this->resultCache !== null) {
            return $this->resultCache;
        }
        
        if ($this->resultGenerator === null) {
            throw new DataQueryFailedError($this, 'Cannot access result of file query before it has been performed via data connector!');
        }
        
        $this->resultCache = [];
        foreach ($this->resultGenerator as $file) {
            $this->resultCache[] = $file;
            yield $file;
        }
        return;
    }
    
    /**
     * 
     * @return string[]|NULL
     */
    public function getFilenamePatterns() : ?array
    {
        return $this->filenamePatterns;
    }
    
    /**
     * 
     * @param string $value
     * @return FileReadDataQuery
     */
    public function addFilenamePattern(string $value) : FileReadDataQuery
    {
        $this->filenamePatterns[] = $value;
        return $this;
    }

    /**
     * 
     * @return string[]
     */
    public function getFolders(bool $withBasePath = false) : array
    {
        if ($withBasePath) {
            $paths = [];
            $basePath = $this->getBasePath() ?? '';
            $sep = $this->getDirectorySeparator();
            foreach ($this->folders as $path) {
                $paths[] = FilePathDataType::makeAbsolute($path, $basePath, $sep);
            }
            return $paths;
        }
        return $this->folders;
    }

    /**
     * 
     * @param string[] $patternArray
     * @return FileReadDataQuery
     */
    public function setFolders(array $patternArray) : FileReadDataQuery
    {
        $this->folders = $patternArray;
        return $this;
    }

    /**
     * 
     * @param string $relativeOrAbsolutePath
     * 
     * @return FileReadDataQuery
     */
    public function addFolder(string $relativeOrAbsolutePath) : FileReadDataQuery
    {
        $path = FilePathDataType::normalize($relativeOrAbsolutePath, $this->getDirectorySeparator());
        
        // Also try to filter out paths that match paterns in other paths
        if (strpos($path, '*') !== false) {
            foreach ($this->folders as $i => $otherPath) {
                // Remove any existing paths, that match the new wildcard pattern as they
                // will be included anyhow
                if ($otherPath !== $path && FilePathDataType::matchesPattern($otherPath, $path)) {
                    unset($this->folders[$i]);
                }
            }
        }
        $this->folders[] = $relativeOrAbsolutePath;
        
        // Make sure not to have duplicate paths as some libs like Symfony FileFinder will yield results 
        // for each path separately
        $this->folders = array_unique($this->folders);
        
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    public function getBasePath() : ?string
    {
        return $this->basePath;
    }

    /**
     * 
     * @param string $absolutePath
     * @return FileReadDataQuery
     */
    public function setBasePath(string $absolutePath) : FileReadDataQuery
    {
        $this->basePath = Filemanager::pathNormalize($absolutePath, $this->getDirectorySeparator());
        return $this;
    }

    /**
     * 
     * @param bool $value
     * @return FileReadDataQuery
     */
    public function setFullScanRequired(bool $value) : FileReadDataQuery
    {
        $this->fullScanRequired = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isFullScanRequired() : bool
    {
        return $this->fullScanRequired;
    }

    /**
     *
     * {@inheritdoc} 
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $finder_tab = $debug_widget->createTab();
        $finder_tab->setCaption('Finder');
        /* @var $finder_widget \exface\Core\Widgets\Html */
        $finder_widget = WidgetFactory::createFromUxonInParent($finder_tab, new UxonObject([
            'widget_type' => 'Markdown',
            'value' => $this->toMarkdown(),
            'height' => '100%',
            'width' => '100%'
        ]));
        $finder_tab->addWidget($finder_widget);
        $debug_widget->addTab($finder_tab);
        return $debug_widget;
    }

    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        $md = '';
        $md .= "Folders:\n" . implode("\n- ", $this->getFolders());
        return $md;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getFolderDepth() : ?int
    {
        return $this->folderDepth;
    }
    
    /**
     * 
     * @param int $value
     * @return FileReadDataQuery
     */
    public function setFolderDepth(?int $value) : FileReadDataQuery
    {
        $this->folderDepth = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\FileDataQueryInterface::getDirectorySeparator()
     */
    public function getDirectorySeparator() : string
    {
        return $this->directorySeparator;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::countAffectedRows()
     */
    public function countAffectedRows()
    {
        if (! $this->isPerformed()) {
            return 0;
        }
        if ($this->resultCache === null) {
            return 0;
        }
        return count($this->resultCache);
    }
}