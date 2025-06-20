<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Facades\DocsFacade\DocsTemplateRenderer;
use exface\Core\Facades\DocsFacade\Placeholders\ImageListResolver;
use exface\Core\Facades\DocsFacade\Placeholders\ImageNumberResolver;
use exface\Core\Facades\DocsFacade\Placeholders\ImageReferenceResolver;
use exface\Core\Facades\DocsFacade\Placeholders\NavButtonResolver;
use exface\Core\Facades\DocsFacade\Placeholders\SubPageListResolver;
use exface\Core\Interfaces\AppExporterInterface;

/**
 * This is more an exporter than an installer - it allows to generate docs from the meta model
 * and other data.
 * 
 * You can use placeholders in markdown files inside the `Docs` folder like this:
 * 
 * ```
 * <!-- BOF SubPageList:depth=2 -->
 * 
 * <!-- EOF SubPageList -->
 * 
 * ```
 * 
 * These placeholders are then replaced by generated content every time the app model is exported.
 * The generated content is placed inside the placeholder boundaries and updated with every export.
 * This way, the markdown files commmitted to git repos contain the (invisble) placeholder config
 * and the (visible) text, that was last generated for the placeholder.
 * 
 * ## Available placeholders
 * 
 * - `SubPageList` - unordered list of subpages of the current page
 * 
 * @author Andrej Kabachnik
 */
class AppDocsInstaller extends AbstractAppInstaller implements AppExporterInterface
{
    private $docsPath = 'Docs';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function backup(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        $rootPath = $source_absolute_path . DIRECTORY_SEPARATOR . $this->getDocsPathRelative();
        yield $indent . "Docs from " . $this->getDocsPathRelative() . ": ";
        //placeholders are implemented twice because of references
        $this->implementPlaceholders($rootPath);
        $fileCnt = $this->implementPlaceholders($rootPath);
        yield ' rendered ' . $fileCnt . ' files.' . PHP_EOL;
    }
    
    protected function implementPlaceholders(string $rootPath): int
    {
        $baseRenderer = new DocsTemplateRenderer($this->getWorkbench());

        $fileCnt = 0;
        foreach ($this->getMarkdownFiles($rootPath) as $file) {
            $fileRenderer = $baseRenderer->copy();
            $fileRenderer->addPlaceholder(new ImageNumberResolver($file));
            $fileRenderer->addPlaceholder(new SubPageListResolver($file));
            $fileRenderer->addPlaceholder(new NavButtonResolver($file));
            $fileRenderer->addPlaceholder(new ImageReferenceResolver($file));
            $fileRenderer->addPlaceholder(new ImageListResolver($file));
            // TODO add other placeholder classes here
            $rendered = $fileRenderer->render($file);
            file_put_contents($file, $rendered);
            $fileCnt++;
        }
        
        return $fileCnt;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppExporterInterface::exportModel()
     */
    public function exportModel() : \Iterator
    {
        yield from $this->backup($this->getApp()->getDirectoryAbsolutePath());
    }

    /**
     * 
     * @return string
     */
    protected function getDocsPathRelative() : string
    {
        return $this->docsPath;
    }

    /**
     * 
     * @param string $folderAbsPath
     * @return string[]
     */
    function getMarkdownFiles(string $folderAbsPath) {
        $mdFiles = [];
    
        // Open the directory
        $items = scandir($folderAbsPath);
    
        foreach ($items as $item) {
            // Skip "." and ".."
            if ($item === "." || $item === "..") {
                continue;
            }
    
            $itemPath = $folderAbsPath . DIRECTORY_SEPARATOR . $item;
    
            // If it's a directory, recurse into it
            if (is_dir($itemPath)) {
                $mdFiles = array_merge($mdFiles, $this->getMarkdownFiles($itemPath));
            }
            // If it's a .md file, add it to the list
            elseif (is_file($itemPath) && pathinfo($itemPath, PATHINFO_EXTENSION) === 'md') {
                $mdFiles[] = $itemPath;
            }
        }
    
        return $mdFiles;
    }
}