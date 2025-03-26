<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\CashctrlHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Terminal42\CashctrlApi\Entity\Journal;

#[Route(path: '/_webhooks/kofi', methods: ['POST'])]
class KofiController
{
    public function __construct(
        private readonly CashctrlHelper $cashctrlHelper,
        private readonly string $kofiToken,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $data = json_decode((string) $request->request->get('data'), true, 512, JSON_THROW_ON_ERROR);

        if (!$this->kofiToken || $data['verification_token'] !== $this->kofiToken) {
            throw new AccessDeniedHttpException('Invalid verification token.');
        }

        if ('EUR' !== strtoupper((string) $data['currency'])) {
            throw new BadRequestHttpException(\sprintf('Currency "%s" is not supported', $data['currency']));
        }

        $journal = new Journal(
            (float) $data['amount'],
            $this->cashctrlHelper->getAccountId(3457),
            $this->cashctrlHelper->getAccountId(1090),
            new \DateTime($data['timestamp']),
        );
        $journal->setReference($data['kofi_transaction_id']);
        $journal->setTitle($data['type'].' from Ko-fi.com - '.$data['from_name']);
        $journal->setNotes($data['message']);

        $this->cashctrlHelper->addJournalEntry($journal);

        return new Response();
    }
}
