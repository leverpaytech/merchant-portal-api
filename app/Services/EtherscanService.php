<?php

namespace App\Services;

/**
 * Class EtherscanService.
 */
class EtherscanService
{
    // protected $apiUrl = 'https://api.etherscan.io/api';
    // protected $apiKey; // Set your API key here

    // public function __construct()
    // {
    //     $this->apiKey = config('services.etherscan.api_key');
    // }

    public static function getBalance($address,$apiUrl,$apiKey)
    {
        $url = $apiUrl."?module=account&action=balance&address={$address}&tag=latest&apikey=".$apiKey;

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if ($data['status'] == '1') {
            $balanceWei = $data['result'];
            $balanceEth =bcdiv($balanceWei, bcpow("10", "18"), 18);
            //$balanceEth = $this->weiToEth($balanceWei);

            return $balanceEth;
        } else {
            return $data['message'];
        }
    }

    
}
