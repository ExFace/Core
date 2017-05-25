<?php

namespace exface\Core\Actions;

use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * This creates and displays a widget from a JSON file containing some UXON description of the widget.
 *
 * It is used for instance to show errors with additional information from log detail files. Such files contain
 * UXON like this:
 *
 * {
 * "widget_type": "DebugMessage",
 * "object_alias": "exface.Core.ERROR",
 * "visibility": "normal",
 * "widgets": [
 * {
 * "id": "error_tab",
 * "widget_type": "Tab",
 * "object_alias": "exface.Core.ERROR",
 * "caption": "Error",
 * "visibility": "normal",
 * "widgets": [
 * {
 * "widget_type": "TextHeading",
 * "object_alias": "exface.Core.ERROR",
 * "value": "Error 6T91AR9: Invalid data filter widget configuration",
 * "visibility": "normal",
 * "heading_level": 2
 * },
 * {
 * "widget_type": "Text",
 * "object_alias": "exface.Core.ERROR",
 * "value": "Cannot create a filter for attribute alias \"NO\" in widget \"style\": attribute not found for object \"alexa.RMS.ARTICLE\"!",
 * "visibility": "normal"
 * },
 * {
 * "widget_type": "Text",
 * "object_alias": "exface.Core.ERROR",
 * "caption": "Description",
 * "hint": "[Text] ",
 * "visibility": "normal",
 * "attribute_alias": "DESCRIPTION"
 * }
 * ]
 * },
 * ... eventually more tabs ...
 * ]
 * }
 *
 * @author Thomas Walter
 *        
 */
class ShowDialogFromFile extends ShowDialog
{

    private $file_path_attribute_alias = null;

    private $file_extension = null;

    /**
     *
     * @return string
     */
    public function getFilePathAttributeAlias()
    {
        return $this->file_path_attribute_alias;
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\Actions\ShowDialogFromFile
     */
    public function setFilePathAttributeAlias($value)
    {
        $this->file_path_attribute_alias = $value;
        return $this;
    }

    protected function perform()
    {
        $basePath = Filemanager::pathNormalize($this->getWorkbench()
            ->filemanager()
            ->getPathToBaseFolder());
        
        $filename = $this->getInputDataSheet()
            ->getColumns()
            ->getByExpression($this->getFilePathAttributeAlias())
            ->getCellValue(0);
        if (strlen(trim($filename)) > 0) {
            if ($this->getFolderPath()) {
                if (Filemanager::pathIsAbsolute($this->getFolderPath())) {
                    $basePath = $this->getFolderPath();
                } else {
                    $basePath = Filemanager::pathJoin(array(
                        $basePath,
                        $this->getFolderPath()
                    ));
                }
            }
            $completeFilename = $basePath . '/' . $filename . ($this->getFileExtension() ? '.' . ltrim($this->getFileExtension(), ".") : '');
            if (file_exists($completeFilename)) {
                $json = file_get_contents($completeFilename);
                $this->setWidget(WidgetFactory::createFromUxon($this->getDialogWidget()
                    ->getPage(), UxonObject::fromJson($json), $this->getDialogWidget()));
            } else {
                throw new FileNotFoundError('File "' . $completeFilename . '" not found!');
            }
        } else {
            throw new ActionInputMissingError($this, 'No file name found in input column "' . $this->getFilePathAttributeAlias() . '" for action "' . $this->getAliasWithNamespace() . '"!');
        }
        
        if (! $this->getWidget()->getCaption()) {
            $this->getWidget()->setCaption($completeFilename);
        }
        
        parent::perform();
    }

    /**
     *
     * @return string
     */
    public function getFileExtension()
    {
        return $this->file_extension;
    }

    /**
     * Sets the file extension to be used if file_path_attribute_alias does not contain the extension.
     *
     * If the value of the file path attribute only contains the file name and no extension, add the
     * extension here: e.g. "json" or ".json".
     *
     * @uxon-property file_extension
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Actions\ShowDialogFromFile
     */
    public function setFileExtension($value)
    {
        $this->file_extension = $value;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getFolderPath()
    {
        return $this->folder_path;
    }

    /**
     * Adds a folder path to the value of the file path.
     * Relative paths will be assumed relative to the installation folder.
     *
     * @uxon-property folder_path
     * @uxon-type string
     *
     * @param unknown $value            
     * @return \exface\Core\Actions\ShowDialogFromFile
     */
    public function setFolderPath($value)
    {
        $this->folder_path = $value;
        return $this;
    }
}

?>