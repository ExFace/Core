<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Contexts\NotificationContext;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\DataSheetFactory;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class Notification implements WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $uxon = null;
    
    private $title = null;
    
    private $icon = null;
    
    private $widgetUxon = null;
    
    private $widgetObject = null;
    
    private $buttonsUxon = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon, MetaObjectInterface $widgetObject)
    {
        $this->workbench = $workbench;    
        $this->uxon = $uxon;
        $this->widgetObject = $widgetObject;
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->uxon;
        // TODO add eventually missing properties
        
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    protected function getWidgetObject() : MetaObjectInterface
    {
        return $this->widgetObject;
    }
    
    public function getTitle() : string
    {
        return $this->title;
    }
    
    /**
     * The title will appear in the notification area
     * 
     * @uxon-property title
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $value
     * @return Notification
     */
    protected function setTitle(string $value) : Notification
    {
        $this->title = $value;
        return $this;
    }
    
    public function getContentWidgetUxon() : ?UxonObject
    {
        return $this->widgetUxon;
    }
    
    protected function hasContentWidget() : bool
    {
        return $this->widgetUxon !== null;
    }
    
    /**
     * The widget to show in the expanded view of the notification
     * 
     * @uxon-property content_widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-template {"widget_type":""}
     * 
     * @param UxonObject $uxon
     * @return Notification
     */
    protected function setContentWidget(UxonObject $uxon) : Notification
    {
        $this->widgetUxon = $uxon;
        return $this;
    }
    
    /**
     * The body (long text) of the notification - in case you don't need a complex `content_widget`.
     * 
     * @uxon-property body
     * @uxon-type string
     * 
     * @param string $value
     * @return Notification
     */
    protected function setBody(string $value) : Notification
    {
        if ($this->uxon->hasProperty('content_widget') || $this->widgetUxon !== null) {
            throw new RuntimeException('Cannot set notification `body` and `content_widget` at the same time!');
        }
        $textUxon = new UxonObject([
            'widget_type' => 'Text',
            'hide_caption' => true,
            'text' => $value
        ]);
        $this->uxon->setProperty('content_widget', $textUxon);
        $this->widgetUxon = $textUxon;
        return $this;
    }
    
    public function getButtonsUxon() : ?UxonObject
    {
        return $this->buttonsUxon;
    }
    
    /**
     * Buttons to show for the notification
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"caption": "", "action": {"alias": "", "object_alias": ""}}]
     * 
     * @param UxonObject $value
     * @return Notification
     */
    protected function setButtons(UxonObject $value) : Notification
    {
        $this->buttonsUxon = $value;
        return $this;
    }
    
    public function getIcon() : ?string
    {
        return $this->icon;
    }
    
    /**
     * An icon to show for this notification
     * 
     * @uxon-property icon
     * @uxon-type icon
     * 
     * @param string $value
     * @return Notification
     */
    public function setIcon(string $value) : Notification
    {
        $this->icon = $value;
        return $this;
    }
    
    public function sendTo(array $userUids) : Notification
    {
        $widgetUxon = new UxonObject([
            'object_alias' => $this->getWidgetObject()->getAliasWithNamespace(),
            'width' => 1,
            'height' => 'auto',
            'caption' => $this->getTitle(),
            'widgets' => $this->getContentWidgetUxon() ? [$this->getContentWidgetUxon()->toArray()] : [],
            'buttons' => $this->getButtonsUxon() ? $this->getButtonsUxon()->toArray() : []
        ]);
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.NOTIFICATION');
        foreach ($userUids as $userUid) {
            $ds->addRow([
                'USER' => $userUid,
                'TITLE' => $this->getTitle(),
                'ICON' => $this->getIcon(),
                'WIDGET_UXON' => $widgetUxon->toJson()
            ]);
        }
        
        $ds->dataCreate(false);
        
        return $this;
    }
}