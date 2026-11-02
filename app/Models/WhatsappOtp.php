<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WhatsappOtp extends Model
{
    protected $fillable = [
        'phone_number',
        'otp',
        'expires_at',
        'is_verified',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Check if max attempts exceeded (5 attempts max)
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    /**
     * Increment attempts
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->update(['is_verified' => true]);
    }

    /**
     * Generate a new OTP
     */
    public static function generateOtp(string $phoneNumber, int $expiryMinutes = 5): self
    {
        // Invalidate any existing OTPs for this phone
        self::where('phone_number', $phoneNumber)
            ->where('is_verified', false)
            ->delete();

        return self::create([
            'phone_number' => $phoneNumber,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            'is_verified' => false,
            'attempts' => 0,
        ]);
    }

    /**
     * Verify OTP
     */
    public static function verifyOtp(string $phoneNumber, string $otp): array
    {
        $otpRecord = self::where('phone_number', $phoneNumber)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return [
                'success' => false,
                'message' => 'No OTP found for this phone number. Please request a new one.',
            ];
        }

        if ($otpRecord->isExpired()) {
            $otpRecord->delete();
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ];
        }

        if ($otpRecord->hasExceededAttempts()) {
            $otpRecord->delete();
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new OTP.',
            ];
        }

        if ($otpRecord->otp !== $otp) {
            $otpRecord->incrementAttempts();
            $remaining = 5 - $otpRecord->attempts;
            return [
                'success' => false,
                'message' => "Invalid OTP. {$remaining} attempts remaining.",
            ];
        }

        $otpRecord->markAsVerified();

        return [
            'success' => true,
            'message' => 'Phone number verified successfully!',
        ];
    }
}
