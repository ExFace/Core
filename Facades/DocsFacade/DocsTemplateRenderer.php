<?php
namespace exface\Core\Facades\DocsFacade;

use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;

/**
 * Renders documentation placeholders while preserving surrounding Markdown.
 */
class DocsTemplateRenderer extends AbstractTemplateRenderer 
{
    /**
     * Reads documentation templates through canonical containment validation because callers may derive paths from URLs.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     *
     * @param string|null $filePathAbsolute
     * @return string
     */
	public function render(string $filePathAbsolute = null)
	{
        $markdown = $this->getWorkbench()->filemanager()->readFileSecure(
            $filePathAbsolute ?? '',
            $this->getAllowedBasePaths()
        );
		$phs = $this->getPlaceholders($markdown);
        $vals = $this->getPlaceholderValues($phs);
        
        foreach ($phs as $ph => $phData) {
            $startTag = $phData['comment'];
            $endTag = '<!-- END ' . $phData['key'] . ' -->';

            $options = [];
            parse_str($phData['options'], $options);
    
            $markdown = $this->replaceAtOffset($markdown, $startTag, $endTag, $vals[$ph] ?? '', $phData['offset'], $phData['key'], $options['noprint'] ?? '');
        }

        return $markdown;
	}

    /**
     * Returns the proposed default documentation template roots, pending team decision.
     *
     * Vendor is the only expected source for app documentation and excludes installation secrets.
     *
     * @return string[]
     */
    protected function getAllowedBasePaths() : array
    {
        return [$this->getWorkbench()->filemanager()->getPathToVendorFolder()];
    }
    
    /**
     * Replaces a placeholder using its existing line endings, or the document's for inline blocks.
     *
     * @return string
     */
    function replaceAtOffset(string $markdown, string $startTag, string $endTag, string $replacement, int $offset, string $placeholderName, string $noprint): string
    {
        if ($offset > strlen($markdown)) {
        	return $markdown;
        }
        
        $startPos = strpos($markdown, $startTag, $offset);
        if ($startPos === false) {
            return $markdown; 
        }

        $endPos = strpos($markdown, $endTag, $startPos);
        if ($endPos === false) {
            return $markdown;
        }

        $endPos += strlen($endTag);

        if (!preg_match('/\r\n|\r|\n/', substr($markdown, $startPos, $endPos - $startPos), $lineEndings)) {
            preg_match('/\r\n|\r|\n/', $markdown, $lineEndings);
        }
        $lineEnding = $lineEndings[0] ?? "\n";
        $replacement = $this->checkPrintStatus($replacement, $noprint);
        $replacement = str_replace("\n", $lineEnding, str_replace(["\r\n", "\r"], "\n", $replacement));

        if ($placeholderName === 'ImageRef') {
            $newBlock = $startTag . $replacement . $endTag;
        }
        else {
            $newBlock = $startTag . $lineEnding . $replacement . $lineEnding . $endTag;
        }

        return substr_replace($markdown, $newBlock, $startPos, $endPos - $startPos);
    }

    /**
     * Checks noprint option to put noprint tags in the document
     * 
     * @param string $content
     * @param string $noprint
     * @return string
     */
    protected function checkPrintStatus(string $content, string $noprint) : string
    {
        if (!empty($noprint) && $noprint === 'true') {
            $content = '<!-- noprint:start -->' . PHP_EOL
                     . $content . PHP_EOL
                     . '<!-- noprint:end -->';
        }
        return $content;
    }

	public function exportUxonObject()
	{
		// Implement the exportUxonObject method
	}

    protected function getPlaceholders(string $tpl) : array
    {
        // Regex to extract the comment block (e.g., <!-- ... -->)
        $regex = '/<!--\s*BEGIN\s+((\w+)(?::([^\s]*))?)\s*-->/i';
        $matches = [
            // 0 => full match
            // 1 => placeholder with options
            // 2 => placeholder name
            // 3 => placeholder options
        ];
        preg_match_all($regex, $tpl, $matches, PREG_OFFSET_CAPTURE);

        $phs = [];
        foreach ($matches[0] as $i => $match) {
            [$fullMatch, $offset] = $match;
            $phs[] = [
                'key' => $matches[2][$i][0],
                'name' => $matches[1][$i][0],
                'options' => trim($matches[3][$i][0]),
                'comment' => $fullMatch,
                'offset' => $offset
            ];
        }
        return $phs;
    }
    
    /**
     * 
     * @param string[] $placeholders
     * @return array
     */
    protected function getPlaceholderValues(array $placeholders) : array
    {
        $phVals = [];

        // Let each resolver handle the full placeholder list and return values indexed by their original positions
        foreach ($this->getPlaceholderResolvers() as $resolver) {
            $resolved = $resolver->resolve($placeholders);
            foreach ($resolved as $i => $val) {
                $phVals[$i] = $val;
            }
        }
        
        // Find placeholders that were not resolved by any resolver
        $placeholderIndexes = array_keys($placeholders);
        $missingIndexes = array_diff($placeholderIndexes, array_keys($phVals));
        
        // Assign fallback value for missing placeholders
        foreach ($missingIndexes as $i) {
            $ph = $placeholders[$i];
            $phVals[$i] = '[#' . ($ph['key'] ?? $ph['name']) . '#]';
        }

        return $phVals;
    }
}