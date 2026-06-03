<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\DataTypes\StringDataType;

/**
 * Renders an exception as indented text for CLI output
 * 
 * @author Andrej Kabachnik
 *
 */
class ExceptionCliRenderer extends AbstractExceptionRenderer
{
    private bool $includeTrace = true;
    private bool $onlyBottomTrace = false;
    
    /**
     * {@inheritdoc}
     * @see AbstractExceptionRenderer::render()
     */
    public function render(string $indent = '') : string
    {
        $result = $this->renderPlainText($this->includeTrace, $this->onlyBottomTrace);
        if ($indent !== '') {
            $result = StringDataType::indent($result, $indent);
        }
        return $result;
    }

    /**
     * @param bool $includeTrace
     * @param bool $onlyBottomTrace
     * @return $this
     */
    public function setIncludeTrace(bool $includeTrace, bool $onlyBottomTrace = true) : ExceptionCliRenderer
    {
        $this->includeTrace = $includeTrace;
        $this->onlyBottomTrace = $onlyBottomTrace;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see AbstractExceptionRenderer::getMessage()
     */
    public function getMessage()
    {
        $message = parent::getMessage();
        if ($logId = $this->getLogId()) {
            $message .= " (see Log-ID $logId)";
        }
        return $message;
    }
}