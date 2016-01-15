<?php

$GLOBALS['SENTRY_CLIENT'] = new Raven_Client($GLOBALS['TL_CONFIG']['sentry_url']);
$GLOBALS['SENTRY_HANDLER'] = new Raven_ErrorHandler($GLOBALS['SENTRY_CLIENT']);
$GLOBALS['SENTRY_HANDLER']->registerExceptionHandler();
$GLOBALS['SENTRY_HANDLER']->registerErrorHandler(true, E_ALL & ~E_NOTICE);
$GLOBALS['SENTRY_HANDLER']->registerShutdownFunction();
