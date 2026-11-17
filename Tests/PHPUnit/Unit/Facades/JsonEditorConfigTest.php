<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsonEditorTrait;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\InputJson;
use PHPUnit\Framework\TestCase;

/**
 * Covers editor configuration generation without a persisted metamodel.
 */
class JsonEditorConfigTest extends TestCase
{
    /**
     * Invalid JSON and quotes must be emitted as a JS value, never as raw code.
     */
    public function testInitializationPreservesInvalidJsonAndEscapesStrings() : void
    {
        $workbench = new Workbench();
        $widget = new InputJson(UiPageFactory::createBlank($workbench, ''));
        $widget->setMetaObject(new MetaObject(new Model($workbench)));
        $value = '{"title":"unfinished\'';
        $widget->setValue($value, false);
        $element = $this->createElement($widget);

        $js = $element->buildJs();
        self::assertStringContainsString($element->escapeString($value), $js);
        self::assertStringStartsWith('exfJsonEditor.create(document.getElementById("editor-with-hyphens"),', $js);
        self::assertStringNotContainsString('var editor-with-hyphens', $js);
    }

    /**
     * Values and disabled state must target the same host-owned adapter.
     */
    public function testValueAndStateAccessShareTheHostInstance() : void
    {
        $widget = new InputJson(UiPageFactory::createBlank(new Workbench(), ''));
        $element = $this->createElement($widget);
        $getter = 'document.getElementById("editor-with-hyphens").exfJsonEditor';

        self::assertSame($getter . '.getValue()', $element->buildJsValueGetter());
        self::assertSame($getter . '.setValue(oValue)', $element->buildJsValueSetter('oValue'));
        self::assertSame($getter . '.setDisabled(true)', $element->buildJsSetDisabled(true));
    }

    /**
     * Only the unrelated facade services are omitted from this local generator fixture.
     */
    private function createElement(InputJson $widget) : AbstractJqueryElement
    {
        return new class($widget) extends AbstractJqueryElement {
            use JsonEditorTrait;

            /**
             * Initializes a rendering-only fixture without registering a facade.
             */
            public function __construct(WidgetInterface $widget, ?FacadeInterface $facade = null)
            {
                $this->setWidget($widget);
            }

            /**
             * {@inheritDoc}
             * @see AbstractJqueryElement::getId()
             */
            public function getId()
            {
                return 'editor-with-hyphens';
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
             * Supplies the external configuration boundary for the initialization test.
             */
            protected function buildJsJsonEditorConfig() : string
            {
                return '{}';
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
    }
}
