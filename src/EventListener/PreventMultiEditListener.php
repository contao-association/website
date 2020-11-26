<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Input;

/**
 * @Callback(table="tl_member", target="config.onload")
 * @Callback(table="tl_member_group", target="config.onload")
 */
class PreventMultiEditListener
{
    public function __invoke(): void
    {
        if ('select' === Input::get('act')) {
            throw new AccessDeniedException('Edit multiple is disabled');
        }
    }
}
