<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    if (($_SERVER['SENTRY_ENV'] ?? '') !== 'prod') {
        $containerConfigurator->extension('framework', [
            'mailer' => [
                'envelope' => [
                    'recipients' => ['association@contao.org']
                ]
            ]
        ]);
    }
};
