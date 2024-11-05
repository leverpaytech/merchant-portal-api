<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;

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
                "from": { "address": "development@leverpay.io"},
                "to": [{"email_address": {"address": '.$email.', "name": "LeverPay"}}],
                "subject":'.$subject.',
                "htmlbody":"'.preg_replace('/\n/', '', $message).'",
            }',
            CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey ".env('ZEPTOMAIL_TOKEN'),
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

    public static function sendTemplateZeptoMail($templateId,$body,$email)
    {
        $curl = curl_init();
        $info = json_encode($body);
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
                "merge_info":'.$info.',
                "from": { "address": "development@leverpay.io"},
                "to": [{"email_address": {"address": '.$email.', "name": "LeverPay"}}],
                "template_key":'.$templateId.',
            }',
            CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey ".env('ZEPTOMAIL_TOKEN'),
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

    public static function payInvoiceMail($subject ,$message, $email)
    {
        //return $message;
        $product_name=$message['product_name'];
        $price=$message['price'];
        $description=$message['product_description'];
        $total=$message['total'];
        $vat=$message['vat'];
        $customer=$message['customer'];
        $url=$message['url'];
        $fee=$message['fee'];
        $merchant=$message['merchant'];

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
                "from": { "address": "development@leverpay.io", "name": "noreply"},
                "to": [{"email_address": {"address": '.$email.', "name": "LeverPay"}}],
                "merge_info": {"product name":"'.$product_name.'","amount":"'.$price.'","total price":"'.$total.'","vat":"'.$vat.'","due_date":"due_date_value","description":"'.$description.'","transaction_fee":"'.$fee.'","view_invoice_link":"'.$url.'", "Merchant-Name": "'.$merchant.'","customer_name":"'.$customer.'","setreminder_link":"setreminder_link_value"}}',
                CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey ".env('ZEPTOMAIL_TOKEN'),
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
