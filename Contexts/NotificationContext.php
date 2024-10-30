<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Widgets\Menu;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Widgets\Button;

/**
 * The ObjectBasketContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 *
 * Technically it stores a data sheet with instances for each object in the basket. Regardless of the input, this sheet will always
 * contain the default display columns.
 *
 * @author Andrej Kabachnik
 *        
 */
class NotificationContext extends AbstractContext
{
    private $data = null;
    
    private $counter = null;
    
    /**
     * The object basket context resides in the window scope by default.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->getContext()->getScopeUser();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $this->counter = $uxon->getProperty('counter');
        return;
    }

    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'counter' => $this->counter
        ]);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        return $this->getNotificationData() ? $this->getNotificationData()->countRows() : 0;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon() : ?string
    {
        return Icons::ENVELOPE_O;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {       
        /* @var $menu \exface\Core\Widgets\Menu */
        $menu = WidgetFactory::create($container->getPage(), 'Menu', $container);
        $menu->setCaption($this->getName());
        
        // Fill with buttons
        $menu = $this->createMessageButtons($menu);
        $menu->addButton($menu->createButton(new UxonObject([
            'caption' => 'Show all',
            'action' => [
                'alias' => 'exface.Core.ShowDialog',
                'dialog' => [
                    'caption' => 'My notifications',
                    'cacheable' => false,
                    'widgets' => [
                        [
                            'widget_type' => 'DataTable',
                            'object_alias' => 'exface.Core.NOTIFICATION',
                            'hide_header' => true,
                            'columns' => [
                                ['attribute_alias' => 'ISREAD', 'hide_caption' => true, 'width' => '2rem'],
                                ['attribute_alias' => 'CREATED_ON'],
                                ['attribute_alias' => 'TITLE']
                            ]
                        ]
                    ]
                ]
            ]
        ])));
        
        $container->addWidget($menu);
        
        /*
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous()) {
            return $container;
        }
        
        $table = WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'DataTableResponsive',
            'object_alias' => 'exface.Core.NOTIFICATION',
            'hide_caption' => true,
            'hide_footer' => true,
            'paginate' => false,
            'filters' => [
                [
                    'attribute_alias' => 'USER__USERNAME', 
                    'value' => $authToken->getUsername(),
                    'comparator' => ComparatorDataType::EQUALS,
                    'hidden' => true
                ]
            ],
            'columns' => [
                [
                    'attribute_alias' => 'TITLE',
                    'visibility' => WidgetVisibilityDataType::PROMOTED
                ],
                [
                    'attribute_alias' => 'CREATED_ON',
                    'visibility' => WidgetVisibilityDataType::PROMOTED
                ]
            ],
            'sorters' => [
                [
                    'attribute_alias' => 'CREATED_ON',
                    'direction' => SortingDirectionsDataType::DESC
                ]
            ],
            'buttons' => [
                [
                    'caption' => 'Open',
                    'bind_to_left_click' => true,
                    'action' => [
                        'alias' => 'exface.Core.ShowDialogFromData',
                        'uxon_attribute' => 'WIDGET_UXON'
                    ]
                ]
            ]
        ]));
        
        $container->addWidget($table);*/
        
        return $container;
    }
    
    public function getName(){
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.NOTIFICATION.NAME');
    }
    
    /**
     * 
     * @param Menu $menu
     * @return Menu
     */
    protected function createMessageButtons(Menu $menu) : Menu
    {
        $data = $this->getNotificationData();
        if ($data === null) {
            return $menu;
        }
        
        $btn = null;
        $btnGrp = null;
        $grps = [];
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        foreach ($data->getRows() as $rowNo => $row) {
            $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $renderer->addPlaceholder(
                (new DataRowPlaceholders($data, $rowNo, '~notification:'))
                ->setSanitizeAsUxon(true)
            );
            $widgetJson = $renderer->render($row['WIDGET_UXON']);
            
            $dateDiff = DateDataType::diff($row['CREATED_ON'])->days;
            switch (true) {
                case $dateDiff === 0: $grpCaption = $translator->translate('LOCALIZATION.DATE.TODAY'); break;
                case $dateDiff === 1: $grpCaption = $translator->translate('LOCALIZATION.DATE.YESTERDAY'); break;
                default: $grpCaption = DateDataType::formatDateLocalized(new \DateTime($row['CREATED_ON']), $this->getWorkbench());
            }
            
            if (null === $btnGrp = ($grps[$grpCaption] ?? null)) {
                $btnGrp = $menu->createButtonGroup(new UxonObject([
                    'caption' => $grpCaption
                ]));
                $menu->addButtonGroup($btnGrp);
                $grps[$grpCaption] = $btnGrp;
            }
            
            try {
                $btn = $btnGrp->createButton(new UxonObject([
                    'caption' => $row['TITLE'],
                    'action' => [
                        'alias' => 'exface.Core.ShowDialog',
                        'widget' => UxonObject::fromJson($widgetJson)->setProperty('cacheable', false)
                    ]
                ]));
                $dialog = $btn->getAction()->getWidget();
                $this->fillMessageDialog($dialog, $row, $translator);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException(
                    new ContextRuntimeError($this, 'Corrupted notification detected (UID "' . $row['UID'] . '"): ' . $e->getMessage(), null, $e)
                );
                $btn = $this->createMessageButtonForCorruptNotification($btnGrp, $row);
            }
            
            if ($row['ICON'] !== null) {
                $btn->setIcon($row['ICON']);
            } else {
                $btn->setShowIcon(false);
            }
            
            $btnGrp->addButton($btn);
        }
        
        if ($btnGrp) {
            $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'caption' => $translator->translate('CONTEXT.NOTIFICATION.MARK_ALL_READ'),
                'action' => [
                    'alias' => 'exface.Core.NotificationAllRead',
                    'object_alias' => 'exface.Core.NOTIFICATION',
                    'input_rows_min' => 0,
                    'input_data_sheet' => [
                        'object_alias' => 'exface.Core.NOTIFICATION'
                    ]
                ]
            ])));
        }
        
        return $menu;
    }
    
    /**
     * 
     * @param ButtonGroup $btnGrp
     * @param string[] $row
     * @return Button
     */
    protected function createMessageButtonForCorruptNotification(ButtonGroup $btnGrp, array $row) : Button
    {
        $btn = $btnGrp->createButton(new UxonObject([
            'caption' => $row['TITLE'],
            'action' => [
                'alias' => 'exface.Core.ShowDialog',
                'dialog' => [
                    'cacheable' => false,
                    'widgets' => [
                        [
                            'widget_type' => 'Message',
                            'type' => 'error',
                            'text' => 'Corrupted notification. This message cannot be displayed!'
                        ]
                    ]
                ]
            ]
        ]));
        return $btn;
    }
    
    /**
     * 
     * @param Dialog $dialog
     * @param string[] $row
     * @param TranslationInterface $translator
     * @return Dialog
     */
    protected function fillMessageDialog(Dialog $dialog, array $row, TranslationInterface $translator) : Dialog
    {
        $dialog->setHeader(new UxonObject([
            'widgets' => [
                [
                    'widget_type' => 'WidgetGroup',
                    'widgets' => [
                        [
                            'attribute_alias' => 'UID',
                            'widget_type' => 'InputHidden',
                            'value' => $row['UID']
                        ],[
                            'attribute_alias' => 'MODIFIED_ON',
                            'widget_type' => 'InputHidden',
                            'value' => $row['MODIFIED_ON']
                        ],[
                            'attribute_alias' => 'CREATED_BY_USER__USERNAME',
                            'widget_type' => 'Display',
                            'caption' => $translator->translate('CONTEXT.NOTIFICATION.MESSAGE_FROM'),
                            'value' => $row['CREATED_BY_USER__USERNAME']
                        ], [
                            'attribute_alias' => 'CREATED_ON',
                            'widget_type' => 'Display',
                            'caption' => $translator->translate('CONTEXT.NOTIFICATION.MESSAGE_SENT_AT'),
                            'value' => DateTimeDataType::formatDateLocalized(new \DateTime($row['CREATED_ON']), $this->getWorkbench())
                        ]
                        
                    ]
                ]
            ]
        ]));
        
        //add read button
        $dialog->addButton($dialog->createButton(new UxonObject([
            'action_alias' => 'exface.Core.NotificationRead',
            'align' => $dialog->countButtons() <= 1 ? EXF_ALIGN_OPPOSITE : EXF_ALIGN_DEFAULT,
            'visibility' => WidgetVisibilityDataType::NORMAL
        ])));
        
        //add delete button
        $dialog->addButton($dialog->createButton(new UxonObject([
            'action_alias' => 'exface.Core.DeleteObject',
            'align' => $dialog->countButtons() <= 2 ? EXF_ALIGN_OPPOSITE : EXF_ALIGN_DEFAULT,
            'visibility' => WidgetVisibilityDataType::NORMAL,
            'color' => Colors::SEMANTIC_ERROR
        ])));
        
        return $dialog;
    }
    
    protected function getNotificationData() : ?DataSheetInterface
    {
        if ($this->data !== null) {
            return $this->data;
        }
        
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous()) {
            return null;
        }
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.NOTIFICATION');
        $ds->getColumns()->addFromSystemAttributes();
        $ds->getColumns()->addMultiple([
            'CREATED_ON',
            'MODIFIED_ON',
            'CREATED_BY_USER__USERNAME',
            'TITLE',
            'ICON',
            'WIDGET_UXON'
        ]);
        $ds->getSorters()->addFromString('CREATED_ON', SortingDirectionsDataType::DESC);
        $ds->getFilters()->addConditionFromString('USER__USERNAME', $authToken->getUsername(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('ISREAD', 0, ComparatorDataType::EQUALS);
        $ds->dataRead();
        $this->data = $ds;
        return $ds;
    }
    
    /**
     * Sends a communication message to one or more users
     * 
     * This method is static in order to allow sending notifications for unauthenticated or technical users,
     * that will generally not have access to their own notification context, but may need to send notifications
     * to others.
     * 
     * @param NotificationMessage $notification
     * @param array $userUids
     * @return NotificationMessage
     */
    public static function send(NotificationMessage $notification, array $userUids) : NotificationMessage
    {
        $title = $notification->getTitle() ?? StringDataType::truncate($notification->getText(), 60, true);
        $bodyUxon= $notification->getBodyWidgetUxon();
        $widgetUxon = new UxonObject([
            'widget_type' => 'Dialog',
            'object_alias' => 'exface.Core.NOTIFICATION',
            'width' => ($bodyUxon !== null && $bodyUxon->hasProperty('width') ? $bodyUxon->getProperty('width') : 1),
            'height' => 'auto',
            'caption' => $title,
            'widgets' => ($bodyUxon !== null ? [$bodyUxon->toArray()] : []),
            'buttons' => ($notification->getButtonsUxon() ? $notification->getButtonsUxon()->toArray() : [])
        ]);
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($notification->getWorkbench(), 'exface.Core.NOTIFICATION');
        foreach ($userUids as $userUid) {
            $ds->addRow([
                'USER' => $userUid,
                'TITLE' => $title,
                'ICON' => $notification->getIcon(),
                'WIDGET_UXON' => $widgetUxon->toJson()
            ]);
        }
        
        if (! $ds->isEmpty()) {
            $ds->dataCreate(false);
        }
        
        return $notification;
    }
}