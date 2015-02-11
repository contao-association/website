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


    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }


    public function book($text, \DateTime $receiptDate, $amount, $sollAccount, $habenAccount)
    {
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
            throw new \LogicException('FIBU3 API-Call failed (' . $request->url . ')');
        }

        $lastNumber = 0;
        $response   = json_decode($request->response, true);

        foreach ($response['data']['accountingTransaction'] as $transaction) {
            if ($transaction['receiptNumber'] > $lastNumber) {
                $lastNumber = $transaction['receiptNumber'];
            }
        }

        return $lastNumber;
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
