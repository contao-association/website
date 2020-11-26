<?php

declare(strict_types=1);

namespace App\EventListener\MemberLog;

use Contao\Backend;
use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;

class MemberLogPermissionListener
{
    private Connection $connection;
    private Security $security;

    public function __construct(Connection $connection, Security $security)
    {
        $this->connection = $connection;
        $this->security = $security;
    }

    /**
     * @Callback(table="tl_member_log", target="config.onload")
     */
    public function checkPermissions(DataContainer $dc): void
    {
        switch (Input::get('act')) {
            case '':
            case 'create':
                break;

            /** @noinspection PhpMissingBreakStatementInspection */
            case 'edit':
                $type = $this->connection->fetchOne('SELECT type FROM tl_member_log WHERE id=?', [$dc->id]);

                if ('' === $type || 'note' === $type) {
                    return;
                }
                // no break

            default:
                throw new AccessDeniedException('Attempt to change a locked log entry');
        }
    }

    /**
     * @Callback(table="tl_member_log", target="list.operations.edit.button")
     */
    public function editButton(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        if ('note' !== $row['type']) {
            return '';
        }

        return '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_member_log", target="config.onsubmit")
     */
    public function updateNote(DataContainer $dc)
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        $this->connection->update(
            'tl_member_log',
            [
                'type' => 'note',
                'dateAdded' => $dc->activeRecord->dateAdded ?: time(),
                'user' => $user->id,
            ],
            ['id' => $dc->id]
        );
    }
}
