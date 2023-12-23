<?php

namespace App\Services;
use GuzzleHttp\Client;

/**
 * Class EtherscanService.
 */
class EtherscanService
{
    public static function getTransactionDetails($transactionHash)
    {
        $client = new Client([
            'base_uri' => 'https://api.etherscan.io/',
        ]);
        
        //0xbc78ab8a9e9a0bca7d0321a27b2c03addeae08ba81ea98b03cd3dd237eabed44
        $response = $client->get('api', [
            'query' => [
                'module' => 'proxy',
                'action' => 'eth_getTransactionByHash', 
                'txhash' => $transactionHash,
                'apikey' => 'MDQ9EJSVTS5PSGU642ACWXUXSKFGVKITPA',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        //return response()->json($data);

        if (isset($data['result']) && !empty($data['result'])) 
        {
            $transaction = $data['result'];

            $sender = $transaction['from'];
            $recipient = $transaction['to'];
            $amountInWei = hexdec($transaction['value']);
            //$timestamp = $transaction['timeStamp'];

            // Convert Wei to Ether
            $valueInEther = $amountInWei / 1e18;

            return response()->json([
                'amount'=>$valueInEther,
                'sender'=>$sender,
                'reciever'=>$recipient,
                'value'=>$transaction['value'],
                
            ],200);

            /*** 
                to extract more details about transaction decode $transaction['input'] as below
            ****/ 
            $inputData = $transaction['input'];
            // Remove '0x' prefix if present
            $inputData = str_replace('0x', '', $inputData);

            // Assuming ABI JSON for the contract is available
            $abi = '[{"constant":true,"inputs":[{"name":"param1","type":"uint256"}],"name":"exampleFunction","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"}]';

            // Decode the input data using the ABI
            $decodedInput = self::decodeInputData($abi, $inputData);

            return response()->json([
                "result"=> $decodedInput
            ]);
    
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ]);
        }
    }

    // Function to decode parameters using ABI
    public static function decodeInputData($abi, $inputData)
    {
        $parameters = json_decode($abi, true)[0]['inputs'];

        $decoded = [];
        $offset = 0;

        foreach ($parameters as $param) {
            $type = $param['type'];
            $length = hexdec(substr($inputData, $offset, 64)); // Assuming 64 characters per parameter
            $offset += 64;

            $value = substr($inputData, $offset, $length * 2);
            $offset += $length * 2;

            // Convert the value based on the parameter type
            $decoded[] = self::convertValue($value, $type);
        }

        return $decoded;
    }

    public static function convertValue($hexValue, $type)
    {
        // Implement conversion logic based on the parameter type
        // This example only handles uint256
        if ($type === 'uint256') {
            return hexdec($hexValue);
        }

        // Add more cases as needed for different types

        return $hexValue;
    }

    
    
    
}
