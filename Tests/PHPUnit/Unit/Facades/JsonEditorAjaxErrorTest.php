<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsonEditorTrait;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shared adapter's delegation to a facade's server-error renderer.
 */
class JsonEditorAjaxErrorTest extends TestCase
{
    /**
     * The callback must preserve the response body and dispatch through the facade.
     */
    public function testAjaxCallbackUsesFacadeErrorRenderer() : void
    {
        $element = new class extends AbstractJqueryElement {
            use JsonEditorTrait;

            /**
             * This generator-only fixture does not register a widget or facade.
             */
            public function __construct(?WidgetInterface $widget = null, ?FacadeInterface $facade = null)
            {
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJs()
             */
            public function buildJs()
            {
                return $this->buildJsJsonEditorOnAjaxError();
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildHtml()
             */
            public function buildHtml()
            {
                return '';
            }

            /**
             * Marks the facade rendering boundary, leaving callback generation real.
             *
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJsShowError()
             */
            public function buildJsShowError($message_body_js, $title_js = null)
            {
                return 'showServerWidget(' . $message_body_js . ', ' . $title_js . ');';
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJsBusyIconShow()
             */
            public function buildJsBusyIconShow($global = false)
            {
                return '';
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJsBusyIconHide()
             */
            public function buildJsBusyIconHide($global = false)
            {
                return '';
            }
        };

        self::assertStringContainsString(
            'showServerWidget(oRequest.responseText, oRequest.status + " " + oRequest.statusText);',
            $element->buildJs()
        );
    }
}
