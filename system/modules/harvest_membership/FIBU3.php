<?php

/**
 * harvest_membership Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-harvest_membership
 */

class FIBU3
{
    private $baseUrl = 'https://www.fibu3.ch/rest/api/v1/';
    private $apiKey;
    private $periodStart;
    private $periodEnd;


    public function __construct($apiKey, \DateTime $periodStart, \DateTime $periodEnd)
    {
        $this->apiKey      = $apiKey;
        $this->periodStart = $periodStart;
        $this->periodEnd   = $periodEnd;
    }


    public function book($text, \DateTime $receiptDate, $amount, $sollAccount, $habenAccount)
    {
        if ($receiptDate < $this->periodStart || $receiptDate > $this->periodEnd) {
            throw new \OutOfRangeException('Invoice date is out of booking period.');
        }

        $data = array(
            'text'               => $text,
            'vatCode'            => '',
            'receiptDate'        => $receiptDate->format('d.m.Y'),
            'receiptNumber'      => $this->getNextReceiptNumber(),
            'amount'             => number_format($amount, 2, '.', ''),
            'sollAccountNumber'  => $sollAccount,
            'habenAccountNumber' => $habenAccount,
            'id'                 => 0
        );

        $this->apiCall('book', json_encode($data));
    }


    private function getNextReceiptNumber()
    {
        $request = $this->apiCall('listAccountingTransactions');

        if ($request->code != 200) {
            throw new \LogicException("FIBU3 API-Call failed\n\nRequest:\n" . $request->request . '\n\nResponse:\n' . $request->response);
        }

        $lastNumber = 0;
        $response   = json_decode($request->response, true);

        foreach ($response['data']['accountingTransaction'] as $transaction) {
            if ($transaction['receiptNumber'] > $lastNumber) {
                $lastNumber = $transaction['receiptNumber'];
            }
        }

        return $lastNumber + 1;
    }


    private function apiCall($method, $body = null)
    {
        $url = $this->baseUrl . $method . '/' . $this->apiKey;

        $request = new Request();
        $request->setHeader('Content-Type', 'application/json');
        $request->setHeader('Accept', 'application/json');

        if (null === $body) {
            $request->send($url);
        } else {
            $request->send($url, $body, 'POST');
        }

        return $request;
    }
}
