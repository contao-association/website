<?php

use Contao\Crawl;

/*
 * Disable maintenance and settings
 */
unset(
    $GLOBALS['BE_MOD']['design']['tpl_editor'],
    $GLOBALS['BE_MOD']['system']['settings'],
);
$GLOBALS['TL_MAINTENANCE'] = [Crawl::class];

/*
 * Backend modules
 */
$GLOBALS['BE_MOD']['accounts']['member']['tables'][] = 'tl_member_log';
