<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * An empty installer that does not perform any work. You can pass an optional message to be yielded 
 * whenever `install()`, `backup()` or `uninstall()` is called.
 * 
 * You can use this installer as a stub or to inject debugging messages and logging into your deployment logic.
 */
class AppDebugInstaller implements AppInstallerInterface
{
    private ?string $message;
    private string $indent;

    function __construct(
        string $message = null,
        string $indent = '  '
    )
    {
        $this->indent = $indent;
        $this->message = str_ends_with($message, PHP_EOL) ? $message : $message . PHP_EOL;
    }
    
    /**
     * @inheritDoc
     */
    public function install(string $source_absolute_path): \Iterator
    {
        yield $this->getMessage() ?? new \EmptyIterator();
    }

    /**
     * @inheritDoc
     */
    public function backup(string $absolute_path): \Iterator
    {
        yield $this->getMessage() ?? new \EmptyIterator();
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): \Iterator
    {
        yield $this->getMessage()  ?? new \EmptyIterator();
    }

    /**
     * STUB! Do not use.
     * @deprecated 
     */
    public function getWorkbench() : WorkbenchInterface
    {
        throw new NotImplementedError('Method "getWorkbench()" is only a stub in "' . self::class . '"!');
    }

    /**
     * STUB! Do not use.
     * @deprecated
     */
    public function getApp() : WorkbenchInterface
    {
        throw new NotImplementedError('Method "getApp()" is only a stub in "' . self::class . '"!');
    }

    /**
     * @return string
     */
    public function getOutputIndentation() : string
    {
        return $this->indent;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setOutputIndentation(string $value) : AppDebugInstaller
    {
        $this->indent = $value;
        return $this;
    }

    /**
     * @param bool $withIndentation
     * @return string|null
     */
    public function getMessage(bool $withIndentation = true) : ?string
    {
        if($this->message === null) {
            return null;
        }
        
        return $this->getOutputIndentation() . $this->message;
    }
}