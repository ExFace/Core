<?php
namespace exface\Core\Communication\Messages;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class AnnouncementMessage extends NotificationMessage
{
    const FOLDER_ANNOUNCEMENTS = 'ANNOUNCEMENTS';

    public function getFolder() : ?string
    {
        return parent::getFolder() ?? self::FOLDER_ANNOUNCEMENTS;
    }
}