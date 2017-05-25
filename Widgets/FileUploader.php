<?php
namespace exface\Core\Widgets;

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

    public function setAllowedExtensions($value)
    {
        $this->allowed_extensions = $value;
        return $this;
    }

    public function getMaxFileSizeBytes()
    {
        return $this->max_file_size_bytes;
    }

    public function setMaxFileSizeBytes($value)
    {
        $this->max_file_size_bytes = $value;
        return $this;
    }
}
?>