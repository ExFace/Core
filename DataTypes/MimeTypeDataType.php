<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Data type for mime types.
 * 
 * If no mime types are added explicitly, a list of typical mime type will be
 * created automatically.
 * 
 * @author Andrej Kabachnik
 *
 */
class MimeTypeDataType extends StringDataType implements EnumDataTypeInterface
{
    private $mimeTypes = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabelOfValue()
     */
    public function getLabelOfValue($value = null): string
    {
        return $value;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        return $this->mimeTypes ?? array_combine(array_values(static::getMimeTypesByExtension()), array_values(static::getMimeTypesByExtension()));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::setValues()
     */
    public function setValues($uxon_or_array)
    {
        if ($uxon_or_array instanceof UxonObject) {
            $array = $uxon_or_array->toArray();
        } else {
            $array = $uxon_or_array;
        }
        $this->mimeTypes = $array;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::toArray()
     */
    public function toArray(): array
    {
        return $this->getLabels();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValues()
     */
    public function getValues()
    {
        return array_keys($this->getLabels());
    }
    
    /**
     * 
     * @param string $absolutePath
     * @return string|NULL
     */
    public static function guessMimeTypeOfFile(string $absolutePath) : ?string
    {
        $type = mime_content_type($absolutePath);
        return $type !== false ? $type : null;
    }
    
    /**
     * 
     * @param string $fileExtension
     * @param string $default
     * @return string
     */
    public static function guessMimeTypeOfExtension(string $fileExtension, string $default = 'application/octet-stream') : string
    {
        $fileExtension = strtolower($fileExtension);
        $mime = static::getMimeTypesByExtension()[$fileExtension];
        return $mime ?? $default;
    }
    
    protected static function getMimeTypesByExtension() : array
    {
        return [
            'aac' => 'audio/aac', // AAC audio
            'abw' => 'application/x-abiword', // AbiWord document
            'arc' => 'application/octet-stream', // Archive document (multiple files embedded)
            'avi' => 'video/x-msvideo', // AVI: Audio Video Interleave
            'azw' => 'application/vnd.amazon.ebook', // Amazon Kindle eBook format
            'bin' => 'application/octet-stream', // Any kind of binary data
            'bmp' => 'image/bmp', // Windows OS/2 Bitmap Graphics
            'bz' => 'application/x-bzip', // BZip archive
            'bz2' => 'application/x-bzip2', // BZip2 archive
            'csh' => 'application/x-csh', // C-Shell script
            'css' => 'text/css', // Cascading Style Sheets (CSS)
            'csv' => 'text/csv', // Comma-separated values (CSV)
            'doc' => 'application/msword', // Microsoft Word
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Microsoft Word (OpenXML)
            'eot' => 'application/vnd.ms-fontobject', // MS Embedded OpenType fonts
            'epub' => 'application/epub+zip', // Electronic publication (EPUB)
            'gif' => 'image/gif', // Graphics Interchange Format (GIF)
            'htm' => 'text/html', // HyperText Markup Language (HTML)
            'html' => 'text/html', // HyperText Markup Language (HTML)
            'ico' => 'image/x-icon', // Icon format
            'ics' => 'text/calendar', // iCalendar format
            'jar' => 'application/java-archive', // Java Archive (JAR)
            'jpeg' => 'image/jpeg', // JPEG images
            'jpg' => 'image/jpeg', // JPEG images
            'js' => 'application/javascript', // JavaScript (IANA Specification) (RFC 4329 Section 8.2)
            'json' => 'application/json', // JSON format
            'mid' => 'audio/midi audio/x-midi', // Musical Instrument Digital Interface (MIDI)
            'midi' => 'audio/midi audio/x-midi', // Musical Instrument Digital Interface (MIDI)
            'mpeg' => 'video/mpeg', // MPEG Video
            'mpkg' => 'application/vnd.apple.installer+xml', // Apple Installer Package
            'odp' => 'application/vnd.oasis.opendocument.presentation', // OpenDocument presentation document
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet', // OpenDocument spreadsheet document
            'odt' => 'application/vnd.oasis.opendocument.text', // OpenDocument text document
            'oga' => 'audio/ogg', // OGG audio
            'ogv' => 'video/ogg', // OGG video
            'ogx' => 'application/ogg', // OGG
            'otf' => 'font/otf', // OpenType font
            'png' => 'image/png', // Portable Network Graphics
            'pdf' => 'application/pdf', // Adobe Portable Document Format (PDF)
            'ppt' => 'application/vnd.ms-powerpoint', // Microsoft PowerPoint
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', // Microsoft PowerPoint (OpenXML)
            'rar' => 'application/x-rar-compressed', // RAR archive
            'rtf' => 'application/rtf', // Rich Text Format (RTF)
            'sh' => 'application/x-sh', // Bourne shell script
            'svg' => 'image/svg+xml', // Scalable Vector Graphics (SVG)
            'swf' => 'application/x-shockwave-flash', // Small web format (SWF) or Adobe Flash document
            'tar' => 'application/x-tar', // Tape Archive (TAR)
            'tif' => 'image/tiff', // Tagged Image File Format (TIFF)
            'tiff' => 'image/tiff', // Tagged Image File Format (TIFF)
            'ts' => 'application/typescript', // Typescript file
            'ttf' => 'font/ttf', // TrueType Font
            'txt' => 'text/plain', // Text, (generally ASCII or ISO 8859-n)
            'vsd' => 'application/vnd.visio', // Microsoft Visio
            'wav' => 'audio/wav', // Waveform Audio Format
            'weba' => 'audio/webm', // WEBM audio
            'webm' => 'video/webm', // WEBM video
            'webp' => 'image/webp', // WEBP image
            'woff' => 'font/woff', // Web Open Font Format (WOFF)
            'woff2' => 'font/woff2', // Web Open Font Format (WOFF)
            'xhtml' => 'application/xhtml+xml', // XHTML
            'xls' => 'application/vnd.ms-excel', // Microsoft Excel
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Microsoft Excel (OpenXML)
            'xml' => 'application/xml', // XML
            'xul' => 'application/vnd.mozilla.xul+xml', // XUL
            'zip' => 'application/zip', // ZIP archive
            '3gp' => 'video/3gpp', // 3GPP audio/video container
            '3g2' => 'video/3gpp2', // 3GPP2 audio/video container
            '7z' => 'application/x-7z-compressed' // 7-zip archive
        ];
    }
    
    /**
     * Returns TRUE if the mime type is a JSON format and FALSE otherwise
     * @param string $mimeType
     * @return bool
     */
    public static function detectJson(string $mimeType) : bool
    {
        return stripos($mimeType, 'json') !== false;
    }
    
    /**
     * Returns TRUE if the mime type is an XML format and FALSE otherwise
     * @param string $mimeType
     * @return bool
     */
    public static function detectXml(string $mimeType) : bool
    {
        return stripos($mimeType, 'xml') !== false && ! static::detectHtml($mimeType);
    }
    
    /**
     * Returns TRUE if the mime type is an HTML format and FALSE otherwise
     * @param string $mimeType
     * @return bool
     */
    public static function detectHtml(string $mimeType) : bool
    {
        return stripos($mimeType, 'html') !== false;
    }
}