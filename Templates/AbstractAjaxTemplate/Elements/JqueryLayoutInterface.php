<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

interface JqueryLayoutInterface
{

    /**
     * Returns an inline JavaScript-Snippet to start the layouting of the widget.
     *
     * @return string
     */
    public function buildJsLayouter();

    /**
     * Returns a JavaScript-Function that layouts the widget.
     *
     * @return string
     */
    public function buildJsLayouterFunction();
}