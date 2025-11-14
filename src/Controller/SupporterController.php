<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class SupporterController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly array $memberships,
    ) {
    }

    #[Route(path: '/supporter.json')]
    public function supporterAction(Request $request): JsonResponse
    {
        $json = [];

        foreach ($this->getActiveMembers() as $member) {
            $listing = $this->memberships[$member['membership']]['listing'] ?? [];

            if (!$member['listing'] || empty($listing['name'])) {
                continue;
            }

            $data = [
                'level' => $listing['name'],
                'name' => $this->getName($member),
            ];

            if ($listing['link'] ?? false) {
                $data['link'] = $this->getLink($member);
            }

            if ($listing['logo'] ?? false) {
                $data['logo'] = $member['listing_logo'] ? ($request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.$member['listing_logo']) : '';
            }

            if (isset($listing['video'])) {
                $data['video'] = $listing['video'];
            }

            $json[] = $data;
        }

        usort($json, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return new JsonResponse($json);
    }

    #[Route(path: '/cloud-supporter.json')]
    public function cloudAction(): JsonResponse
    {
        $json = [];

        foreach ($this->getActiveMembers() as $member) {
            $listing = $this->memberships[$member['membership']]['listing'] ?? [];

            if (!$member['listing'] || empty($listing['name']) || !($listing['cloud'] ?? false)) {
                continue;
            }

            $json[] = [
                'name' => $this->getName($member),
                'link' => $this->getLink($member),
            ];
        }

        usort($json, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return new JsonResponse($json);
    }

    private function getActiveMembers(): \Traversable
    {
        return $this->connection->executeQuery("
            SELECT *
            FROM tl_member
            WHERE disable=0
              AND membership!='inactive'
              AND (start='' OR start<UNIX_TIMESTAMP())
              AND (stop='' OR stop>UNIX_TIMESTAMP())
              AND (membership_start='' OR membership_start<UNIX_TIMESTAMP())
              AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ")->iterateAssociative();
    }

    private function getName(array $member): string
    {
        return $member['listing_name'] ?: $member['company'] ?: ("{$member['firstname']} {$member['lastname']}");
    }

    private function getLink(array $member): string
    {
        return $member['listing_link'] ?: $member['website'];
    }
}
