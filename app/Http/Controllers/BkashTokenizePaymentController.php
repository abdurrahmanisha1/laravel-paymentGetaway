<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Karim007\LaravelBkashTokenize\Facade\BkashRefundTokenize;
use Karim007\LaravelBkashTokenize\Facade\BkashPaymentTokenize;

class BkashTokenizePaymentController extends Controller
{
    public function index()
    {
        return view('bkashT::bkash-payment');
    }
    //working
    public function createPayment(Request $request)
    {
        $inv = uniqid();
        $request['intent'] = 'sale';
        $request['mode'] = '0011'; //0011 for checkout
        $request['payerReference'] = $inv;
        $request['currency'] = 'BDT';
        // $request['amount'] = 10;
        $request['amount'] = (string)$request->input('amount', '10');
        $request['merchantInvoiceNumber'] = $inv;
        $request['callbackURL'] = config("bkash.callbackURL");

        $request_data_json = json_encode($request->all());
        //dd($request_data_json);
        $response =  BkashPaymentTokenize::cPayment($request_data_json);
        //$response =  BkashPaymentTokenize::cPayment($request_data_json,1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
        //dd($response);
        //store paymentID and your account number for matching in callback request
        //dd($response); //if you are using sandbox and not submit info to bkash use it for 1 response

        if (isset($response['bkashURL'])) return redirect()->away($response['bkashURL']);
        else return redirect()->back()->with('error-alert2', $response['statusMessage']);
    }

    //working also
    // public function createPayment(Request $request)
    // {
    //     $inv = uniqid();
    //     $requestData = [
    //         'intent' => 'sale',
    //         'mode' => '0011', // 0011 for checkout
    //         'payerReference' => $inv,
    //         'currency' => 'BDT',
    //         //'amount' => $request->input('amount', 10),
    //         'amount' => (string)$request->input('amount', '10'),
    //         'merchantInvoiceNumber' => $inv,
    //         'callbackURL' => config("bkash.callbackURL"),
    //     ];

    //     $request_data_json = json_encode($requestData);

    //     // Log the request data for debugging
    //     Log::info('bKash Payment Request Data', ['request_data' => $requestData]);

    //     try {
    //         $response = BkashPaymentTokenize::cPayment($request_data_json);
    //         //dd($response);
    //         // Log the response for debugging
    //         Log::info('bKash Payment Response', ['response' => $response]);

    //         if (isset($response['bkashURL'])) {
    //             return redirect()->away($response['bkashURL']);
    //         } else {
    //             $errorMessage = isset($response['statusMessage']) ? $response['statusMessage'] : 'Unknown error occurred.';
    //             Log::error('bKash Payment Error', ['response' => $response]);
    //             return redirect()->back()->with('error-alert2', $errorMessage);
    //         }
    //     } catch (\Exception $e) {
    //         dd($e->getMessage());
    //         Log::error('bKash Payment Exception', ['message' => $e->getMessage()]);
    //         return redirect()->back()->with('error-alert2', 'Payment creation failed. Please try again.');
    //     }
    // }


    public function callBack(Request $request)
    {
        //callback request params
        // paymentID=your_payment_id&status=success&apiVersion=1.2.0-beta
        //using paymentID find the account number for sending params

        if ($request->status == 'success'){
            $response = BkashPaymentTokenize::executePayment($request->paymentID);
            //$response = BkashPaymentTokenize::executePayment($request->paymentID, 1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
            if (!$response){ //if executePayment payment not found call queryPayment
                $response = BkashPaymentTokenize::queryPayment($request->paymentID);
                //$response = BkashPaymentTokenize::queryPayment($request->paymentID,1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
            }

            if (isset($response['statusCode']) && $response['statusCode'] == "0000" && $response['transactionStatus'] == "Completed") {
                /*
                 * for refund need to store
                 * paymentID and trxID
                 * */
                return BkashPaymentTokenize::success('Thank you for your payment', $response['trxID']);
            }
            return BkashPaymentTokenize::failure($response['statusMessage']);
        }else if ($request->status == 'cancel'){
            return BkashPaymentTokenize::cancel('Your payment is canceled');
        }else{
            return BkashPaymentTokenize::failure('Your transaction is failed');
        }
    }

    public function searchTnx($trxID)
    {
        //response
        return BkashPaymentTokenize::searchTransaction($trxID);
        //return BkashPaymentTokenize::searchTransaction($trxID,1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
    }

    public function refund(Request $request)
    {
        $paymentID='Your payment id';
        $trxID='your transaction no';
        $amount=5;
        $reason='this is test reason';
        $sku='abc';
        //response
        return BkashRefundTokenize::refund($paymentID,$trxID,$amount,$reason,$sku);
        //return BkashRefundTokenize::refund($paymentID,$trxID,$amount,$reason,$sku, 1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
    }
    public function refundStatus(Request $request)
    {
        $paymentID='Your payment id';
        $trxID='your transaction no';
        return BkashRefundTokenize::refundStatus($paymentID,$trxID);
        //return BkashRefundTokenize::refundStatus($paymentID,$trxID, 1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
    }
}
