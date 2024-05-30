<?php

declare(strict_types=1);

namespace App\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Config\ConfigInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Terminal42\CashctrlBundle\Terminal42CashctrlBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * Gets a list of autoload configurations for this bundle.
     *
     * @return array<ConfigInterface>
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(Terminal42CashctrlBundle::class),
        ];
    }
}
