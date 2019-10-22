<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class FileContentInstaller extends AbstractAppInstaller
{
    private $filePathAbsolute = null;
    
    private $fileTemplatePathAbsolute = null;
    
    private $markerBegin = null;
    
    private $markerEnd = null;
    
    /**
     * [ marker => content ]
     * @var array
     */
    private $contentArray = [];
    
    /**
     * Set the absolut Path for the file
     * 
     * @param string $absolutePath
     * @return FileContentInstaller
     */
    public function setFilePath(string $absolutePath) : FileContentInstaller
    {
        $this->filePathAbsolute = $absolutePath;
        return $this;
    }
    
    /**
     * Returns absolut path for file
     * 
     * @return string
     */
    protected function getFilePathAbsolute() : string
    {
        return $this->filePathAbsolute;
    }
    
    /**
     * Returns the file name with extension
     * 
     * @return string
     */
    protected function getFileName() : string
    {
        return pathinfo($this->getFilePathAbsolute(), PATHINFO_BASENAME);
    }
    
    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }

    public function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * Creates new file with given name by either copying file template, or if no templateis given,
     * creating an empty file.
     * If file already exists, content that is included by given markers gets replaced with given content,
     * or if markers dont exists yet, markers and content get added to the file.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        yield $indent . "File " . $this->getFileName() . ": ";
        
        if (file_exists($this->getFilePathAbsolute()) === false) {
            yield $indent.$indent.$this->createFile($source_absolute_path) . ', ';
        }
        
        $fileContent = file_get_contents($this->getFilePathAbsolute());
        $changesCnt = 0;
        foreach ($this->contentArray as $marker => $content) {
            $originalContent = $fileContent;
            $begin = $this->getMarkerBegin() . preg_quote($marker);
            $end = $this->getMarkerEnd() . preg_quote($marker);
            $pattern = '/' . $begin . '.*' . $end . '/is';
            
            if (preg_match($pattern, $fileContent)) {
                $fileContent = preg_replace($pattern, $begin . $content . $end, $fileContent);
                if ($fileContent === null) {
                    throw new InstallerRuntimeError($this, 'Error replacing marker "' . $marker . '" in file "' . $this->getFilePathAbsolute() . '"!');
                }
                if ($fileContent !== $originalContent) {
                    $changesCnt++;
                }
            } else {
                $fileContent .= $begin . $content . $end;
                $changesCnt++;
            }
        }
        file_put_contents($this->getFilePathAbsolute(), $fileContent);
        
        yield ' made ' . $changesCnt . ' changes.' . PHP_EOL;
    }

    /**
     * Creates the file by either copying the template file, or if that not exists creating an empty file
     * 
     * @param string $source_absolute_path
     * @return string
     */
    protected function createFile(string $source_absolute_path) : string
    {
        $result = '';
        
        $file = $this->getFilePathAbsolute();
        if ($this->getFileTemplatePathAbsolute($source_absolute_path) !== null) {
            try {
                $this->getWorkbench()->filemanager()->copy($this->getFileTemplatePathAbsolute($source_absolute_path), $file);
                $result .= "\ncreated from template";
            } catch (\Exception $e) {
                $result .= "\nFailed to create file: " . $e->getMessage() . ' in ' . $e->getFile() . ' at ' . $e->getLine();
            }
        } else {
            file_put_contents($file, '');
            $result .= "\ncreated empty";
        }
        
        return $result;
    }
    
    /**
     * Set the content that should be added to the file. Content is given with an array.
     * Array field names are the marker name.
     * 
     * @param string $marker
     * @param string $content
     * @return FileContentInstaller
     */
    public function addContent(string $marker, string $content) : FileContentInstaller
    {
        $this->contentArray[$marker] = $content;
        return $this;
    }
    
    /**
     * Returns absolute path for template file
     * 
     * @param string $source_absolute_path
     * @return string|NULL
     */
    protected function getFileTemplatePathAbsolute(string $source_absolute_path) : ?string
    {
        return $this->getInstallFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->fileTemplatePathAbsolute;
    }
    
    /**
     * Set the absolute path for the file template
     * 
     * @param string $pathRelativeToInstallFolder
     * @return FileContentInstaller
     */
    public function setFileTemplatePath(string $pathRelativeToInstallFolder) : FileContentInstaller
    {
        $this->fileTemplatePathAbsolute = $pathRelativeToInstallFolder;
        return $this;
    }
    
    /**
     * Returns the beginning for the content marker
     * 
     * @return string
     */
    protected function getMarkerBegin() : string
    {
        return $this->markerBegin;
    }
        
    /**
     * Set the beginning for the marker for the content
     * e.g. '# BEGIN'
     * 
     * @param string $marker
     * @return FileContentInstaller
     */
    public function setMarkerBegin(string $marker) : FileContentInstaller
    {
        $this->markerBegin = $marker;
        return $this;
    }
        
    /**
     * Returns the ending for the content marker
     *
     * @return string
     */
    protected function getMarkerEnd() : string
    {
        return $this->markerEnd;
    }
    
    /**
     * Set the ending for the marker for the content
     * e.g. '# END'
     * 
     * @param string $marker
     * @return FileContentInstaller
     */
    public function setMarkerEnd(string $marker) : FileContentInstaller
    {
        $this->markerEnd = $marker;
        return $this;
    }

}