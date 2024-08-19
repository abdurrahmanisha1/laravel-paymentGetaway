<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Karim007\LaravelBkashTokenize\Facade\BkashPaymentTokenize;
use Karim007\LaravelBkashTokenize\Facade\BkashRefundTokenize;

class BkashPaymentController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->input('user_id',1);
        $plan_id = $request->input('plan_id',1); // Default amount is 100 BDT if not provided
        $orderId = uniqid('order_');
        $amount = $request->input('amount',10); // Default amount is 100 BDT if not provided
        $currency = 'BDT';
        $invoiceNumber = uniqid('invoice_');

        // Call createPayment with the current request
        //$createPaymentResponse = $this->createPayment($user_id, $plan_id, $amount, $request);


        return response()->json([
            'status' => 'success',
            'message' => 'Bkash payment initialized',
            'data' => [
                'user_id' => $user_id,
                'plan_id' => $plan_id,
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'invoice_number' => $invoiceNumber,
            ]
        ], 200);
    }

    public function createPayment(Request $request)
    {
        $user_id = $request->query('user_id',1);
        $plan_id = $request->query('plan_id',1);
        $order_id = $request->query('order_id', uniqid('order_'));


        // $user_id = $request->input('user_id');
        // $plan_id = $request->input('plan_id');
        // $amount = $request->input('amount');
        //$order = $request->input('amount', 1);

        //$checkPlan = Plan::find($request->plan_id);
        $checkPlan = Plan::find($plan_id);
        $amount = $checkPlan->plan_amount;

        if ($checkPlan) {
            $payout = (string) $amount;

            if ($payout <= 0) {
                return response()->json(['error' => __('frontWords.insufficient_amount_for_transaction')], 400);
            }

           DB::table('orders')->insert([
                'user_id' => $user_id,
                'plan_id' => $plan_id,
                'order_id' => $order_id,
                'status' => 0, // Assuming 0 is for pending
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // $respObj = (object) [
            //     'transaction_id' => uniqid(),
            //     'amount' => $payout,
            //     'payment_gateway' => 'bkash',
            //     'order_id' => uniqid(),
            //     'currency' => 'BDT',
            // ];

            $inv = $order_id;
            $request['intent'] = 'sale';
            $request['mode'] = '0011'; // 0011 for checkout
            $request['payerReference'] = $inv;
            $request['currency'] = 'BDT';
            $request['amount'] = $payout;
            $request['merchantInvoiceNumber'] = $order_id;
            //$request['callbackURL'] = config("bkash.callbackURL");
            $request['callbackURL'] = route('bkash.payment.callback');

            $request_data_json = json_encode($request->all());

            $response = BkashPaymentTokenize::cPayment($request_data_json);
            //return response()->json(['data' => $response], 200);

            if (isset($response['bkashURL'])) {
                return response()->json(['bkashURL' => $response['bkashURL']], 200);
            } else {
                return response()->json(['error' => $response['statusMessage']], 400);
            }
        } else {
            return response()->json(['error' => __('frontWords.try_again')], 400);
        }
    }

    public function callBack(Request $request)
    {
        if ($request->status == 'success') {
            $response = BkashPaymentTokenize::executePayment($request->paymentID);

            if (!$response) {
                $response = BkashPaymentTokenize::queryPayment($request->paymentID);
            }

            if (isset($response['statusCode']) && $response['statusCode'] == "0000" && $response['transactionStatus'] == "Completed") {
                // Save payment data and update user plan here
                // You can use $this->savePaymentData($params) method for saving data
                $merchantInvoiceNumber = $response['merchantInvoiceNumber'] ?? null;
                if ($merchantInvoiceNumber) {
                    // Update the order status in the database
                    DB::table('orders')
                        ->where('order_id', $merchantInvoiceNumber)
                        ->update(['status' => 1]); // Assuming 1 is for success

                    // Retrieve the updated order details
                    $order = DB::table('orders')
                        ->where('order_id', $merchantInvoiceNumber)
                        ->first();

                    // Prepare payment data
                    $respObj = (object) [
                        'transaction_id' => $response['paymentID'],
                        'amount' => $response['amount'],
                        'payment_gateway' => 'bkash',
                        'order_id' => $response['merchantInvoiceNumber'],
                        'currency' => 'BDT',
                    ];

                    $paymentObj[] = $respObj;

                    // Insert payment data into the database
                    $sendData = [
                        'user_id' => $order->user_id, // Assuming the `user_id` field exists in the orders table
                        'type' => 'bkash',
                        'status' => 1, // Assuming 1 indicates successful payment
                        'plan_id' => $order->plan_id, // Update this as needed
                        'payment_data' => json_encode($paymentObj),
                        'order_id' => $merchantInvoiceNumber
                    ];

                    //SuccessPayment::create($sendData);
                    DB::table('success_payments')->insert($sendData);

                    // If not manual payment, add to paymentGateway
                    //PaymentGateway::create($sendData);
                    DB::table('payment_gateways')->insert($sendData);

                    $getPlan = Plan::find($order->plan_id);
                    // //dd($getPlan);
                    if ($getPlan) {
                        $isDayMonth = $getPlan->is_month_days;
                        $daysMon = ($isDayMonth == 0) ? 'day' : 'month';
                        $planValid = $getPlan->validity;
                        $expiry_date = date("Y-m-d", strtotime("+".$planValid.' '.$daysMon));

                        // UserPurchasedPlan::create([
                        //     'user_id' => $order->user_id,
                        //     'plan_id' => $getPlan->id,
                        //     'order_id' => $order->order_id,
                        //     'plan_data' => json_encode($getPlan),
                        //     'payment_data' => json_encode($paymentObj),
                        //     'currency' => 'BDT',
                        //     'expiry_date' => $expiry_date
                        // ]);

                        DB::table('user_purchased_plans')->insert([
                            'user_id' => $order->user_id,
                            'plan_id' => $getPlan->id,
                            'order_id' => $order->order_id,
                            'plan_data' => json_encode($getPlan),
                            'payment_data' => json_encode($paymentObj),
                            'currency' => 'BDT',
                            'expiry_date' => $expiry_date
                        ]);

                    }

                    $purchased_plan_date = date("Y-m-d", strtotime(date('Y-m-d')));
                    // $user = User::where('id', $order->user_id)->first();
                    $user = DB::table('users')->where('id', $order->user_id)->first();
                    DB::table('users')->where('id', $order->user_id)
                        ->update(['plan_id' => $getPlan->id,'purchased_plan_date' => $purchased_plan_date // Set to current date/time or any specific date
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment successful.'
                ], 200);
            }  else {
            // Return JSON response with failure status
            return response()->json([
                'status' => 'failure',
                'message' => 'Transaction failure'
            ], 200);
        }


        } elseif ($request->status == 'cancel') {
            return response()->json([
                 'status' => 'cancel',
                 'message' => 'Payment was cancel.'
            ], 200);
        } else {
            return response()->json([
               'status' => 'failure',
                'message' => 'Transaction failed.'

            ], 200);
        }
    }
}
