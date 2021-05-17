<?php

namespace Deployer;

use Deployer\Exception\RuntimeException;

$recipes = [
    'common',
    'symfony4',
    'deployer/recipes/recipe/rsync',
    'terminal42/deployer-recipes/recipe/contao',
    'terminal42/deployer-recipes/recipe/database',
    'terminal42/deployer-recipes/recipe/deploy',
    'terminal42/deployer-recipes/recipe/encore',
    'terminal42/deployer-recipes/recipe/maintenance',
];

// Require the recipes
foreach ($recipes as $recipe) {
    if (false === strpos($recipe, '/')) {
        require_once sprintf('recipe/%s.php', $recipe);
        continue;
    }

    require_once sprintf('%s/vendor/%s.php', getcwd(), $recipe);
}

// Load the hosts
inventory('deploy-hosts.yml');

/**
 * ===============================================================
 * Configuration
 *
 * Define the deployment configuration. Each of the variables
 * can be overridden individually per each host.
 * ===============================================================
 */
// Enable SSH multiplexing
set('ssh_multiplexing', true);

// Number of releases to keep
set('keep_releases', 3);

// Disable anonymous stats
set('allow_anonymous_stats', false);

// Rsync
set('rsync_src', __DIR__);
set('rsync', function () {
    return [
        'exclude' => array_unique(get('exclude', [])),
        'exclude-file' => false,
        'include' => [],
        'include-file' => false,
        'filter' => [],
        'filter-file' => false,
        'filter-perdir' => false,
        'flags' => 'rz',
        'options' => ['delete'],
        'timeout' => 300,
    ];
});

set('shared_dirs', [
    'assets/images',
    'files',
    'var/invoices',
    'var/logs',
    'web/share',
]);
add('shared_files', ['.env.local']);

task('deploy:opcache', function () {
    try {
        run('pkill lsphp');
    } catch (RuntimeException $e) {
        writeln("\r\033[1A\033[40C … skipped");
        output()->setWasWritten(false);

        return;
    }
})->desc('Clear OpCode cache');

task('database:backup', static function () {
    if (!has('previous_release')) {
        return;
    }

    try {
        run('cd {{previous_release}} && {{bin/composer}} show backup-manager/symfony');
    } catch (RuntimeException $e) {
        writeln("\r\033[1A\033[32C … skipped");

        output()->setWasWritten(false);

        return;
    }

    run(sprintf('cd {{previous_release}} && {{bin/php}} {{bin/console}} backup-manager:backup contao local -c gzip --filename %s.sql', date('Y-m-d-H-i-s')));
})->desc('Backup database');


// Main task
task('deploy', [
    // Prepare
    'contao:validate',
    'encore:compile',

    // Deploy
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'rsync',
    'deploy:create_initial_dirs',
    'deploy:shared',
    'deploy:vendors',
    'deploy:entry_points',

    // Release
    'maintenance:enable',
    'contao:download_manager',
    'contao:lock_install_tool',
    'deploy:symlink',
    'deploy:opcache',
    'database:backup',
    'contao:migrate',
    'maintenance:disable',

    // Cleanup
    'deploy:unlock',
    'cleanup',
    'success',
])->desc('Deploy your project');
