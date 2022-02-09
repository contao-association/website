<?php

namespace Deployer;

use Deployer\Exception\RuntimeException;

$recipes = [
    'common',
    'symfony4',
    'deployer/recipes/recipe/rsync',
    'terminal42/deployer-recipes/recipe/contao',
    'terminal42/deployer-recipes/recipe/deploy',
    'terminal42/deployer-recipes/recipe/encore',
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
    'contao-manager',
    'files',
    'var/backups',
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


// Enable maintenance mode
task('maintenance:enable', function () {
    run('{{bin/php}} {{bin/console}} contao:maintenance-mode enable {{console_options}}');
})->desc('Enable maintenance mode');

// Disable maintenance mode
task('maintenance:disable', function () {
    run('{{bin/php}} {{bin/console}} contao:maintenance-mode disable {{console_options}}');
})->desc('Disable maintenance mode');

task('contao:lock_manager', function () {
    run('echo \'3\' > {{release_path}}/contao-manager/login.lock');
})->desc('Lock the Contao Manager');


// Main task
task('deploy', [
    // Prepare
    'contao:validate',
    'encore:compile',

    // Deploy
    'deploy:info',
    'deploy:prepare',
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
    'contao:lock_manager',
    'deploy:symlink',
    'deploy:opcache',
    'contao:migrate',
    'maintenance:disable',

    // Cleanup
    'cleanup',
    'success',
])->desc('Deploy your project');
