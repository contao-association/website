<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @Route("/supporter.json")
 * @ServiceTag("controller.service_arguments")
 */
class SupporterController
{
    private Connection $connection;
    private array $memberships;

    public function __construct(Connection $connection, array $memberships)
    {
        $this->connection = $connection;
        $this->memberships = $memberships;
    }

    public function __invoke()
    {
        $json = [];
        $members = $this->connection->executeQuery("
            SELECT *
            FROM tl_member
            WHERE disable=''
              AND (start='' OR start<UNIX_TIMESTAMP())
              AND (stop='' OR stop>UNIX_TIMESTAMP())
              AND (membership_start='' OR membership_start<UNIX_TIMESTAMP())
              AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ");

        foreach ($members->iterateAssociative() as $member) {
            $listing = $this->memberships[$member['membership']]['listing'] ?? [];

            if (!$member['listing'] || empty($listing['name'])) {
                continue;
            }

            $data = [
                'level' => $listing['name'],
                'name' => $member['listing_name'] ?: $member['company'] ?: ("{$member['firstname']} {$member['lastname']}"),
            ];

            if ($listing['link'] ?? false) {
                $data['link'] = $member['listing_link'] ?: $member['website'];
            }

            if ($listing['logo'] ?? false) {
                $data['logo'] = '...';
            }

            if ($listing['cloud'] ?? false) {
                $data['cloud'] = [$data['name'], '', $data['link']];
            }

            if (isset($listing['video'])) {
                $data['video'] = $listing['video'];
            }

            $json[] = $data;
        }

        usort($json, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return new JsonResponse($json);
    }
}
