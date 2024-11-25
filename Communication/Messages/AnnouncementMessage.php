<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\UserInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class AnnouncementMessage extends NotificationMessage
{
    const FOLDER_ANNOUNCEMENTS = 'ANNOUNCEMENTS';

    private $announcementUid = null;

    private $messageType = null;

    private $showFrom = null;

    private $showTo = null;

    public function getFolder() : ?string
    {
        return parent::getFolder() ?? self::FOLDER_ANNOUNCEMENTS;
    }

    public function setAnnouncementUid(string $uid) : AnnouncementMessage
    {
        $this->setReference($uid);
        return $this;
    }

    public function setMessageType(string $type) : AnnouncementMessage
    {
        $this->messageType = $type;
        return $this;
    }

    public function getMessageType() : ?string
    {
        return $this->messageType;
    }

    public function setShowBetween(string $from, string $to = null) : AnnouncementMessage
    {
        $this->showFrom = $from;
        $this->showTo = $to;
        return $this;
    }

    public function getShowFrom() : ?string
    {
        return $this->showFrom;
    }

    public function getShowTo() : ?string
    {
        return $this->showTo;
    }

    public function isVisible(UserInterface $user, $dateTime = null) : bool
    {
        $errors = [];
        $dateTime = $dateTime ?? DateTimeDataType::now();
        $visibleForUser = $this->checkVisibilityUser($user, $this->getRecipients(), $errors, $this->getRecipientsToExclude());
        $visibleOnDate = $this->getShowFrom() <= $dateTime && ($this->getShowTo() === null || $this->getShowTo() <= $dateTime);
        return $visibleForUser && $visibleOnDate;
    }

    protected function checkVisibilityUser(UserInterface $user, array $recipients, array &$errors, array $excludeRecipients = []) : bool
    {
        foreach ($recipients as $recipient) {
            foreach ($excludeRecipients as $excl) {
                if ($excl->is($recipient)) {
                    return false;
                }
            }
            switch (true) {
                case $recipient instanceof UserRoleRecipient && $recipient->isGlobalRoleAuthenticated():
                    return ! $user->isAnonymous();
                case $recipient instanceof UserRoleRecipient && $recipient->isGlobalRoleAnonymous():
                    return $user->isAnonymous();
                case $recipient instanceof RecipientGroupInterface:
                    if (true === $this->checkVisibilityUser($user, $recipient->getRecipients(), $errors, $excludeRecipients)) {
                        return true;
                    }
                    break;
                case $recipient instanceof UserRecipientInterface:
                    if (true === $recipient->is($user)) {
                        return true;
                    }
                    break;
                default:
                    // TODO
            }
        }
        
        return false;
    }
}