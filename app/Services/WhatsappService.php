<?php


namespace App\Services;

use App\Models\WhatsappOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WhatsappService
{

    protected function sendWhatsappMessage(string $phoneNumber , string $customisedMessage): bool
    {
        $maxRetries = 3;
        $attempt = 0;
        $success = false;

        while ($attempt < $maxRetries && !$success) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => config('whatsapp.api_url'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => [
                    'appkey' => config('whatsapp.app_key'),
                    'authkey' => config('whatsapp.auth_key'),
                    'to' => $phoneNumber,
                    'message' => $customisedMessage,
                    'sandbox' => 'false'
                ],
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($error) {
                Log::warning('WhatsApp cURL error', [
                    'error' => $error,
                    'attempt' => $attempt + 1,
                    'phone' => $phoneNumber
                ]);
            }

            if ($response) {
                $responseData = json_decode($response, true);

                Log::info('WhatsApp API response', [
                    'http_code' => $httpCode,
                    'response' => $responseData,
                    'attempt' => $attempt + 1
                ]);

                // Check if response is successful
                if (isset($responseData['message_status']) && $responseData['message_status'] === 'Success') {
                    $success = true;
                }
            }

            $attempt++;

            if (!$success && $attempt < $maxRetries) {
                usleep(500000); // 0.5 seconds
            }
        }

        if (!$success) {
            Log::error('WhatsApp: Failed after ' . $maxRetries . ' attempts', ['phone' => $phoneNumber]);
        }

        return $success;
    }


    public function send(string $phoneNumber, string $message): bool
    {
        return $this->sendWhatsappMessage($phoneNumber, $message);
    }

    /**
     * Send OTP to WhatsApp number
     */
    public function sendOtp(string $phoneNumber): array
    {
        // Rate limiting: Max 3 OTPs per phone per hour
        $cacheKey = "otp_limit_{$phoneNumber}";
        $attempts = Cache::get($cacheKey, 0);

        // if ($attempts >= 3) {
        //     return [
        //         'success' => false,
        //         'message' => 'Too many OTP requests. Please try again in 1 hour.',
        //     ];
        // }

        // Generate OTP
        $otpRecord = WhatsappOtp::generateOtp($phoneNumber);
        $otp = $otpRecord->otp;

        // Create OTP message
        $appName = config('app.name', 'SportApp');
        $message = "🔐 *{$appName} Verification*\n\n"
            . "Your OTP code is: *{$otp}*\n\n"
            . "⏰ Valid for 5 minutes.\n"
            . "❌ Do not share this code with anyone.";

        // Send via WhatsApp
        $success = $this->sendWhatsappMessage($phoneNumber, $message);

        if ($success) {
            // Increment rate limit counter
            Cache::put($cacheKey, $attempts + 1, now()->addHour());

            return [
                'success' => true,
                'message' => 'OTP sent successfully to your WhatsApp.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.',
        ];
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $phoneNumber, string $otp): array
    {
        return WhatsappOtp::verifyOtp($phoneNumber, $otp);
    }
}
