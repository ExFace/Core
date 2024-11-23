<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Communication\Messages\AnnouncementMessage;
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
    private $notificationsSheet = null;

    private $announcementsSheet = null;
    
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
        
        // Add common buttons
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $bottomGroup = $menu->createButtonGroup();
        $bottomGroup->addButton($bottomGroup->createButton(new UxonObject([
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
                            'filters' => [
                                [
                                    'attribute_alias' => 'USER',
                                    'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                    'comparator' => ComparatorDataType::EQUALS
                                ]
                            ],
                            'columns' => [
                                ['attribute_alias' => 'ISREAD', 'hide_caption' => true, 'width' => '2rem'],
                                ['attribute_alias' => 'SENT_ON'],
                                ['attribute_alias' => 'TITLE']
                            ]
                        ]
                    ]
                ]
            ]
        ])));
        if (! $menu->isEmpty()) {
            $bottomGroup->addButton($bottomGroup->createButton(new UxonObject([
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
        
        $menu->addButtonGroup($bottomGroup);
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
                    'attribute_alias' => 'SENT_ON',
                    'visibility' => WidgetVisibilityDataType::PROMOTED
                ]
            ],
            'sorters' => [
                [
                    'attribute_alias' => 'SENT_ON',
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
            
            $dateDiff = DateDataType::diff($row['SENT_ON'])->days;
            switch (true) {
                case $dateDiff === 0: $grpCaption = $translator->translate('LOCALIZATION.DATE.TODAY'); break;
                case $dateDiff === 1: $grpCaption = $translator->translate('LOCALIZATION.DATE.YESTERDAY'); break;
                default: $grpCaption = DateDataType::formatDateLocalized(new \DateTime($row['SENT_ON']), $this->getWorkbench());
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
                            'attribute_alias' => 'SENT_BY',
                            'widget_type' => 'Display',
                            'caption' => $translator->translate('CONTEXT.NOTIFICATION.MESSAGE_FROM'),
                            'value' => $row['SENT_BY']
                        ], [
                            'attribute_alias' => 'SENT_ON',
                            'widget_type' => 'Display',
                            'caption' => $translator->translate('CONTEXT.NOTIFICATION.MESSAGE_SENT_AT'),
                            'value' => DateTimeDataType::formatDateLocalized(new \DateTime($row['SENT_ON']), $this->getWorkbench())
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
        if ($this->notificationsSheet !== null) {
            return $this->notificationsSheet;
        }
        
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous()) {
            return null;
        }
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.NOTIFICATION');
        $ds->getColumns()->addFromSystemAttributes();
        $ds->getColumns()->addMultiple([
            'MODIFIED_ON',
            'SENT_ON',
            'SENT_BY',
            'TITLE',
            'ICON',
            'WIDGET_UXON',
            'REFERENCE'
        ]);
        $ds->getSorters()->addFromString('SENT_ON', SortingDirectionsDataType::DESC);
        $ds->getFilters()->addConditionFromString('USER', $user->getUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('HIDE_FROM_INBOX', 0, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('ISREAD', 0, ComparatorDataType::EQUALS);
        $ds->dataRead();
        $this->notificationsSheet = $ds;
        return $ds;
    }

    protected function getAnnouncementsData() : DataSheetInterface
    {
        if ($this->announcementsSheet !== null) {
            return $this->announcementsSheet;
        }
        
        $currentUser = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.ANNOUNCEMENT');
        $ds->getColumns()->addFromSystemAttributes();
        $ds->getColumns()->addMultiple([
            'TITLE',
            'COMMUNICATION_TEMPLATE__MESSAGE_UXON',
            'MESSAGE_TYPE',
            'MESSAGE_UXON',
            'SHOW_FROM',
            'SHOW_TO',
            'NOTIFICATION__READ_ON:MAX',
            'NOTIFICATION__UID:LIST_DISTINCT'
        ]);
        $ds->getSorters()->addFromString('SHOW_FROM', SortingDirectionsDataType::DESC);
        $ds->getFilters()->addNestedOR()
            ->addConditionFromString('NOTIFICATION__USER', $currentUser->getUid(), ComparatorDataType::EQUALS)
            ->addConditionForAttributeIsNull('NOTIFICATION__ANNOUNCEMENT');
        $ds->dataRead();
        $this->announcementsSheet = $ds;
        return $ds;
    }

    /**
     * 
     * @param array $row
     * @return \exface\Core\Communication\Messages\AnnouncementMessage
     */
    protected function createAnnouncementMessage(array $row) : AnnouncementMessage
    {

        $uxon = UxonObject::fromJson($row['COMMUNICATION_TEMPLATE__MESSAGE_UXON'] ?? '{}');
        if ($row['MESSAGE_UXON']) {
            $uxon = $uxon->extend(UxonObject::fromJson($row['MESSAGE_UXON']));
        }

        $msg = new AnnouncementMessage($this->getWorkbench(), $uxon);
        $msg->setTitle($row['TITLE']);
        if (null !== $val = $row['MESSAGE_TYPE']) {
            $msg->setMessageType($val);
        }
        $msg->setReference($row['UID']);
        $msg->setShowBetween($row['SHOW_FROM'], $row['SHOW_TO'] ?? null);

        return $msg;
    }

    /**
     * 
     * @return AnnouncementMessage[]
     */
    public function getAnnouncements() : array
    {
        $msgs = [];
        $currentUser = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        $data = $this->getAnnouncementsData();
        $isReadColName = $data->getColumns()->getByExpression('NOTIFICATION__READ_ON:MAX')->getName();
        $uidColName = $data->getColumns()->getByExpression('NOTIFICATION__UID:LIST_DISTINCT')->getName();
        $now = DateTimeDataType::now();
        foreach ($data->getRows() as $row) {
            $msg = $this->createAnnouncementMessage($row);
            if ($msg->isVisible($currentUser)) {
                switch (true) {
                    // If the message was never sent to this user, send it now
                    case ($row[$uidColName] ?? null) === null:
                        $this::send($msg, [$currentUser->getUid()]);
                        break;
                    // If it was sent and read already, ignore it (if READ_ON is in the future, it is just
                    // scheduled to disappear, so right now it is still to be sent)
                    case $row[$isReadColName] !== null && [$isReadColName] < $now:
                        continue 2;
                    // TODO detect if the notiication was created earlier, than the last
                    // change of the announcement. In this case, delete the notification
                    // and resend it to make the user see the changes.

                    default: 
                        // just output the message - it was already sent and is still visible
                }
                $msgs[] = $msg;
            }
        }
        return $msgs;
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
            $row = [
                'USER' => $userUid,
                'TITLE' => $title,
                'ICON' => $notification->getIcon(),
                'FOLDER' => $notification->getFolder(),
                'SENT_BY' => $notification->getSenderName() ?? $notification->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUsername(),
                'SENT_ON' => $notification->getSendingTime() ?? DateTimeDataType::now(),
                'REFERENCE' => $notification->getReference(),
                'WIDGET_UXON' => $widgetUxon->toJson()
            ];

            if (($notification instanceof AnnouncementMessage) && null !== $showTo = $notification->getShowTo()) {
                $row['READ_ON'] = $showTo;
            }

            $ds->addRow($row);
        }
        
        if (! $ds->isEmpty()) {
            $ds->dataCreate(false);
        }
        
        return $notification;
    }
}