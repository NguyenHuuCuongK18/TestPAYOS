<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use PayOS\PayOS;
use Exception;

class OrderController extends Controller
{
    private function getPayOSInstance()
    {
        return new PayOS(
            config('services.payos.client_id'),
            config('services.payos.api_key'),
            config('services.payos.checksum_key')
        );
    }

    private function removeAccents($str)
    {
        return preg_replace(
            array('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', '/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', '/(ì|í|ị|ỉ|ĩ)/', '/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', '/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', '/(ỳ|ý|ỵ|ỷ|ỹ)/', '/(đ)/', '/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/', '/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/', '/(Ì|Í|Ị|Ỉ|Ĩ)/', '/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/', '/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/', '/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/', '/(Đ)/'),
            array('a', 'e', 'i', 'o', 'u', 'y', 'd', 'A', 'E', 'I', 'O', 'U', 'Y', 'D'),
            $str
        );
    }

    public function create(Request $request)
    {
        try {
            $amount = intval($request->input('price'));
            if ($amount < 2000) {
                return response()->json([
                    'error' => -1,
                    'message' => 'Số tiền thanh toán tối thiểu là 2,000 VNĐ'
                ], 400);
            }
            $description = $request->input('description', 'Thanh toan don hang');
            $productName = $request->input('productName', 'Mì tôm Hảo Hảo ly');
            $returnUrl = $request->input('returnUrl');
            $cancelUrl = $request->input('cancelUrl');

            // Generate a unique 9-digit order code
            $orderCode = intval(substr(strval(time()), -6)) * 1000 + rand(100, 999);

            // Remove accents and sanitize description for payOS (max 25 characters, alphanumeric, spaces, - or _)
            $cleanDescription = $this->removeAccents($description);
            $sanitizedDescription = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $cleanDescription);
            $sanitizedDescription = trim(substr($sanitizedDescription, 0, 25));
            if (empty($sanitizedDescription)) {
                $sanitizedDescription = "Thanh toan don hang";
            }

            $payosData = [
                "orderCode" => $orderCode,
                "amount" => $amount,
                "description" => $sanitizedDescription,
                "items" => [
                    [
                        "name" => $this->removeAccents($productName),
                        "quantity" => 1,
                        "price" => $amount
                    ]
                ],
                "returnUrl" => $returnUrl,
                "cancelUrl" => $cancelUrl
            ];

            $payOS = $this->getPayOSInstance();
            $response = $payOS->paymentRequests->create($payosData, ['asArray' => true]);

            // Save order to database
            Order::create([
                'id' => $orderCode,
                'status' => 'PENDING',
                'amount' => $amount,
                'description' => $description,
                'product_name' => $productName,
                'price' => $amount,
            ]);

            return response()->json([
                'error' => 0,
                'message' => 'Success',
                'data' => [
                    'bin' => $response['bin'],
                    'accountNumber' => $response['accountNumber'],
                    'accountName' => $response['accountName'],
                    'amount' => $response['amount'],
                    'description' => $response['description'],
                    'orderCode' => $response['orderCode'],
                    'qrCode' => $response['qrCode'],
                    'checkoutUrl' => $response['checkoutUrl'],
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => -1,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function get($orderId)
    {
        try {
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'error' => -1,
                    'message' => 'Không tìm thấy đơn hàng'
                ]);
            }

            // Sync status with payOS in real-time to support local development without webhooks
            try {
                $payOS = $this->getPayOSInstance();
                $paymentLinkInfo = $payOS->paymentRequests->get($orderId, ['asArray' => true]);

                if ($paymentLinkInfo['status'] === 'PAID') {
                    $order->status = 'PAID';
                    
                    $transaction = !empty($paymentLinkInfo['transactions']) ? $paymentLinkInfo['transactions'][0] : null;
                    
                    $order->webhook_snapshot = [
                        'data' => [
                            'orderCode' => $order->id,
                            'amount' => $paymentLinkInfo['amountPaid'] ?? $order->amount,
                            'description' => $transaction ? ($transaction['description'] ?? '') : $order->description,
                            'accountNumber' => $transaction ? ($transaction['accountNumber'] ?? '') : '',
                            'reference' => $transaction ? ($transaction['reference'] ?? '') : '',
                            'transactionDateTime' => $transaction ? ($transaction['transactionDateTime'] ?? '') : '',
                            'paymentLinkId' => $paymentLinkInfo['id'] ?? '',
                            'code' => '00',
                            'desc' => 'Success',
                            'counterAccountBankId' => $transaction ? ($transaction['counterAccountBankId'] ?? '') : '',
                            'counterAccountBankName' => $transaction ? ($transaction['counterAccountBankName'] ?? '') : '',
                            'counterAccountName' => $transaction ? ($transaction['counterAccountName'] ?? '') : '',
                            'counterAccountNumber' => $transaction ? ($transaction['counterAccountNumber'] ?? '') : '',
                            'virtualAccountName' => $transaction ? ($transaction['virtualAccountName'] ?? '') : '',
                            'virtualAccountNumber' => $transaction ? ($transaction['virtualAccountNumber'] ?? '') : '',
                        ]
                    ];
                    $order->save();
                } elseif ($paymentLinkInfo['status'] === 'CANCELLED') {
                    $order->status = 'CANCELLED';
                    $order->save();
                }
            } catch (Exception $e) {
                // Fall back to local SQLite status on network/API exception
            }

            return response()->json([
                'error' => 0,
                'message' => 'Success',
                'data' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'amount' => $order->amount,
                    'items' => [
                        [
                            'name' => $order->product_name,
                            'quantity' => 1,
                            'price' => $order->price,
                        ]
                    ],
                    'webhook_snapshot' => $order->webhook_snapshot,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => -1,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, $orderId)
    {
        try {
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'error' => -1,
                    'message' => 'Không tìm thấy đơn hàng'
                ]);
            }

            $payOS = $this->getPayOSInstance();
            try {
                $payOS->paymentRequests->cancel($orderId, 'User cancelled', ['asArray' => true]);
            } catch (Exception $e) {
                // If it's already cancelled on payOS or expired, swallow the exception
            }

            $order->status = 'CANCELLED';
            $order->save();

            return response()->json([
                'error' => 0,
                'message' => 'Success'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => -1,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
