<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\PlaceholderRenderers\PlaceholderRendererInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class FileContentInstaller extends AbstractAppInstaller
{
    /**
     * Behavior setting for missing markers. If applied, missed markers and their contents 
     * will be appended to the end of the file.
     * @see FileContentInstaller::setMissingMarkerBehavior()
     */
    public const MISSING_MARKER_BEHAVIOR_APPEND = 'append';

    /**
     * Behavior setting for missing markers. If applied, missed markers will be ignored.
     * @see FileContentInstaller::setMissingMarkerBehavior()
     */
    public const MISSING_MARKER_BEHAVIOR_IGNORE = 'ignore';

    /**
     * Behavior setting for missing markers. If applied, missed markers will cause an error.
     * @see FileContentInstaller::setMissingMarkerBehavior()
     */
    public const MISSING_MARKER_BEHAVIOR_ERROR = 'error';

    private array $missingMarkerBehaviorOptions = [
        self::MISSING_MARKER_BEHAVIOR_APPEND,
        self::MISSING_MARKER_BEHAVIOR_IGNORE,
        self::MISSING_MARKER_BEHAVIOR_ERROR
    ];
    
    private $filePathAbsolute = null;
    
    private $fileTemplatePathAbsolute = null;
    
    private $markerBegin = null;
    
    private $markerEnd = null;
    
    private $missingMarkerBehavior = self::MISSING_MARKER_BEHAVIOR_APPEND;
    
    private PlaceholderRendererInterface|null $placeholderRenderer = null;
    
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
     * Creates new file with given name by either copying file template, or if no template is given,
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
            $begin = trim(StringDataType::replacePlaceholders($this->getMarkerBegin(), ['marker' => preg_quote($marker)]));
            $end = trim(StringDataType::replacePlaceholders($this->getMarkerEnd(), ['marker' => preg_quote($marker)]));
            $pattern = '/' . "\s+" . $begin . '.*' . $end . '/is';
            $newContent = "\n\n" . $begin . "\n" . $content . "\n" . $end;

            if (preg_match($pattern, $fileContent)) {
                if(preg_match('/^(\t+)(?=' . $begin . ')/m', $fileContent, $indents) === 1) {
                    $newContent = StringDataType::indent($newContent, $indents[0]);
                }

                $fileContent = preg_replace(
                    $pattern,
                    $newContent,
                    $fileContent
                );

                if ($fileContent === null) {
                    throw new InstallerRuntimeError($this, 'Error replacing marker "' . $marker . '" in file "' . $this->getFilePathAbsolute() . '"!');
                }
                if ($fileContent !== $originalContent) {
                    $changesCnt++;
                }
            } else {
                switch ($this->getMissingMarkerBehavior()) {
                    // Append.
                    case self::MISSING_MARKER_BEHAVIOR_APPEND:
                        $fileContent .= $newContent . "\n";
                        $changesCnt++;
                        break;
                    // Ignore.
                    case self::MISSING_MARKER_BEHAVIOR_IGNORE:
                        break;
                    // Error.
                    default:
                        $requiredMarkers = json_encode(array_keys($this->contentArray));
                        throw new InstallerRuntimeError(
                            $this, 
                            "Failed to find marker \"{$marker}\" in file \"{$this->getFilePathAbsolute()}\"." . 
                            " Both \"{$this->getMarkerBegin()}\" and \"{$this->getMarkerEnd()}\" for the following" . 
                            " markers should be present in that file: " . $requiredMarkers . "."
                        );
                }
            }
        }

        // Replace placeholders if needed
        $placeholderRenderer = $this->getPlaceholderRenderer();
        if ($placeholderRenderer) {
            $fileContent = $placeholderRenderer->render($fileContent);
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
                $result .= "created from template";
            } catch (\Exception $e) {
                $result .= "failed to create file: " . $e->getMessage() . ' in ' . $e->getFile() . ' at ' . $e->getLine();
            }
        } else {
            file_put_contents($file, '');
            $result .= "created empty";
        }
        
        return ' ' . $result;
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
     * Set the beginning for the marker for the content. Has to include placeholder [#marker#].
     * e.g. '# BEGIN [#marker#]'
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
     * Set the ending for the marker for the content. Has to include placeholder [#marker#].
     * e.g. '# END [#marker#]'
     * 
     * @param string $marker
     * @return FileContentInstaller
     */
    public function setMarkerEnd(string $marker) : FileContentInstaller
    {
        $this->markerEnd = $marker;
        return $this;
    }

    /**
     * Returns the behavior setting for missing markers. 
     * Check the corresponding `MISSING_MARKER_BEHAVIOR` constants for more info.
     * 
     * @return string
     */
    public function getMissingMarkerBehavior() : string
    {
        return $this->missingMarkerBehavior;
    }

    /**
     * Set the behavior for missing markers.
     * Check the corresponding `MISSING_MARKER_BEHAVIOR` constants for more info.
     *
     * @param string $value
     * @return FileContentInstaller
     */
    public function setMissingMarkerBehavior(string $value) : FileContentInstaller
    {
        if(!in_array($value, $this->missingMarkerBehaviorOptions)) {
            $allowed = json_encode($this->missingMarkerBehaviorOptions);
            throw new InvalidArgumentException('Invalid argument "' . $value . '", expected ' . $allowed . '.');
        }

        $this->missingMarkerBehavior = $value;
        return $this;
    }

    /**
     * Add a template renderer to replace placeholders in the template file and in the generated sections
     * 
     * @param PlaceholderRendererInterface $renderer
     * @return $this
     */
    public function setPlaceholderRenderer(PlaceholderRendererInterface $renderer) : FileContentInstaller
    {
        $this->placeholderRenderer = $renderer;
        return $this;
    }

    /**
     * @return PlaceholderRendererInterface|null
     */
    protected function getPlaceholderRenderer() : ?PlaceholderRendererInterface
    {
        return $this->placeholderRenderer;
    }
}