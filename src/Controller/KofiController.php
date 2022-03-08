<?php

declare(strict_types=1);

namespace App\Controller;

use App\CashctrlHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Terminal42\CashctrlApi\Entity\Journal;

/**
 * @Route("/_kofi", methods={"POST"})
 */
class KofiController
{
    private CashctrlHelper $cashctrlHelper;
    private string $kofiToken;

    public function __construct(CashctrlHelper $cashctrlHelper, string $kofiToken)
    {
        $this->cashctrlHelper = $cashctrlHelper;
        $this->kofiToken = $kofiToken;
    }

    public function __invoke(Request $request)
    {
        $data = json_decode($request->request->get('data'), true, 512, JSON_THROW_ON_ERROR);

        if (empty($this->kofiToken) || $data['verification_token'] !== $this->kofiToken) {
            throw new AccessDeniedHttpException('Invalid verification token.');
        }

        if ('EUR' !== $data['currency']) {
            throw new BadRequestHttpException('Only EUR is supported');
        }

        $journal = new Journal(
            (float) $data['amount'],
            $this->cashctrlHelper->getAccountId(3457),
            $this->cashctrlHelper->getAccountId(1090),
            new \DateTime($data['timestamp'])
        );
        $journal->setReference($data['kofi_transaction_id']);
        $journal->setTitle($data['type'].' from Ko-fi.com - '.$data['from_name']);
        $journal->setNotes($data['message']);

        $this->cashctrlHelper->addJournalEntry($journal);

        return new Response();
    }
}
