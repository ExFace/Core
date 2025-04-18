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
        $vals = $this->getPlaceholderValues(array_keys($phs));
        
        foreach ($phs as $ph => $phData) {
            $val = $phData['comment'] . PHP_EOL . $vals[$ph] . PHP_EOL . '<!-- END ' . $phData['name'] . ' -->';
            $regex = '/' . preg_quote($phData['comment'], '/') . '(.*?)<!-- END ' . preg_quote($phData['name'], '/') . ' -->/s';
            if (preg_match_all($regex, $markdown, $matches)) {
                foreach ($matches[0] as $match) {
                    $markdown = str_replace($match, $val, $markdown);
                }
            }
        }

        return $markdown;
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
        preg_match_all($regex, $tpl, $matches);

        $phs = [];
        foreach ($matches[0] as $i => $fullMatch) {
            $phs[$matches[1][$i]] = [
                'name' => $matches[2][$i],
                'options' => trim($matches[3][$i]),
                'comment' => $fullMatch
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