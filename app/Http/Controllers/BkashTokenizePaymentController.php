<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Karim007\LaravelBkashTokenize\Facade\BkashPaymentTokenize;
use Karim007\LaravelBkashTokenize\Facade\BkashRefundTokenize;

use Modules\Plan\Entities\Plan;
use Redirect;
use App\User;
use App\UserPurchasedPlan;
use App\SuccessPayment;
use App\paymentGateway;


class BkashTokenizePaymentController extends Controller
{
    public function index()
    {
        return view('bkashT::bkash-payment');
    }

    public function createPayment(Request $request)
    {
        //$checkPlan = Plan::where(['id' => $request->plan_id])->get();
        $checkPlan = Plan::where(['id' => 13])->get();
        if(sizeof($checkPlan) > 0){
            //$payout = $request->amount;
            $payout = (string)$request->input('amount', 1);
            //dd($payout);

            if(!empty($payout) && $payout <= 0){
                alert()->error( __('frontWords.insufficient_amount_for_transaction'))->persistent("Close");
                return Redirect::back();
            }

            // $respObj = (object)[
            //     'transaction_id'=> uniqid(),
            //     'amount'=> $payout,
            //     'payment_gateway'=>'bkash',
            //     'order_id' => uniqid(),
            //     'discount' => $request->discountApplied,
            //     'plan_exact_amount' => $request->planExactAmnt,
            //     'taxPercent' => $request->taxPercent,
            //     'taxAmount' => $request->taxApplied,
            //     'currency' => 'BDT',
            //     //'user_name' => auth()->user()->name,
            //     //'user_email' => auth()->user()->email
            // ];

            //dd($respObj);
                  //dd($purchased_plan_date);
            // $getResp = $this->savePaymentData([ 'user_id' => Auth::user()->id, 'plan_id' => $request->plan_id, 'respObj' => $respObj, 'type' => 'stripe', 'status' => 1 ]);

            // Save order ID to session
            //$request->session()->put('order_id', $respObj->order_id);

            //dd($request->session()->put('order_id', $respObj->order_id));

        } else{
            alert()->error( __('frontWords.try_again'))->persistent("Close");
                return Redirect::back();
        }

        // {
        //     id,
        //     order_id,
        //     user_id,
        //     status,
        // }

        $respObj = (object) [
                    'TaxId' => uniqid(),
                    'amount' => $payout,
                    'payment_gateway' => 'bkash',
                    'order_id' => uniqid(),
                    'currency' => 'BDT',
                    //'user_name' => auth()->user()->name,
                    //'user_email' => auth()->user()->email
                ];

        //$inv = uniqid();
        $inv = uniqid();
        $request['intent'] = 'sale';
        $request['mode'] = '0011'; //0011 for checkout
        $request['payerReference'] = $inv;
        $request['currency'] = 'BDT';
        //$request['amount'] = 10;
        $request['amount'] = $payout;
        $request['merchantInvoiceNumber'] = $respObj->order_id;   //$inv;
        $request['callbackURL'] = config("bkash.callbackURL");

        $request_data_json = json_encode($request->all());


        $response =  BkashPaymentTokenize::cPayment($request_data_json);
        //$response =  BkashPaymentTokenize::cPayment($request_data_json,1); //last parameter is your account number for multi account its like, 1,2,3,4,cont..
        //dd($response);
        //store paymentID and your account number for matching in callback request
        // dd($response) //if you are using sandbox and not submit info to bkash use it for 1 response

        //dd($respObj);

        if (isset($response['bkashURL'])) return redirect()->away($response['bkashURL']);
        else return redirect()->back()->with('error-alert2', $response['statusMessage']);
    }

    public function callBack(Request $request)
    {

        if ($request->status == 'success') {
            $response = BkashPaymentTokenize::executePayment($request->paymentID);

            // $respObj = (object) [
            //         'transaction_id' => uniqid(),
            //         'amount' => 10,
            //         'payment_gateway' => 'bkash',
            //         'order_id' => uniqid(),
            //         'currency' => 'BDT',
            //         //'user_name' => auth()->user()->name,
            //         //'user_email' => auth()->user()->email
            //     ];
            // $user_id = 144;
            // $user = User::where('id', $user_id)->first();
            // $purchased_plan_date = date("Y-m-d");
            // $user->update(['plan_id' => 13, 'purchased_plan_date' => $purchased_plan_date]);

        // $paymentObj[] = $respObj;
        // $sendData = [
        //     'user_id' => $user_id,
        //     'type' => 'bkash',
        //     'status' => 1,
        //     'plan_id' => 13,
        //     'payment_data' => json_encode($paymentObj),
        //     'order_id' => $respObj->order_id
        // ];

        // //dd($sendData);
        // SuccessPayment::create($sendData);

        // // If not manual payment, add to paymentGateway
        // paymentGateway::create($sendData);

        // // Handle plan validity
        // $getPlan = Plan::find(13);
        // //dd($getPlan);
        // if ($getPlan) {
        //         $isDayMonth = $getPlan->is_month_days;
        //         $daysMon = ($isDayMonth == 0) ? 'day' : 'month';
        //         $planValid = $getPlan->validity;
        //         $expiry_date = date("Y-m-d", strtotime("+".$planValid.' '.$daysMon));

        //         UserPurchasedPlan::create([
        //             'user_id' => $user_id,
        //             'plan_id' => $getPlan->id,
        //             'order_id' => $respObj->order_id,
        //             'plan_data' => json_encode($getPlan),
        //             'payment_data' => json_encode($paymentObj),
        //             'currency' => 'BDT',
        //             'expiry_date' => $expiry_date
        //         ]);
        // }


            if (!$response) {
                $response = BkashPaymentTokenize::queryPayment($request->paymentID);
            }

            if (isset($response['statusCode']) && $response['statusCode'] == "0000" && $response['transactionStatus'] == "Completed") {
                // $user_id = 144;
                // $user = User::where('id', $user_id)->first();
                // $purchased_plan_date = date("Y-m-d");
                // $user->update(['plan_id' => 13, 'purchased_plan_date' => $purchased_plan_date]);
                //$order_id = $request->session()->get('order_id');

                // Retrieve order ID and user ID from session or database
                //$user_id = Auth::id();

                // Populate respObj with necessary data from bKash response


                //dd($respObj);

                // // Save payment data
                // $this->savePaymentData([
                //     'user_id' => $user_id,
                //     'plan_id' => $request->plan_id,
                //     'respObj' => $respObj,
                //     'type' => 'bkash'
                // ]);

                // Insert payment data into SuccessPayment NEW PART
                // $paymentObj[] = $respObj;
                // $sendData = [
                //     'user_id' => $user_id,
                //     'type' => 'bkash',
                //     'status' => 1,
                //     'plan_id' => 13,
                //     'payment_data' => json_encode($paymentObj),
                //     'order_id' => $respObj->order_id
                // ];
                // SuccessPayment::create($sendData);

                // // If not manual payment, add to paymentGateway
                // paymentGateway::create($sendData);

                // // Update user plan
                // $purchased_plan_date = date("Y-m-d");
                // User::where('id', $user_id)->update(['plan_id' => 13, 'purchased_plan_date' => $purchased_plan_date]);

                // // Handle plan validity
                // $getPlan = Plan::find(13);

                // if ($getPlan) {
                //     $isDayMonth = $getPlan->is_month_days;
                //     $daysMon = ($isDayMonth == 0) ? 'day' : 'month';
                //     $planValid = $getPlan->validity;
                //     $expiry_date = date("Y-m-d", strtotime("+".$planValid.' '.$daysMon));

                //     UserPurchasedPlan::create([
                //         'user_id' => $user_id,
                //         'plan_id' => $getPlan->id,
                //         'order_id' => $respObj->order_id,
                //         'plan_data' => json_encode($getPlan),
                //         'payment_data' => json_encode($paymentObj),
                //         'currency' => 'BDT',
                //         'expiry_date' => $expiry_date
                //     ]);
                // }

                // return redirect()->route('dashboard')->with('success', 'Payment completed successfully. Thank you!');
                //return BkashPaymentTokenize::success('Thank you for your payment', $response['trxID']);
                echo 'Thank you for your payment'.$response['trxID'];
                return true;
            }
            return BkashPaymentTokenize::failure('Your transaction is failed');

            // $user_id = 144;
            // $purchased_plan_date = date("Y-m-d");/
            // User::where('id', $user_id)->update(['plan_id' => 13, 'purchased_plan_date' => $purchased_plan_date]);

        } elseif ($request->status == 'cancel') {
            //return redirect()->route('dashboard')->with('error', 'Your payment is canceled');
            return BkashPaymentTokenize::cancel('Your payment is canceled');
        } else {
            // return BkashPaymentTokenize::failure('Your transaction is failed');
            //return redirect()->route('dashboard')->with('error', 'Your transaction failed');
            echo 'payment';
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
