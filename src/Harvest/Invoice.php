<?php

declare(strict_types=1);

namespace App\Harvest;

use Contao\MemberModel;
use Required\Harvest\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class Invoice
{
    private const PAYMENT_TERMS = '+30 days';
    private const ITEM_CATEGORY = 'Abo/Jahr';

    private Client $api;
    private TranslatorInterface $translator;
    private Filesystem $filesystem;
    private array $memberships;
    private string $projectDir;
    private string $harvestSubdomain;

    public function __construct(Client $api, TranslatorInterface $translator, Filesystem $filesystem, array $memberships, string $projectDir, string $harvestSubdomain)
    {
        $this->api = $api;
        $this->translator = $translator;
        $this->filesystem = $filesystem;
        $this->memberships = $memberships;
        $this->projectDir = $projectDir;
        $this->harvestSubdomain = $harvestSubdomain;
    }

    public function createMembershipInvoice(MemberModel $member): array
    {
        $membership = $this->memberships[$member->membership];

        $data = [
            'client_id' => $member->harvest_client_id,
            'number' => $member->id . '/' . date('Y'),
            'purchase_order' => $member->id,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime(self::PAYMENT_TERMS)),
            'payment_term' => 'custom',
            'notes' => $this->translator->trans('invoice_notes'),
            'currency' => 'EUR',
            'line_items' => [[
                'kind' => self::ITEM_CATEGORY,
                'description' => $this->translator->trans('invoice_description', ['{membership}' => $this->translator->trans('membership.'.$member->membership)]),
                'unit_price' => number_format($membership['custom'] ? $member->membership_amount : $membership['price'], 2, '.', ''),
            ]]
        ];

        return $this->api->invoices()->create($data);
    }

    public function getClientUrl(string $clientKey)
    {
        return 'https://'.$this->harvestSubdomain.'.harvestapp.com/client/invoices/'.$clientKey;
    }

    public function downloadPdf(int $invoiceId): string
    {
        $invoice = $this->api->invoices()->show($invoiceId);

        $date = new \DateTime($invoice['issue_date']);
        $year = $date->format('Y');
        $quarter = ceil($date->format('n') / 4);

        $targetFile = 'var/invoices/'.$year.'/Q'.$quarter.'/member-'.$invoice['purchase_order'].'.pdf';
        $this->filesystem->dumpFile(
            $this->projectDir.'/'.$targetFile,
            file_get_contents($this->getClientUrl($invoice['client_key']).'.pdf')
        );

        return $targetFile;
    }
}
