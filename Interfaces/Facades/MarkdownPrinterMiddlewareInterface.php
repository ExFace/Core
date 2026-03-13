<?php

namespace exface\Core\Interfaces\Facades;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

interface MarkdownPrinterMiddlewareInterface extends MiddlewareInterface
{
    public function getMarkdown(ServerRequestInterface $request) : string;
}