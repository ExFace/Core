<?php
namespace exface\Core\CommonLogic\DataQueries;;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\DataSources\FileDataQueryInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

class FileWriteDataQuery extends AbstractDataQuery implements FileDataQueryInterface
{
    private $basePath = null;
    
    private $resultGenerator = null;
    
    private $filesToSave = [];
    
    private $filesToDelete = [];
    
    private $deleteEmptyFolders = false;
    
    private $filesTouched = 0;
    
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
     * @return FileWriteDataQuery
     */
    public function withResult(iterable $fileInfoGenerator) : FileWriteDataQuery
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
    public function setBasePath(string $absolutePath) : FileWriteDataQuery
    {
        $this->basePath = Filemanager::pathNormalize($absolutePath, $this->getDirectorySeparator());
        return $this;
    }

    /**
     *
     * {@inheritdoc} 
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $finder_tab = $debug_widget->createTab();
        $finder_tab->setCaption('File writer');
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
        $deleteEmptyFolders = $this->getDeleteEmptyFolders() ? 'Yes' : 'No';
        $filesToSave = empty($this->getFilesToSave()) ? 'none' : implode("\n\t -", $this->getFilesToSave());
        $filesToDelete = empty($this->getFilesToDelete()) ? 'none' : implode("\n\t -", $this->getFilesToDelete());
        $md = <<<MD
Base path: ""{$this->getBasePath()}"

Delete empty folders: {$deleteEmptyFolders}

Files to save: {$filesToSave}

Files to delete: {$filesToDelete}

MD;
        return $md;
    }
    
    /**
     * 
     * @return bool
     */
    public function getDeleteEmptyFolders() : bool
    {
        return $this->deleteEmptyFolders;
    }
    
    /**
     * 
     * @param bool $value
     * @return FileWriteDataQuery
     */
    public function setDeleteEmptyFolders(bool $value) : FileWriteDataQuery
    {
        $this->deleteEmptyFolders = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getFilesToSave() : array
    {
        return $this->filesToSave;
    }
    
    /**
     * 
     * @param string $pathAbsoluteOrRelative
     * @param string|resource $content
     * @return FileWriteDataQuery
     */
    public function addFileToSave(string $pathAbsoluteOrRelative, $content) : FileWriteDataQuery
    {
        $this->filesToSave[$pathAbsoluteOrRelative] = $content;
        return $this;
    }
    
    /**
     * 
     * @return string[]|FileInfoInterface[]
     */
    public function getFilesToDelete() : array
    {
        return $this->filesToDelete;
    }
    
    /**
     * 
     * @param string|FileInfoInterface $pathOrFileInfo
     * @return FileWriteDataQuery
     */
    public function addFileToDelete($pathOrFileInfo) : FileWriteDataQuery
    {
        $this->filesToDelete[] = $pathOrFileInfo;
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
            if (is_countable($this->resultGenerator)) {
                return count($this->resultGenerator);
            } else {
                $i = 0;
                foreach ($this->getFiles() as $f) {
                    $i++;
                }
                return $i;
            }
        }
        return count($this->resultCache);
    }
}