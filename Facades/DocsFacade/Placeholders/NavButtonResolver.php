<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

class NavButtonResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
{
    private $pagePath = null;

    public function __construct(string $pagePathAbsolute, string $prefix = 'NavButtons:') {
        $this->pagePath = $pagePathAbsolute;
        $this->setPrefix($prefix);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $vals = [];
        $rootDirectory = $this->getDocsPath($this->pagePath);        
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        $fileList = $this->getFlattenMarkdownFiles($rootDirectory);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $val = $this->createButtons($fileList, $this->pagePath);
                $vals[$i] = $val;
            }
        }
        return $vals;
    }


    protected function createButtons(array $flatList, string $currentPath): string
    {
        $normalizedCurrent = FilePathDataType::normalize($currentPath);

        $normalizedList = array_map(
            fn($p) => FilePathDataType::normalize($p),
            $flatList
        );

        $currentIndex = array_search($normalizedCurrent, $normalizedList);

        if ($currentIndex === false) {
            return '';
        }

        $buttons = [];

        if ($currentIndex > 0) {
            $prevPath = $flatList[$currentIndex - 1];
            $buttons[] = $this->mdButon(
                'Zurück',
                $this->getRelativePath($this->pagePath, $prevPath)
            );
        }

        if ($currentIndex < count($flatList) - 1) {
            $nextPath = $flatList[$currentIndex + 1];
            $buttons[] = $this->mdButon(
                'Weiter',
                $this->getRelativePath($this->pagePath, $nextPath)
            );
        }

        return implode(' ', $buttons);
    }


    function getRelativePath(string $from, string $to): string 
    {
        $from = is_dir($from) ? rtrim($from, '/') . '/' : dirname($from) . '/';
        $from = FilePathDataType::normalize(realpath($from));
        $to   = FilePathDataType::normalize(realpath($to));
    
        $fromParts = explode('/', trim($from, '/'));
        $toParts   = explode('/', trim($to, '/'));
    
        // remove common roots
        while (count($fromParts) && count($toParts) && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }
    
        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }    

    function mdButon(string $buttonText, string $path): string
    {
        return "[<kbd> <br> " . $buttonText . " <br> </kbd>]($path)";
    }

}