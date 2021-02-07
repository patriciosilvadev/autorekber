<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Chat;

class TripayController extends Controller
{

    public function __construct(Request $request)
    {
        // $apikey  = $_SERVER['apiKey'] ?? '';
        // if($apikey != 'YXV0b3Jla2Jlci5jb20='){
        //     return array('status' => 'Not Autorized');
        //     exit;
        // }
        return $request;
        
    }


    public function GetInstruction(Request $request)
    {
        $req = str_replace(\Request::url(), '', \Request::fullUrl()) ?? '';
        $curl = curl_init();
        $transaction = Transaction::where('id',$request->id)->first();
        if($transaction->bot=='Select Payment'){
            $payment = Payment::where('id',$transaction->bot_cmd)->first();
        }
        if(!$payment){
            exit;
        }
        curl_setopt_array($curl, array(
        CURLOPT_FRESH_CONNECT     => true,
        CURLOPT_URL               => "https://payment.tripay.co.id/".ENV('tripay_mode','api')."/payment/instruction?code=".$payment->code."&pay_code=0000000001"."&amount=".($transaction->harga_produk+$transaction->fee)."&allow_html=0",
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_HEADER            => false,
        CURLOPT_HTTPHEADER        => array(
            "Authorization: Bearer ".ENV('tripay_apikey','')
        ),
        CURLOPT_FAILONERROR       => false
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        // echo !empty($err) ? $err : $response;
        $hasil = '<br>';
        $resp = json_decode($response);
        if(empty($err) && $resp->success==1){
            foreach ($resp->data as $row) {
                $hasil .= "*".$row->title."*\r\n<br><br>"??'';
                for ($i=0; $i < count($row->steps)-1; $i++) { 
                    $hasil .="* ".$row->steps[$i]."\r\n<br>"??'';;
                }
                $hasil .= "<br>";
            }
        }
        // return $hasil??'';
        return array('status' => true, 'group_id'=> $transaction->group_id,'response'=>$hasil);
        
    }

    public function GetChannel(Request $request)
    {
        $req = str_replace(\Request::url(), '', \Request::fullUrl());
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_FRESH_CONNECT     => true,
        CURLOPT_URL               => "https://payment.tripay.co.id/".ENV('tripay_mode','api')."/merchant/payment-channel".$req,
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_HEADER            => false,
        CURLOPT_HTTPHEADER        => array(
            "Authorization: Bearer ".ENV('tripay_apikey','')
        ),
        CURLOPT_FAILONERROR       => false
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        // echo !empty($err) ? $err : $response;
        $hasil = '';
        $resp = json_decode($response);
        $i = 1;
        if(empty($err) && $resp->success==1){
            foreach ($resp->data as $row) {
                // $hasil .= "*".$i++.' '.trim(str_replace(['Virtual Account','(Open Payment)'],'',$row->name))."*\r\n<br>"??'';
                $Payment = Payment::where('code',$row->code)->first();
                if(!$Payment){
                    $Payment = new Payment();
                }
                $Payment->group = $row->group;
                $Payment->code = $row->code;
                $Payment->name = $row->name;
                $Payment->type = $row->type;
                $Payment->type = $row->type;
                $Payment->charged_to = $row->charged_to;
                $Payment->fee_flat = $row->fee->flat;
                $Payment->fee_percent = $row->fee->percent;
                $Payment->save();
            }
        }
        $Payment = Payment::all();
        $transaction = Transaction::where('id',$request->id)->whereNotNull('buyer_whatsapp')->first();

        
        if($transaction){
            $hasil .= "@".preg_replace('/^0/i', '62', $transaction->buyer_whatsapp)."\r\n".
                        "Berikut adalah rincian pembayaran kakak : \r\n\r\n".
                        "Harga Produk : Rp. ".number_format($transaction->harga_produk)."\r\n".
                        "Biaya Rekber : Rp. ".number_format($transaction->fee)."\r\n".
                        "Total : Rp. ".number_format($transaction->harga_produk + $transaction->fee)."\r\n\r\n".
                        "Metode Pembayaran : \r\n";
            foreach ($Payment as $row) {
                $hasil .= "*".$row->id.'. '.trim(str_replace(['Virtual Account','(Open Payment)'],'',$row->name))."*\r\n"??'';
            }
            $hasil .= "\r\nSilahkan pilih metode pembayaran yang kakak mau dengan mengetik nomornya.\r\n\r\n".
                        "Contoh : \r\nKetik *1* untuk memilih pembayaran melalui bank *Maybank*";
        }
        $Chat = new Chat();
        $Chat->chatid = $transaction->group_id;
        $Chat->message = $hasil;
        $Chat->type = 'out';
        $Chat->status = 0;
        $Chat->save();
        return array('status' => true, 'group_id'=> $transaction->group_id,'response'=>$hasil);
        
    }

    public function ReqTrx(Request $request)
    {
        $apiKey = ENV('tripay_apikey','');
        $privateKey = ENV('tripay_apikey','');
        $merchantCode = ENV('tripay_apikey','');
        $merchantRef = $request->reff ?? '0000'.time();
        $method = $request->method ?? 'BCAVA';

        $data = [
        'method'            => $method,
        'merchant_ref'      => $merchantRef,
        'customer_name'     => $request->nama ?? 'Customer',
        'signature'         => hash_hmac('sha256', $merchantCode.$method.$merchantRef, $privateKey)
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_FRESH_CONNECT     => true,
        CURLOPT_URL               => "https://payment.tripay.co.id/".ENV('tripay_mode','api')."/open-payment/create",
        CURLOPT_RETURNTRANSFER    => true,
        CURLOPT_HEADER            => false,
        CURLOPT_HTTPHEADER        => array(
            "Authorization: Bearer ".$apiKey
        ),
        CURLOPT_FAILONERROR       => false,
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => http_build_query($data)
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        echo !empty($err) ? $err : $response;
    }

    public function TypePayment(Request $request)
    {
        
    }
}
