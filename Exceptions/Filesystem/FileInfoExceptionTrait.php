<?php
namespace exface\Core\Exceptions\Filesystem;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateTimeDataType;

trait FileInfoExceptionTrait
{
    private $fileInfo = null;
    
    public function __construct($message, $alias = null, $previous = null, FileInfoInterface $fileInfo = null)
    {
        parent::__construct($message, null, $previous);
        $this->fileInfo = $fileInfo;
    }
    
    public function getFileInfo(): ?FileInfoInterface
    {
        return $this->fileInfo;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        
        if ($this->fileInfo !== null) {
            // Add a tab with the data sheet UXON
            $tab = $debugWidget->createTab();
            $debugWidget->addTab($tab);
            $tab->setCaption('File');
            $tab->setColumnsInGrid(1);
            $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
                'widget_type' => 'Markdown',
                'value' => $this->toMarkdown(),
                'width' => 'max'
            ])));
        }
        
        return $debugWidget;
    }
    
    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        $md = '';
        if ($this->fileInfo !== null) {
            $fileinfo = $this->fileInfo;
            $created_on = $fileinfo->getCreatedOn()->format(DateTimeDataType::DATETIME_FORMAT_INTERNAL);
            $modified_on = $fileinfo->getModifiedOn()->format(DateTimeDataType::DATETIME_FORMAT_INTERNAL);
            $md = <<<MD

Filename: {$fileinfo->getFilename()}

Path: {$fileinfo->getPathAbsolute()}

Filesize: {$fileinfo->getSize()} bytes

Created on: {$created_on}

Modified on: {$modified_on}

MimeType: {$fileinfo->getMimetype()}

Type: {$fileinfo->getType()}
MD;
            
        }
        return $md;
    }
}