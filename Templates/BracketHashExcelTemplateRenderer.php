<?php
namespace exface\Core\Templates;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\TemplateRenderer\Traits\BracketHashTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\Traits\FileTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\TemplateRenderer\TemplateRendererRuntimeError;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Renderer for template files using the standard `[##]` placeholder syntax.
 * 
 * @author andrej.kabachnik
 *
 */
class BracketHashExcelTemplateRenderer extends AbstractTemplateRenderer
{
    use FileTemplateRendererTrait;

    private $defaultResolver = null;
    
    private $ignoreUnknownPlaceholders = false;

    private $phCells = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     */
    public function render($tplPath = null)
    {        
        $this->phCells = [];
        try {
            $path = $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $tplPath;
            $reader = IOFactory::createReaderForFile($path);
            $spreadsheet = $reader->load($path);

            $tplWorkSheet = $spreadsheet->getActiveSheet();
            $allCells = $tplWorkSheet->toArray();
            $phs = [];
            foreach ($allCells as $r => $row) {
                foreach ($row as $c => $cell) {
                    $cellPhs = StringDataType::findPlaceholders($cell);
                    if (! empty($cellPhs)) {
                        $this->phCells[] = [$c+1, $r+1];
                    }
                    $phs = array_merge($phs, $cellPhs);
                }
            }

            $phs = array_unique($phs);

            $phVals = $this->getPlaceholderValues($phs);
            foreach ($this->phCells as $cellCoords) {
                $cell = $tplWorkSheet->getCell($cellCoords);
                $cell->setValue(StringDataType::replacePlaceholders($cell->getValue(), $phVals));
            }
        } catch (\Throwable $e) {
            throw new TemplateRendererRuntimeError($this, 'Cannot render template. ' . $e->getMessage(), null, $e);
        }
        
        $resultPath = '';
        return $resultPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        if ($val = $this->getTemplateFilePath()) {
            $uxon->setProperty('template_file_path', $val);
        }
        return $uxon;
    }

    /**
     * 
     * @param string[] $placeholders
     * @return array
     */
    protected function getPlaceholderValues(array $placeholders) : array
    {
        $phVals = [];
        
        // Resolve regular placeholders
        foreach ($this->getPlaceholderResolvers() as $resolver) {
            $phVals = array_merge($phVals, $resolver->resolve($placeholders));
        }
        
        // If there are placeholders left without values, see if there is a default resolver
        // and let it render the missing placeholders
        if (count($phVals) < count($placeholders) && $defaultResolver = $this->getDefaultPlaceholderResolver()) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            $phVals = array_merge($phVals, $defaultResolver->resolve($missingPhs));
        }
        
        // If there are still missing placeholders, either reinsert them or raise an error
        if (count($phVals) < count($placeholders)) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            if ($this->isIgnoringUnknownPlaceholders()) {
                foreach ($missingPhs as $ph) {
                    $phVals[$ph] = '[#' . $ph . '#]';
                }
            } else {
                throw new RuntimeException('Unknown placehodler(s) "[#' . implode('#]", "[#', $missingPhs) . '#]" found in template!');
            }
        }
        
        return $phVals;
    }
    
    /**
     * 
     * @return PlaceholderResolverInterface|NULL
     */
    protected function getDefaultPlaceholderResolver() : ?PlaceholderResolverInterface
    {
        return $this->defaultResolver;
    }
    
    /**
     * The default resolver will receive all the placeholders that are left after
     * all regular resolvers were run.
     * 
     * The main idea here is, that each resolver knows, which placeholders it can
     * resolve - e.g. by using unique placeholder prefixes as namespaces: ´[~data:xxx]´, 
     * `[#~config:xxx#]`, etc. Thus, non-prefixed placeholders will not be evaluated
     * while the regular resolvers are applied - this is where a default resolver can
     * be useful: pass a resolver with an empty namespace to `setDefaultPlaceholderResolver()`
     * and it will receive all the leftovers.
     * 
     * @param PlaceholderResolverInterface $value
     * @return BracketHashTemplateRendererTrait
     */
    public function setDefaultPlaceholderResolver(PlaceholderResolverInterface $value) : TemplateRendererInterface
    {
        $this->defaultResolver = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isIgnoringUnknownPlaceholders() : bool
    {
        return $this->ignoreUnknownPlaceholders;
    }
    
    /**
     * 
     * @param bool $value
     * @return TemplateRendererInterface
     */
    public function setIgnoreUnknownPlaceholders(bool $value) : TemplateRendererInterface
    {
        $this->ignoreUnknownPlaceholders = $value;
        return $this;
    }
}