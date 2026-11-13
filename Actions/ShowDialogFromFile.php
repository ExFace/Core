<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\DataSheets\DataCollector;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\FilePathNotAllowedError;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * This creates and displays a widget from a JSON file containing some UXON description of the widget.
 *
 * It is used for instance to show errors with additional information from log detail files. Such files contain
 * UXON like this:
 *
 * {
 *  "widget_type": "DebugMessage",
 *  "object_alias": "exface.Core.MESSAGE",
 *  "visibility": "normal",
 *  "widgets": [
 *      {
 *          "id": "error_tab",
 *          "widget_type": "Tab",
 *          "object_alias": "exface.Core.MESSAGE",
 *          "caption": "Error",
 *          "visibility": "normal",
 *          "widgets": [
 *      {
 *          "widget_type": "TextHeading",
 *          "object_alias": "exface.Core.MESSAGE",
 *          "value": "Error 6T91AR9: Invalid data filter widget configuration",
 *          "visibility": "normal",
 *          "heading_level": 2
 *      },
 *      {
 *          "widget_type": "Text",
 *          "object_alias": "exface.Core.MESSAGE",
 *          "value": "Cannot create a filter for attribute alias \"NO\" in widget \"style\": attribute not found for object \"alexa.RMS.ARTICLE\"!",
 *          "visibility": "normal"
 *      },
 *      {
 *          "widget_type": "Text",
 *          "object_alias": "exface.Core.MESSAGE",
 *          "caption": "Description",
 *          "hint": "[Text] ",
 *          "visibility": "normal",
 *          "attribute_alias": "DESCRIPTION"
 *      },
 *      ... eventually more tabs ...
 *  ]
 * }
 *
 * @author Thomas Walter
 *        
 */
class ShowDialogFromFile extends ShowDialog
{

    private $file_path_attribute_alias = null;

    private $file_extension = null;
    private $folder_path = null;
    private $file_path_relative = nulL;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
    }

    /**
     *
     * @return string
     */
    public function getFilePathAttributeAlias()
    {
        return $this->file_path_attribute_alias;
    }

    /**
     * Alias of the attribute holding the path to the folder containing the files
     * 
     * @uxon-property file_path_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value            
     * @return \exface\Core\Actions\ShowDialogFromFile
     */
    public function setFilePathAttributeAlias($value)
    {
        $this->file_path_attribute_alias = $value;
        return $this;
    }

    /**
     * Static file path relative to the folder_path
     * 
     * @uxon-property file_path
     * @uxon-type string
     * 
     * @param string $pathRelative
     * @return $this
     */
    public function setFilePath(string $pathRelative) : ShowDialogFromFile
    {
        $this->file_path_relative = $pathRelative;
        return $this;
    }

    /**
     * Reads input-selected UXON only after constraining it to the configured folder.
     *
     * The extension is appended before validation so task data cannot use traversal, absolute paths, wrappers, or
     * symlinks to load and instantiate widgets from outside that folder. Filesystem failures are wrapped here so
     * user-facing errors cannot disclose the resolved installation or configured folder path, inject HTML through
     * Markdown error widgets, or inherit an internal-error response. Traversal attempts remain persistently logged,
     * while ordinary missing files stay below the configured error persistence threshold.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        
        $basePath = Filemanager::pathNormalize($this->getWorkbench()->filemanager()->getPathToBaseFolder());
        $filePath = $this->getFilePath($task);
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
        $completeFilename = $basePath . '/' . $filePath . ($this->getFileExtension() ? '.' . ltrim($this->getFileExtension(), ".") : '');
        $filename = FilePathDataType::findFileName($filePath, false);
        try {
            $json = $this->getWorkbench()->filemanager()->readFileSecure($completeFilename, $this->getAllowedBasePaths());
        } catch (FilePathNotAllowedError | FileNotFoundError $e) {
            $error = new ActionInputError(
                $this,
                'Cannot open file "' . MarkdownDataType::escapeString($filename) . '".',
                null,
                $e
            );
            $error->setStatusCode(400);
            $error->setLogLevel(
                $e instanceof FilePathNotAllowedError
                    ? LoggerInterface::ERROR
                    : LoggerInterface::WARNING
            );
            $error->setUseExceptionMessageAsTitle(true);
            $message = $error->getMessageModel($this->getWorkbench());
            $message->setDescription('');
            $message->setHint('');
            throw $error;
        }
        $this->getDialogWidget()->addWidget(WidgetFactory::createFromUxon($this->getDialogWidget()->getPage(), UxonObject::fromJson($json), $this->getDialogWidget()));
        
        if (! $this->getWidget()->getCaption()) {
            $this->getWidget()->setCaption($filename);
        }
        
        $this->getWidget()->setCacheable(false);
        
        return parent::perform($task, $transaction);
    }

    /**
     * Returns the proposed default dialog file root, pending team decision.
     *
     * A configured folder becomes the sole boundary for task-controlled filenames. If no folder is configured,
     * the installation folder remains the compatibility default and is intentionally surfaced as a decision point.
     *
     * @return string[]
     */
    protected function getAllowedBasePaths() : array
    {
        $basePath = Filemanager::pathNormalize($this->getWorkbench()->filemanager()->getPathToBaseFolder());
        $folderPath = $this->getFolderPath();
        if ($folderPath === null || $folderPath === '') {
            return [$basePath];
        }
        return [
            Filemanager::pathIsAbsolute($folderPath)
                ? $folderPath
                : Filemanager::pathJoin([$basePath, $folderPath])
        ];
    }
    
    protected function getFilePath(TaskInterface $task) : string
    {
        if ($this->file_path_relative !== null) {
            return $this->file_path_relative;
        }
        
        // Get the file path from the input data (read it if necessary)
        $inputSheet = $this->getInputDataSheet($task);
        $collector = new DataCollector($this->getMetaObject());
        $collector->addAttributeAlias($this->getFilePathAttributeAlias());
        $collector->collectFrom($inputSheet);
        $fileCol = $collector->getRequiredColumns()[$this->getFilePathAttributeAlias()];
        $filename = $fileCol->getValue(0);

        if (empty(trim($filename ?? ''))) {
            throw new ActionInputMissingError($this, 'No file name found in input column "' . $this->getFilePathAttributeAlias() . '" for action "' . $this->getAliasWithNamespace() . '"!');
        }
        return $filename;
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
     * @param string $value            
     * @return \exface\Core\Actions\ShowDialogFromFile
     */
    public function setFolderPath(string $value)
    {
        $this->folder_path = $value;
        return $this;
    }
}