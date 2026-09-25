<?php
namespace exface\Core\Exceptions;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\ServerSoftwareDataType;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Filesystem\FileInfoExceptionTrait;

/**
 * Exception thrown if an file exists but is not accessible.
 * 
 * @author Andrej Kabachnik
 *        
 */
class FileNotAccessibleError extends RuntimeException implements ErrorExceptionInterface
{
    use FileInfoExceptionTrait;

    /**
     * Builds filesystem diagnostics and OS-specific access remediation guidance.
     *
     * @return string
     */
    protected function toMarkdown() : string
    {
        $fileInfo = $this->getFileInfo();
        if ($fileInfo === null) {
            return '';
        }

        $path = $fileInfo->getPathAbsolute();
        $exists = $fileInfo->exists();
        $isDirectory = $exists && $fileInfo->isDir();
        $readable = $fileInfo->isReadable();
        $writable = $fileInfo->isWritable();
        $osUser = ServerSoftwareDataType::getOsUser();
        $accessTarget = $exists ? $path : dirname($path);
        $userForCommand = $osUser ?? '<PHP_OS_USER>';

        $markdown = "## Access check\n\n";
        $markdown .= '- Path: ' . MarkdownDataType::escapeCodeInline($path) . "\n";
        $markdown .= '- Exists: **' . ($exists ? 'Yes' : 'No') . "**\n";
        $markdown .= '- Directory: **' . ($isDirectory ? 'Yes' : 'No') . "**\n";
        $markdown .= '- ' . ($isDirectory ? 'Readable (list and traverse)' : 'Readable') . ': **' . ($readable ? 'Yes' : 'No') . "**\n";
        $markdown .= '- ' . ($isDirectory ? 'Writable (create and delete entries)' : 'Writable') . ': **' . ($writable ? 'Yes' : 'No') . "**\n";
        $markdown .= '- PHP OS user: ' . ($osUser === null ? '**Unknown**' : MarkdownDataType::escapeCodeInline($osUser)) . "\n";
        $markdown .= '- Server software: ' . MarkdownDataType::escapeCodeInline(ServerSoftwareDataType::getServerSoftware() ?? PHP_SAPI) . "\n\n";
        $markdown .= "## Mitigation\n\n";
        if ($exists === false) {
            $markdown .= 'The requested path does not exist. Grant access to its parent directory '
                . MarkdownDataType::escapeCodeInline($accessTarget) . " so PHP can create it.\n\n";
        } else {
            $markdown .= 'Grant the PHP OS user the required access to this path. Review the permission change with your system administrator first.' . "\n\n";
        }

        if (ServerSoftwareDataType::isOsWindows()) {
            $command = 'icacls ' . escapeshellarg($accessTarget) . ' /grant ' . escapeshellarg($userForCommand . ':(M)') . ' /C';
            $markdown .= MarkdownDataType::escapeCodeBlock($command, 'powershell');
        } else {
            $command = 'setfacl -m ' . escapeshellarg('u:' . $userForCommand . ':rwX') . ' ' . escapeshellarg($accessTarget);
            $markdown .= MarkdownDataType::escapeCodeBlock($command, 'bash');
        }

        $markdown .= "\nRe-run the failed operation after changing permissions; PHP may cache filesystem status within the current request.";
        return $markdown;
    }
}