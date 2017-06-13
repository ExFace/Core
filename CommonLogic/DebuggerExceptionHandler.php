<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FlattenException;

class DebuggerExceptionHandler extends ExceptionHandler
{
    /**
     * 
     * {@inheritDoc}
     * @see \Symfony\Component\Debug\ExceptionHandler::getContent()
     */
    public function getContent(FlattenException $exception)
    {
        $content = parent::getContent($exception);
        return <<<HTML
    <div class="exception">
        {$content}
    </div>
HTML;
    }
    
    public function getStylesheet(FlattenException $exception)
    {
        return <<<'EOF'

            .exception a { cursor: pointer; text-decoration: none; }
            .exception a:hover { text-decoration: underline; }
            .exception abbr[title] { border-bottom: none; cursor: help; text-decoration: none; }

            .exception code, .exception pre { font: 13px/1.5 Consolas, Monaco, Menlo, "Ubuntu Mono", "Liberation Mono", monospace; }

            .exception table, .exception tr, .exception th, .exception td { background: #FFF; border-collapse: collapse; vertical-align: top; }
            .exception table { background: #FFF; border: 1px solid #E0E0E0; box-shadow: 0px 0px 1px rgba(128, 128, 128, .2); margin: 1em 0; width: 100%; }
            .exception table th, .exception table td { border: solid #E0E0E0; border-width: 1px 0; padding: 8px 10px; }
            .exception table th { background-color: #E0E0E0; font-weight: bold; text-align: left; }

            .exception .hidden-xs-down { display: none; }
            .exception .block { display: block; }
            .exception .break-long-words { -ms-word-break: break-all; word-break: break-all; word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; }
            .exception .text-muted { color: #999; }

            .exception .container { max-width: 1024px; margin: 0 auto; padding: 0 15px; }
            .exception .container::after { content: ""; display: table; clear: both; }

            .exception .exception-summary { background: #B0413E; border-bottom: 2px solid rgba(0, 0, 0, 0.1); border-top: 1px solid rgba(0, 0, 0, .3); flex: 0 0 auto; margin-bottom: 30px; }

            .exception .exception-message-wrapper { display: flex; align-items: center; min-height: 70px; }
            .exception .exception-message { flex-grow: 1; padding: 30px 0; }
            .exception .exception-message, .exception .exception-message a { color: #FFF; font-size: 21px; font-weight: 400; margin: 0; }
            .exception .exception-message.long { font-size: 18px; }
            .exception .exception-message a { text-decoration: none; }
            .exception .exception-message a:hover { text-decoration: underline; }

            .exception .exception-illustration { flex-basis: 111px; flex-shrink: 0; height: 66px; margin-left: 15px; opacity: .7; }

            .exception .trace + .trace { margin-top: 30px; }
            .exception .trace-head .trace-class { color: #222; font-size: 18px; font-weight: bold; line-height: 1.3; margin: 0; position: relative; }

            .exception .trace-message { font-size: 14px; font-weight: normal; margin: .5em 0 0; }

            .exception .trace-file-path, .trace-file-path a { margin-top: 3px; color: #999; color: #795da3; color: #B0413E; color: #222; font-size: 13px; }
            .exception .trace-class { color: #B0413E; }
            .exception .trace-type { padding: 0 2px; }
            .exception .trace-method { color: #B0413E; color: #222; font-weight: bold; color: #B0413E; }
            .exception .trace-arguments { color: #222; color: #999; font-weight: normal; color: #795da3; color: #777; padding-left: 2px; }

            @media (min-width: 575px) {
                .exception .hidden-xs-down { display: initial; }
            }
EOF;
    }
}