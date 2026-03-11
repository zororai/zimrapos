<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReceiptQrCodeService
{
    /**
     * Generate QR code from FDMS verification URL
     *
     * @param string $verificationUrl The full FDMS verification URL
     * @param string $receiptId The FDMS receipt ID
     * @return array ['qr_url' => string, 'verification_code' => string, 'qr_path' => string]
     */
    public function generateQrCode(string $verificationUrl, string $receiptId): array
    {
        // Ensure the QR codes directory exists
        $directory = 'public/zimra/qrcodes';
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        // Generate filename
        $filename = "qr_{$receiptId}.png";
        $filePath = "{$directory}/{$filename}";

        // Generate QR code as PNG
        $qrCode = QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->generate($verificationUrl);

        // Store the QR code
        Storage::put($filePath, $qrCode);

        // Generate public URL
        $publicUrl = Storage::url($filePath);

        // Extract and format verification code from URL
        $verificationCode = $this->extractVerificationCode($verificationUrl);

        return [
            'qr_url' => $publicUrl,
            'verification_code' => $verificationCode,
            'qr_path' => $filePath,
        ];
    }

    /**
     * Extract and format verification code from FDMS URL
     *
     * The verification code is typically a hash of the receipt parameters
     * Format: XXXX-XXXX-XXXX-XXXX
     *
     * @param string $verificationUrl
     * @return string
     */
    protected function extractVerificationCode(string $verificationUrl): string
    {
        // Parse URL to get query parameters
        $parsedUrl = parse_url($verificationUrl);
        $queryParams = [];
        
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        // If ReceiptQrData is already in the URL, return it directly
        if (isset($queryParams['ReceiptQrData'])) {
            return $queryParams['ReceiptQrData'];
        }

        // Otherwise, generate verification code from URL parameters
        // Format: DeviceId + ReceiptCounterReceiptGlobalNo + (fiscalDayNo if available)
        $codeString = sprintf(
            '%s%s%s',
            $queryParams['DeviceId'] ?? $queryParams['deviceID'] ?? '',
            $queryParams['ReceiptCounterReceiptGlobalNo'] ?? $queryParams['receiptGlobalNo'] ?? '',
            $queryParams['fiscalDayNo'] ?? ''
        );

        // Generate a hash and format it
        $hash = strtoupper(substr(md5($codeString), 0, 16));
        
        // Format as XXXX-XXXX-XXXX-XXXX
        return sprintf(
            '%s-%s-%s-%s',
            substr($hash, 0, 4),
            substr($hash, 4, 4),
            substr($hash, 8, 4),
            substr($hash, 12, 4)
        );
    }

    /**
     * Delete QR code file
     *
     * @param string $receiptId
     * @return bool
     */
    public function deleteQrCode(string $receiptId): bool
    {
        $filePath = "public/zimra/qrcodes/qr_{$receiptId}.png";
        
        if (Storage::exists($filePath)) {
            return Storage::delete($filePath);
        }

        return false;
    }

    /**
     * Check if QR code exists for a receipt
     *
     * @param string $receiptId
     * @return bool
     */
    public function qrCodeExists(string $receiptId): bool
    {
        $filePath = "public/zimra/qrcodes/qr_{$receiptId}.png";
        return Storage::exists($filePath);
    }

    /**
     * Get QR code URL for existing receipt
     *
     * @param string $receiptId
     * @return string|null
     */
    public function getQrCodeUrl(string $receiptId): ?string
    {
        $filePath = "public/zimra/qrcodes/qr_{$receiptId}.png";
        
        if (Storage::exists($filePath)) {
            return Storage::url($filePath);
        }

        return null;
    }
}
