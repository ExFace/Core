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
    
            $markdown = $this->replaceAtOffset($markdown, $startTag, $endTag, $vals[$ph], $phData['offset']);
        }

        return $markdown;
	}
    function replaceAtOffset(string $markdown, string $startTag, string $endTag, string $replacement, int $offset): string
    {
         $startPos = strpos($markdown, $startTag, $offset);
        if ($startPos === false) {
            return $markdown; 
        }

        $endPos = strpos($markdown, $endTag, $startPos);
        if ($endPos === false) {
            return $markdown;
        }

        $endPos += strlen($endTag);

        $newBlock = $startTag . PHP_EOL . $replacement . PHP_EOL . $endTag;

        return substr_replace($markdown, $newBlock, $startPos, $endPos - $startPos);
    }

	public function exportUxonObject()
	{
		// Implement the exportUxonObject method
	}

    protected function getPlaceholders(string $tpl) : array
    {
        // Regex to extract the comment block (e.g., <!-- ... -->)
        $regex = '/<!-- BEGIN (([a-zA-Z0-9_]+):?\s*(.*)) -->/';
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
                'key' => $matches[2][$i][0], // e.g. 'ImageCaptionNr'
                'name' => $matches[1][$i][0], // e.g. 'ImageCaptionNr:'
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
        
        // Resolve regular placeholders
        foreach ($this->getPlaceholderResolvers() as $resolver) {
            $phVals = array_merge($phVals, $resolver->resolve($placeholders));
        }
        
        // If there are still missing placeholders, either reinsert them or raise an error
        if (count($phVals) < count($placeholders)) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            foreach ($missingPhs as $ph) {
                $phVals[$ph] = '[#' . $ph . '#]';
            }
        }
        
        return $phVals;
    }
}