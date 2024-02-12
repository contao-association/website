<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    if (($_SERVER['SENTRY_ENV'] ?? '') !== 'prod') {
        $framework->mailer()
            ->envelope()
            ->recipients(['andreas.schempp@terminal42.ch'])
        ;
    }
};
