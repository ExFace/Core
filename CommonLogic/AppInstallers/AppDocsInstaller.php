<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Facades\DocsFacade\DocsTemplateRenderer;
use exface\Core\Facades\DocsFacade\Placeholders\SubPageListResolver;
use exface\Core\Interfaces\AppExporterInterface;

/**
 * This is more an exporter than an installer - it allows to generate docs from the meta model
 * and other data.
 * 
 * You can use placeholders in markdown files inside the `Docs` folder like this:
 * 
 * ```
 * <!-- BOF SubPageList -->
 * 
 * <!-- EOF SubPageList -->
 * 
 * ```
 * 
 * These placeholders are then replaced by generated content every time the app model is exported.
 * The generated content is placed insited the placeholder boundaries and updated with every export.
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
        $fileCnt = 0;
        yield $indent . "Docs from " . $this->getDocsPathRelative() . ": ";
        
        $baseRenderer = new DocsTemplateRenderer($this->getWorkbench());

        foreach ($this->getMarkdownFiles($rootPath) as $file) {
            $fileRenderer = $baseRenderer->copy();
            $fileRenderer->addPlaceholder(new SubPageListResolver($file));
            $rendered = $fileRenderer->render($file);
            file_put_contents($file, $rendered);
            $fileCnt++;
        }
        
        yield ' rendered ' . $fileCnt . ' files.' . PHP_EOL;
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