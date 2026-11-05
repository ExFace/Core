<?php
namespace exface\Core\Exceptions;

/**
 * Exception thrown when a local file resolves outside the directories approved for the read.
 *
 * This distinct error prevents path traversal, absolute-path, stream-wrapper, and symlink escapes
 * from being confused with ordinary missing or unreadable files.
 */
class FilePathNotAllowedError extends FileNotAccessibleError
{
    /**
     * Defaults to 403 Forbidden, because a path outside the allowed base folders is a rejected request,
     * not a server error. An explicitly set status code (setStatusCode()) still takes precedence,
     * so callers like HttpFileServerFacade or ShowDialogFromFile can map it differently (e.g. 404 or 400).
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 403) : int
    {
        return parent::getStatusCode($default);
    }
}