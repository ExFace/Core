<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

/**
 * Trait for templates stored as files: text, XML, HTML, etc.
 * 
 * @author andrej.kabachnik
 *
 */
trait FileTemplateRendererTrait
{    
    private $templateFilePath;
    
    /**
     * 
     * @return string|NULL
     */
    protected function getTemplateFilePath() : ?string
    {
        return $this->templateFilePath;
    }
    
    /**
     * Path to the template file - either absolute or relative to vendor folder.
     *
     * @uxon-property template_file_path
     * @uxon-type string
     *
     * @param string $value
     * @return TemplateRendererInterface
     */
    public function setTemplateFilePath(string $value) : TemplateRendererInterface
    {
        if (Filemanager::pathIsAbsolute($value)) {
            $this->templateFilePath = $value;
        } else {
            $this->templateFilePath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $value;
        }
        return $this;
    }
    
    /**
     * Reads a template only after canonical containment validation prevents UXON paths from escaping vendor.
     *
     * @param string $tplPath
     * @throws RuntimeException
     * @return string
     */
    protected function getTemplate(string $tplPath = null) : string
    {
        $tplPath = $tplPath ?? $this->getTemplateFilePath();
        if (! $tplPath) {
            throw new RuntimeException('No template file specified for TemplateRenderer "' . get_class($this) . '"!');
        }
        if (Filemanager::pathIsAbsolute($tplPath)) {
            $absPath = $tplPath;
        } else {
            $absPath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $tplPath;
        }
        return $this->getWorkbench()->filemanager()->readFileSecure($absPath, $this->getAllowedBasePaths());
    }

    /**
     * Returns the proposed default template roots, pending team decision.
     *
     * Restricting file-backed templates to vendor blocks configured absolute paths, traversal, and symlink escapes
     * from exposing installation secrets while keeping app-owned templates available.
     *
     * @return string[]
     */
    protected function getAllowedBasePaths() : array
    {
        return [$this->getWorkbench()->filemanager()->getPathToVendorFolder()];
    }
}