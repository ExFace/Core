<?php
namespace exface\Core\Facades\DocsFacade;

use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;
use exface\Core\Exceptions\FileNotFoundError;

class DocsTemplateRenderer extends AbstractTemplateRenderer 
{
	public function render(string $filePathAbsolute = null)
	{
        // Check if the file exists
        if (!file_exists($filePathAbsolute)) {
            throw new FileNotFoundError("File not found: $filePathAbsolute");
        }
        $markdown = file_get_contents($filePathAbsolute);
		$phs = $this->getPlaceholders($markdown);
        $vals = $this->getPlaceholderValues($phs);
        
        foreach ($phs as $ph => $phData) {
            $startTag = $phData['comment'];
            $endTag = '<!-- END ' . $phData['key'] . ' -->';
    
            $markdown = $this->replaceAtOffset($markdown, $startTag, $endTag, $vals[$ph] ?? '', $phData['offset'], $phData['key']);
        }

        return $markdown;
	}
    
    function replaceAtOffset(string $markdown, string $startTag, string $endTag, string $replacement, int $offset, string $placeholderName): string
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

        if ($placeholderName === 'ImageRef') {
            $newBlock = $startTag . $replacement . $endTag;
        }
        else {
            $newBlock = $startTag . PHP_EOL . $replacement . PHP_EOL . $endTag;
        }


        return substr_replace($markdown, $newBlock, $startPos, $endPos - $startPos);
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