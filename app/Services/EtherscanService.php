<?php

namespace App\Services;
use GuzzleHttp\Client;

/**
 * Class EtherscanService.
 */
class EtherscanService
{
    public static function getTransactionDetails($transactionHash,$apiUrl,$apiKey)
    {
        $client = new Client();

        $response = $client->get($apiUrl, [
            'query' => [
                'module' => 'proxy',
                'action' => 'eth_getTransactionByHash',
                'txhash' => $transactionHash,
                'apikey' => $apiKey,
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        if ($data['status'] == '1') {
            $transaction = $data['result'];

            $sender = $transaction['from'];
            $recipient = $transaction['to'];
            $amountInWei = hexdec($transaction['value']);
            $timestamp = $transaction['timeStamp'];

            // Format the date
            $date = date('Y-m-d H:i:s', $timestamp);

            // Check if the transaction involves token transfer
            if (!empty($transaction['input'])) {
                // Decode input data to get token transfer details
                $inputData = $transaction['input'];
                $decoder = new Decoder(json_decode($usdtAbi, true));
                $decodedInput = $decoder->decodeData($inputData);

                if ($decodedInput['method'] === 'transfer' && $decodedInput['to'] === '0x19f256453d3245c7ec5213433c89a601625d53f3') {
                    // Extract USDT transfer amount
                    $amountInUsdt = hexdec($decodedInput['params'][1]) / 1e6; // Assuming 6 decimal places for USDT

                    return response()->json([
                        'status' => 'success',
                        'sender' => $sender,
                        'recipient' => $recipient,
                        'amount_in_eth' => $amountInWei / 1e18,
                        'amount_in_usdt' => $amountInUsdt,
                        'date' => $date,
                    ]);
                }
            }

            // If not a token transfer, return amount in Ether
            return response()->json([
                'status' => 'success',
                'sender' => $sender,
                'recipient' => $recipient,
                'amount_in_eth' => $amountInWei / 1e18,
                'date' => $date,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found or invalid API key.',
            ]);
        }
    }

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

    public static function getUsdtTransactionAmount($address, $apiUrl, $apiKey)
    {
        // Define the Etherscan API endpoint for getting ERC-20 token (USDT) transactions
        $url = $apiUrl . "?module=account&action=tokentx&address={$address}&contractaddress=0xdac17f958d2ee523a2206206994597c13d831ec7&startblock=0&endblock=999999999&sort=asc&apikey=" . $apiKey;

        // Make a request to Etherscan API
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if ($data['status'] == '1') {
            // Assuming the first transaction in the response is the latest one
            $latestTransaction = $data['result'][0];

            // Extract the USDT transaction amount
            $usdtAmount = $latestTransaction['value'] / 1e6; // 1e6 is the number of decimals for USDT

            return $usdtAmount;
        } else {
            return $data['message'];
        }
    }


    
}
