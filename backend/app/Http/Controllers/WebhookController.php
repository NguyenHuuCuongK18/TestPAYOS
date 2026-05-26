<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use PayOS\PayOS;
use Exception;

class WebhookController extends Controller
{
    private function getPayOSInstance()
    {
        return new PayOS(
            config('services.payos.client_id'),
            config('services.payos.api_key'),
            config('services.payos.checksum_key')
        );
    }

    public function handle(Request $request)
    {
        try {
            $webhookBody = $request->all();
            
            $payOS = $this->getPayOSInstance();
            // Verify webhook signature and extract data
            $verifiedData = $payOS->webhooks->verify($webhookBody, ['asArray' => true]);

            $orderCode = $verifiedData['orderCode'];
            $order = Order::find($orderCode);

            if ($order) {
                // Update status to PAID if successful
                $order->status = 'PAID';
                $order->webhook_snapshot = $webhookBody;
                $order->save();
            }

            return response()->json([
                'error' => 0,
                'message' => 'Webhook processed successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => -1,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
