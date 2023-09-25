<?php
namespace exface\Core\CommonLogic\DataQueries;;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSources\FileDataQueryInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

class FileReadDataQuery extends AbstractDataQuery implements FileDataQueryInterface
{
    private $folders = array();
    
    private $folderDepth = null;

    private $basePath = null;

    private $fullScanRequired = false;
    
    private $filenamePatterns = null;
    
    private $resultGenerator = null;
    
    private $resultCache = null;
    
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
    public function getFolders() : array
    {
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
        $this->folders[] = $relativeOrAbsolutePath;
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
        $this->basePath = Filemanager::pathNormalize($absolutePath);
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
}