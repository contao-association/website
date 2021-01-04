<?php

namespace App\EventListener;

use Contao\User;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * @Hook("checkCredentials")
 */
class LegacyCredentialsListener
{
    private EncoderFactoryInterface $encoderFactory;

    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    public function __invoke($username, $password, User $user): bool
    {
        if (false === strpos($user->password, ':')) {
            return false;
        }

        [$legacyPasswordHash, $legacyPasswordSalt] = explode(':', $user->password);

        // Check if password matches
        /** @noinspection HashTimingAttacksInspection */
        if (empty($legacyPasswordSalt) || $legacyPasswordHash !== sha1($legacyPasswordSalt . $password)) {
            return false;
        }

        // Update password hash in database
        $user->password = $this->encoderFactory->getEncoder(User::class)->encodePassword($password, null);
        $user->save();

        return true;
    }
}
