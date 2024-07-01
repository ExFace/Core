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
    public function getLabelOfValue($value = null): ?string
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
     * @param string $fileExtension
     * @param string $default
     * @return string
     */
    public static function guessMimeTypeOfExtension(string $fileExtension, string $default = 'application/octet-stream') : string
    {
        $fileExtension = strtolower(trim($fileExtension));
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
    
    /**
     * 
     * @param string $absolutePath
     * @return string|NULL
     */
    public static function findMimeTypeOfFile(string $absolutePath) : ?string
    {
        switch (true) {
            case function_exists("finfo_file"):
                $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
                $mime = finfo_file($finfo, $absolutePath);
                finfo_close($finfo);
                return $mime;
            case function_exists("mime_content_type"):
                return mime_content_type($absolutePath);
            case ! stristr(ini_get("disable_functions"), "shell_exec"):
                // http://stackoverflow.com/a/134930/1593459
                $absolutePath = escapeshellarg($absolutePath);
                $mime = shell_exec("file -bi " . $absolutePath);
                return $mime;                
        }
        
        return null;
    }
    
    /**
     * Returns TRUE if the provided string is a valid mime type and FALSE otherwise
     * 
     * @link https://stackoverflow.com/a/48046041
     * 
     * @param string $str
     * @return bool
     */
    public static function isValidMimeType(string $str) : bool
    {
        return preg_match("@(application|audio|font|example|image|message|model|multipart|text|video|x-(?:[0-9A-Za-z!#$%&'*+.^_`|~-]+))/([0-9A-Za-z!#$%&'*+.^_`|~-]+)@", $str) === 1 ? true : false;
    }
    
    /**
     * 
     * @param string $type
     * @return bool
     */
    public static function isBinary(string $type) : bool
    {
        switch (true) {
            case stripos($type, 'text') !== false: return false;
            case stripos($type, 'json') !== false: return false;
            case stripos($type, 'xml') !== false: return false;
            case stripos($type, 'html') !== false: return false;
        }
        return true;
    }
    
    /**
     * 
     * @param string $type
     * @return bool
     */
    public static function isImage(string $type) : bool
    {
        switch (true) {
            case stripos($type, 'image') === 0: return true;
        }
        return false;
    }
    
    /**
     * Tries to guess the file extension from a given mime type.
     * 
     * Returns NULL for unrecognized mime types.
     * 
     * @link https://stackoverflow.com/questions/16511021/convert-mime-type-to-file-extension-php
     * @param string $mime
     * @return string|NULL
     */
    public static function findFileExtension(string $mime) : ?string
    {
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'audio/mp4'                                                                 => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'font/otf'                                                                  => 'otf',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'font/ttf'                                                                  => 'ttf',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'font/woff'                                                                 => 'woff',
            'font/woff2'                                                                => 'woff2',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];
        
        return array_key_exists(mb_strtolower($mime), $mime_map) === true ? $mime_map[$mime] : null;
    }
}