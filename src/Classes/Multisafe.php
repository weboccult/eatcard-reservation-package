<?php

namespace Weboccult\EatcardReservation\Classes;

use GuzzleHttp\Client;

class Multisafe
{
	private $mode;
	private $payment_url;

	public function __construct()
	{
        $this->mode = config('app.multi_safe_mode');
        $this->payment_url = $this->mode == 'live' ? config('app.payment_live_url') : config('app.payment_test_url');
	}

	public function getPaymentMethods($api_key)
	{
        $client = new Client(['headers' => ['api_key' => $api_key]]);
        $request = $client->request('GET', $this->payment_url . '/gateways');
        $statusCode = $request->getStatusCode();
        $request->getHeaderLine('content-type');
        $response = json_decode($request->getBody()->getContents(), true);
        if ($statusCode == 200 && isset($response['data'])) {

            $payment_methods = [];
            foreach ($response['data'] as $payment_method){
                $payment_method['payment_class'] = addPaymentClass($payment_method['description']);
                array_push($payment_methods, $payment_method);
            }

            return $payment_methods;
        } else {
            return false;
        }

	}

    public function getIssuers($api_key)
    {
        $client = new Client(['headers' => ['api_key' => $api_key]]);
        $request = $client->request('GET', $this->payment_url . '/issuers/IDEAL');
        $statusCode = $request->getStatusCode();
        $request->getHeaderLine('content-type');
        $response = json_decode($request->getBody()->getContents(), true);
        if ($statusCode == 200 && isset($response['data'])) {
            return $response['data'];
        } else {
            return false;
        }

    }


    public function postOrder($api_key, $data)
    {
        $client = new Client(['headers' => ['api_key' => $api_key]]);
        $request = $client->request('POST', $this->payment_url . '/orders', [
            'form_params' => $data
        ]);
        $statusCode = $request->getStatusCode();
        $request->getHeaderLine('content-type');
        $response = json_decode($request->getBody()->getContents(), true);
        if ($statusCode == 200 && isset($response['data'])) {
            return $response['data'];
        } else {
            return false;
        }
    }

    public function getOrder($api_key, $order_id)
    {
        $client = new Client(['headers' => ['api_key' => $api_key]]);
        $request = $client->request('GET', $this->payment_url . '/orders/' . $order_id);
        $statusCode = $request->getStatusCode();
        $request->getHeaderLine('content-type');
        $response = json_decode($request->getBody()->getContents(), true);
        if ($statusCode == 200 && isset($response['data'])) {
            return $response['data'];
        } else {
            return false;
        }
    }

    public function refundOrder($api_key, $order_id, $data)
    {
        $client = new Client(['headers' => ['api_key' => $api_key]]);
        $request = $client->request('POST', $this->payment_url . '/orders/' . $order_id . '/refunds', [
            'form_params' => $data
        ]);
        $statusCode = $request->getStatusCode();
        $request->getHeaderLine('content-type');
        $response = json_decode($request->getBody()->getContents(), true);
        if ($statusCode == 200 && isset($response['data'])) {
            return $response['data'];
        } else {
            return false;
        }
    }

}
