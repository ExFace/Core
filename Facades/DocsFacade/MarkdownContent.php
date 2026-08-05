<?php
namespace exface\Core\Facades\DocsFacade;

use exface\Core\DataTypes\MarkdownDataType;
use kabachello\FileRoute\FileTypes\MarkdownFile;
use kabachello\FileRoute\Interfaces\FolderStructureInterface;

class MarkdownContent extends MarkdownFile
{
    private $markdown;

    private $contentRenderedHtml = null;
    
    public function __construct(string $filePath, string $urlPath, FolderStructureInterface $folder, string $markdown)
    {
        parent::__construct($filePath, $urlPath, $folder);
        $this->markdown = $markdown;
    }
    
    protected function getContentRaw(): string
    {
        return $this->markdown;
    }

    /**
     * Render the markdown to HTML via the central MarkdownDataType conversion, so the
     * DocsFacade uses the same parser configuration (incl. the `enable_newlines` default)
     * as the rest of the workbench.
     * 
     * {@inheritDoc}
     * @see \kabachello\FileRoute\FileTypes\MarkdownFile::getContent()
     */
    public function getContent(): string
    {
        if ($this->contentRenderedHtml === null) {
            $this->contentRenderedHtml = MarkdownDataType::convertMarkdownToHtml($this->getContentRaw());
        }
        return $this->contentRenderedHtml;
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