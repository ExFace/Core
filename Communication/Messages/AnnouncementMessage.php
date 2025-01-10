<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Communication\Recipients\UserMultiRoleRecipient;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
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

    /**
     * 
     * @param string $uid
     * @return \exface\Core\Communication\Messages\AnnouncementMessage
     */
    public function setAnnouncementUid(string $uid) : AnnouncementMessage
    {
        $this->setReference($uid);
        return $this;
    }

    /**
     * Type of the message: error, warning, info, success, hint.
     * 
     * @uxon-property type
     * @uxon-type [error,warning,info,success,hint,question]
     * 
     * @param string $type
     * @return \exface\Core\Communication\Messages\AnnouncementMessage
     */
    public function setMessageType(string $type) : AnnouncementMessage
    {
        $this->messageType = $type;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getMessageType() : ?string
    {
        return $this->messageType;
    }

    /**
     * 
     * @param string $from
     * @param string $to
     * @return \exface\Core\Communication\Messages\AnnouncementMessage
     */
    public function setShowBetween(string $from, string $to = null) : AnnouncementMessage
    {
        $this->showFrom = $from;
        $this->showTo = $to;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getShowFrom() : ?string
    {
        return $this->showFrom;
    }

    /**
     * 
     * @return string
     */
    public function getShowTo() : ?string
    {
        return $this->showTo;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\UserInterface $user
     * @param mixed $dateTime
     * @return bool
     */
    public function isVisible(UserInterface $user, $dateTime = null) : bool
    {
        $errors = [];
        $dateTime = $dateTime ?? DateTimeDataType::now();
        $visibleForUser = $this->checkVisibilityUser($user, $this->getRecipients(), $errors, $this->getRecipientsToExclude());
        $visibleOnDate = $this->getShowFrom() <= $dateTime && ($this->getShowTo() === null || $this->getShowTo() >= $dateTime);
        return $visibleForUser && $visibleOnDate;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\UserInterface $user
     * @param \exface\Core\Interfaces\Communication\RecipientInterface[] $recipients
     * @param array $errors
     * @param \exface\Core\Interfaces\Communication\RecipientInterface[] $excludeRecipients
     * @return bool
     */
    protected function checkVisibilityUser(UserInterface $user, array $recipients, array &$errors, array $excludeRecipients = []) : bool
    {
        foreach ($recipients as $recipient) {
            foreach ($excludeRecipients as $excl) {
                if ($excl->is($recipient)) {
                    return false;
                }
            }
            switch (true) {
                // If at least one recipient is the role exface.Core.AUTHENTICATED - return TRUE if the
                // User is authenticated. If not authenticated, continue with other recipients.
                case $recipient instanceof UserRoleRecipient && $recipient->isGlobalRoleAuthenticated():
                    if (! $user->isAnonymous()) {
                        return true;
                    }
                    break;
                // Similarly if exface.Core.ANONYMOUS is the recipient role, check if the user is
                // anonymous. If not, continue;
                case $recipient instanceof UserRoleRecipient && $recipient->isGlobalRoleAnonymous():
                    if ($user->isAnonymous()) {
                        return true;
                    }
                    break;
                // If it is any other role, check if the user has it
                case $recipient instanceof UserRoleRecipient:
                    if($user->hasRole($recipient->getRoleSelector())) {
                        return true;
                    }
                    break;
                // If it is a rolce combination like <role1>+<role2>, return true if the user has all
                // of them. Otherwise - continue;
                case $recipient instanceof UserMultiRoleRecipient:
                    if($user->hasRolesAll($recipient->getRoleSelectors())) {
                        return true;
                    }
                    break;
                // Break down other types or recipient groups and check their contents recurisvely
                case $recipient instanceof RecipientGroupInterface:
                    if (true === $this->checkVisibilityUser($user, $recipient->getRecipients(), $errors, $excludeRecipients)) {
                        return true;
                    }
                    break;
                // For a single user simply check if it is our user
                case $recipient instanceof UserRecipientInterface:
                    if (true === $recipient->getUser()->is($user)) {
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