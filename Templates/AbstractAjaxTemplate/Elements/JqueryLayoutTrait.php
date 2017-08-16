<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

trait JqueryLayoutTrait {

    /**
     * Returns an inline JavaScript-Snippet to start the layouting of the widget.
     *
     * @return string
     */
    public function buildJsLayouter()
    {
        return $this->buildJsFunctionPrefix() . 'layouter()';
    }
}