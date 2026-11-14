<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Filesystem\FileInterface;

/**
 * Data connections that can download files from URLs within their own address space.
 */
interface FileDownloadConnectionInterface extends DataConnectionInterface
{
    /**
     * Downloads a file and verifies it against the expected MIME type and size limit.
     *
     * @param string $url Absolute or relative to the connection's base address.
     * @param string $filename
     * @param string $mimeType
     * @param int $maxBytes
     * @return FileInterface
     */
    public function downloadFile(string $url, string $filename, string $mimeType, int $maxBytes) : FileInterface;
}
