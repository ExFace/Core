<?php
namespace exface\Core\Facades\DocsFacade;

use kabachello\FileRoute\FileTypes\MarkdownFile;
use kabachello\FileRoute\Interfaces\FolderStructureInterface;

class MarkdownContent extends MarkdownFile
{
    private $markdown;
    
    public function __construct(string $filePath, string $urlPath, FolderStructureInterface $folder, string $markdown)
    {
        parent::__construct($filePath, $urlPath, $folder);
        $this->markdown = $markdown;
    }
    
    protected function getContentRaw(): string
    {
        return $this->markdown;
    }

    public function getDateTimeUpdated(): \DateTime
    {
        return new \DateTime();
    }

    public function getDateTimeCreated(): \DateTime
    {
        return new \DateTime();
    }
}