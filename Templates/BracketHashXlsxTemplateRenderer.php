<?php
namespace exface\Core\Templates;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\TemplateRenderer\Traits\BracketHashTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\Traits\FileTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\TemplateRenderer\TemplateRendererRuntimeError;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Validations;

/**
 * Renderer for Php-Office worksheets containing placeholders with `[##]` syntax.
 * 
 * @author Georg Bieger
 *
 */
class BracketHashXlsxTemplateRenderer extends AbstractTemplateRenderer
{
    use FileTemplateRendererTrait;

    private $defaultResolver = null;
    
    private $ignoreUnknownPlaceholders = false;

    /**
     *
     * {@inheritDoc}
     * @throws Exception
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     */
    public function render(Spreadsheet $tplSpreadsheet = null) : Spreadsheet
    {
        if($tplSpreadsheet === null) {
            throw new InvalidArgumentException('Cannot render template without a worksheet! $tplWorksheet must not be null!');
        }

        foreach($tplSpreadsheet->getWorksheetIterator() as $tplWorksheet) {
            $cellsWithPhs = [];
            try {
                $phs = [];
                foreach ($tplWorksheet->getRowIterator() as $row) {
                    if($row->isEmpty()) {
                        continue;
                    }

                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(true);
                    foreach ($cells as $cell) {
                        $phsInCell = StringDataType::findPlaceholders($cell->getValue());
                        if (!empty($phsInCell)) {
                            $cellsWithPhs[] = $cell->getCoordinate();
                            $phs = array_merge($phs, $phsInCell);
                        }
                    }
                }

                $phs = array_unique($phs);
                $phVals = $this->getPlaceholderValues($phs);

                $newRowsRequested = [];
                $pendingColumns = [];
                foreach ($cellsWithPhs as $coords) {
                    // Get numeric coordinates.
                    [$x, $y] = Coordinate::indexesFromString($coords);

                    // Render placeholder.
                    $cell = $tplWorksheet->getCell($coords);
                    $rendered = $this->renderCellPlaceholders($cell->getValue(), $phVals);

                    // Check if additional rows are required.
                    $rowCount = count($rendered);
                    if ($rowCount > 1) {
                        // Request new rows if necessary. Note that we now search by row ($y) first.
                        $newRowsRequested[$y] = max($newRowsRequested[$y], $rowCount - 1);

                        // PhpSpreadsheet works with 2D-Arrays in the form of [row][column], so we have to "rotate" our array to match that.
                        $pendingColumns[$y][$x] = array_chunk($rendered, 1);
                    } else {
                        $tplWorksheet->setCellValue([$x, $y], empty($rendered) ? '' : $rendered[0]);
                    }
                }

                $rowOffset = 0;
                foreach ($newRowsRequested as $y => $rowCount) {
                    // Apply current offset.
                    $yOffset = $y + $rowOffset;

                    // Add requested rows.
                    $tplWorksheet->insertNewRowBefore($yOffset + 1, $rowCount);

                    // Fill in rendered values.
                    foreach ($pendingColumns[$y] as $x => $column) {
                        $tplWorksheet->fromArray($column, null, Validations::validateCellAddress([$x, $yOffset]), true);
                    }

                    // Update offset.
                    $rowOffset += $rowCount;
                }

            } catch (\Throwable $e) {
                throw new TemplateRendererRuntimeError($this, 'Cannot render template. ' . $e->getMessage(), null, $e);
            }
        }

        return $tplSpreadsheet;
    }

    /**
     * Renders all placeholders present in the provided cell contents.
     *
     * Returns an array with the results. If any of the placeholders resolve to
     * an array, the result will have as many rows as the largest array among the resolved
     * placeholders.
     *
     * @param string $cellContent
     * @param array $phValues
     * @return array
     */
    private function renderCellPlaceholders(string $cellContent, array $phValues) : array
    {
        // Filter unused placeholders.
        $phValues = array_intersect_key($phValues, array_flip(StringDataType::findPlaceholders($cellContent)));

        // Pre-process placeholder values.
        $indexToKey = [];
        $maxCount = 1;
        foreach ($phValues as $ph => $value) {
            if(is_array($value)) {
                $maxCount = max(count($value), $maxCount);
                $indexToKey[$ph] = array_keys($value);
            }
        }

        // Render placeholders.
        $result = [];
        $localPhValues = $phValues;
        for($i = 0; $i < $maxCount; $i++) {
            foreach ($phValues as $ph => $value) {
                if(key_exists($ph, $indexToKey)) {
                    $key = $indexToKey[$ph][$i];
                    $localPhValues[$ph] = $value[$key] ?? '';
                }
            }

            $result[] = StringDataType::replacePlaceholders($cellContent, $localPhValues);
        }

        return $result;
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