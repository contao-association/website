<?php

namespace Deployer;

require_once 'recipe/contao.php';

host('test', 'prod')
    ->setHostname('s001.cyon.net')
    ->setRemoteUser('contaoro')
    ->set('bin/php', 'php81')
;

host('test')
    ->setDeployPath('/home/contaoro/public_html/test.members.contao.org')
    ->set('htaccess_filename', '.htaccess.test')
;

host('prod')
    ->setDeployPath('/home/contaoro/public_html/members.contao.org')
    ->set('htaccess_filename', '.htaccess.prod')
;

set('keep_releases', 10);
set('allow_anonymous_stats', false);
add('shared_dirs', ['var/backups', 'var/invoices']);

// Task: upload files
task('deploy:upload', static function () {
    $paths = [
        'config',
        'contao',
        'src',
        'templates',
        'translations',
        'web/.well-known',
        'web/layout',
        '.env',
        'composer.json',
        'composer.lock',
    ];

    if ($htaccess = currentHost()->get('htaccess_filename')) {
        $paths[] = 'web/'.$htaccess;
    }

    foreach ($paths as $path) {
        upload($path, '{{release_path}}/', [
            'options' => ['--recursive', '--relative'],
            'progress_bar' => false,
        ]);
    }
});


task('deploy:opcache', function () {
    try {
        run('pkill lsphp');
    } catch (\RuntimeException $e) {
        info(' … skipped');
    }
})->desc('Clear OpCode cache');

// Task: Composer self update
task('deploy:composer-self-update', static function () {
    run('{{bin/composer}} self-update');
});

// Task: deploy the .htaccess file
task('deploy:htaccess', static function () {
    $file = currentHost()->get('htaccess_filename');

    if (!$file) {
        info(' … skipped');
        return;
    }

    run('cd {{release_path}}/web && if [ -f "./.htaccess" ]; then rm -f ./.htaccess; fi');
    run('cd {{release_path}}/web && if [ -f "./'.$file.'" ]; then mv ./'.$file.' ./.htaccess; fi');
});

// Task: build assets
task('deploy:build-assets', static function () {
    runLocally('yarn build');
});

// Task: override deploy:prepare
task('deploy:prepare', [
    'deploy:build-assets',
    'deploy:info',
    'deploy:setup',
    'deploy:release',
    'deploy:shared',
]);

// Task: override deploy
task('deploy', [
    'deploy:prepare',
    'deploy:upload',
    'deploy:composer-self-update',
    'deploy:vendors',
    'deploy:htaccess',
    'contao:manager:download',
    'contao:install:lock',
    'contao:manager:lock',
    'contao:maintenance:enable',
    'deploy:symlink',
    'deploy:opcache',
    'contao:migrate',
    'contao:maintenance:disable',
    'deploy:cleanup',
    'deploy:success',
]);
