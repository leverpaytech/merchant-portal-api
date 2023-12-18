<?php

namespace App\Services;

/**
 * Class ZeptomailService.
 */
class ZeptomailService
{
    public static function sendMailZeptoMail($subject ,$message, $email)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.zeptomail.com/v1.1/email",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => '{
                "from": { "address": "noreply@leverpay.io"},
                "to": [{"email_address": {"address": '.$email.', "name": "LeverPay"}}],
                "subject":'.$subject.',
                "htmlbody":"'.$message.'",
            }',
            CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey wSsVR61x+ETwXfovymKucr8/mglcUl/yQU16jVCn6ySvGKuR9Mc/kkCYUFLyFPgdFGBuRjUVpLJ8nEtV0TcIj9t7yVEGCyiF9mqRe1U4J3x17qnvhDzOXWRdkROLJY8Kxg5skmVoEc4k+g==",
                "cache-control: no-cache",
                "content-type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        } else {
            return $response;
        }
    }

    public static function payInvoice($subject ,$message, $email)
    {
        $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.zeptomail.com/v1.1/email/template",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => '{
                "mail_template_key": "2d6f.117fe6ec4fda4841.k1.d46e9be0-9b7d-11ee-a277-5254004d4100.18c6ee4949e",
                "from": { "address": "noreply@leverpay.io", "name": "noreply"},
                "to": [{"email_address": {"address": '.$email.', "name": "LeverPay"}}],
                "merge_info": {"product name":"product name_value","amount":"amount_value","total price":"total price_value","vat":"vat_value","due_date":"due_date_value","description":"description_value","transaction_fee":"transaction_fee_value","view_invoice_link":"view_invoice_link_value","customer_name":"customer_name_value","setreminder_link":"setreminder_link_value"}}',
                CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey wSsVR61x+ETwXfovymKucr8/mglcUl/yQU16jVCn6ySvGKuR9Mc/kkCYUFLyFPgdFGBuRjUVpLJ8nEtV0TcIj9t7yVEGCyiF9mqRe1U4J3x17qnvhDzOXWRdkROLJY8Kxg5skmVoEc4k+g==",
                "cache-control: no-cache",
                "content-type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        } else {
            return $response;
        }
    }
}
