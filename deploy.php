<?php

require_once 'vendor/terminal42/contao-build-tools/src/Deployer.php';

use Terminal42\ContaoBuildTools\Deployer;

(new Deployer('s001.cyon.net', 'contaoro', 'php81'))
    ->addTarget('test', '/home/contaoro/public_html/test.members.contao.org', 'pkill lsphp')
    ->addTarget('prod', '/home/contaoro/public_html/members.contao.org', 'pkill lsphp')
    ->addUploadPaths('web/.well-known')
    ->addSharedDirs('var/invoices')
    ->buildAssets()
    ->run()
;
