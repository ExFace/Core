<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsonEditorTrait;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\InputJson;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the complete shared configuration with jEasyUI's HTML escape default.
 */
class JsonEditorEscapingTest extends TestCase
{
    /**
     * Translation arrays and JSON text must use JS serialization in every facade.
     */
    public function testConfigurationUsesJavaScriptEscapingWithHtmlDefault() : void
    {
        $workbench = new Workbench();
        $widget = new InputJson(UiPageFactory::createBlank($workbench, ''));
        $widget->setMetaObject(new MetaObject(new Model($workbench)));
        $widget->setCaption('JSON "editor" & <tools>');
        $widget->setValue('{"text":"quotes \\" & <tags>"}', false);
        $element = new class($widget) extends AbstractJqueryElement {
            use JsonEditorTrait;

            /**
             * Omits facade registration for this local rendering fixture.
             */
            public function __construct(WidgetInterface $widget, ?FacadeInterface $facade = null)
            {
                $this->setWidget($widget);
            }

            /**
             * Matches the HTML default used by EuiAbstractElement.
             *
             * {@inheritDoc}
             * @see AbstractJqueryElement::escapeString()
             */
            public function escapeString($string, bool $encloseInQuotes = true, bool $forUseInHtml = true) : ?string
            {
                return parent::escapeString($string, $encloseInQuotes, $forUseInHtml);
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::getId()
             */
            public function getId()
            {
                return 'json-editor';
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::getWorkbench()
             */
            public function getWorkbench()
            {
                return $this->getWidget()->getWorkbench();
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::getAjaxUrl()
             */
            public function getAjaxUrl()
            {
                return 'api/editor?first=1&second=2';
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJs()
             */
            public function buildJs()
            {
                return $this->buildJsJsonEditor();
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildHtml()
             */
            public function buildHtml()
            {
                return $this->buildHtmlJsonEditor();
            }

            /**
             * Supplies translation data without starting the application lifecycle.
             */
            protected static function getTranslations(WorkbenchInterface $workbench, string $prefix = 'WIDGET.UXONEDITOR') : array
            {
                return ['HELP' => 'Help "quotes" & <tags>'];
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJsShowMessageError()
             */
            public function buildJsShowMessageError($message_body_js, $title = null)
            {
                return 'console.error(' . $message_body_js . ')';
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::buildJsShowMessageSuccess()
             */
            public function buildJsShowMessageSuccess($message_body_js, $title = null)
            {
                return 'console.log(' . $message_body_js . ')';
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

        $js = $element->buildJs();
        self::assertStringContainsString('translations: {"HELP":"Help \\"quotes\\" & <tags>"}', $js);
        self::assertStringContainsString('name: "JSON \\"editor\\" & <tools>"', $js);
        self::assertStringContainsString('ajaxUrl: "api/editor?first=1&second=2"', $js);
        self::assertStringContainsString($element->escapeString($widget->getValueWithDefaults(), true, false), $js);
        self::assertStringNotContainsString('&quot;', $js);
        self::assertStringNotContainsString('&amp;', $js);
    }
}
