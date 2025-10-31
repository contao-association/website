<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\FrontendUser;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCallback('tl_member', 'fields.listing_logo.save')]
#[Autoconfigure(bind: ['$projectDir' => '%kernel.project_dir%'])]
class MemberLogoListener
{
    final public const string UPLOAD_DIR = 'files/sponsors';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(mixed $value, DataContainer|FrontendUser $dc): mixed
    {
        $currentFile = $dc instanceof FrontendUser ? $dc->listing_logo : ($dc->getCurrentRecord()['listing_logo'] ?? null);

        if (empty($value)) {
            if ($currentFile && $this->filesystem->exists(Path::join($this->projectDir, $currentFile))) {
                $this->filesystem->remove(Path::join($this->projectDir, $currentFile));
            }

            return '';
        }

        $currentFile = $value;
        $targetFile = Path::join(self::UPLOAD_DIR, $dc->id.'.'.pathinfo((string) $value, PATHINFO_EXTENSION));

        if ($currentFile !== $targetFile) {
            $this->filesystem->rename(Path::join($this->projectDir, $currentFile), Path::join($this->projectDir, $targetFile), true);
        }

        return $targetFile;
    }
}
