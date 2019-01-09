<?php
namespace exface\Core\Widgets;

/**
 * A FileUploader lets users upload one or more files to the server.
 * 
 * Depending on the template, it may be a select-field linked to the file system, an
 * URL-input or a drop/paste area.
 * 
 * @author Andrej Kabachnik
 *
 */
class FileUploader extends Input
{

    private $default_file_description = 'Upload';

    /**
     * @uxon allowed_extensions Comma separated list of allowed file extensions (case insensitive) - all by default
     *
     * @var string
     */
    private $allowed_extensions = '';

    /**
     *
     * @var max_file_size_bytes Maximum upload size in bytes - 10 000 000 by default
     * @var integer
     */
    private $max_file_size_bytes = 10000000;

    public function getDefaultFileDescription()
    {
        return $this->default_file_description;
    }

    public function setDefaultFileDescription($value)
    {
        $this->default_file_description = $value;
        return $this;
    }

    public function getAllowedExtensions()
    {
        return $this->allowed_extensions;
    }

    /**
     * A comma-separated list of allowed file extensions
     * 
     * @uxon-property allowed_extensions
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Widgets\FileUploader
     */
    public function setAllowedExtensions($value)
    {
        $this->allowed_extensions = $value;
        return $this;
    }

    public function getMaxFileSizeBytes()
    {
        return $this->max_file_size_bytes;
    }

    /**
     * 
     * @param int $value
     * @return \exface\Core\Widgets\FileUploader
     */
    public function setMaxFileSizeBytes($value)
    {
        $this->max_file_size_bytes = $value;
        return $this;
    }
}
?>