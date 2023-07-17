<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Input;

#[AsCallback(table: 'tl_member', target: 'config.onload')]
#[AsCallback(table: 'tl_member_group', target: 'config.onload')]
class PreventMultiEditListener
{
    public function __invoke(): void
    {
        if ('select' === Input::get('act')) {
            throw new AccessDeniedException('Edit multiple is disabled');
        }
    }
}
