<?php

namespace App\Services;

use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ZimraDeviceService
{
    public function registerDevice(int $deviceId, string $serialNumber, string $activationKey)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found. Please create one first.');
        }

        $paddedId = str_pad($deviceId, 10, '0', STR_PAD_LEFT);
        $commonName = "ZIMRA-{$serialNumber}-{$paddedId}";

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Generate ECC P-256 Private Key
        |--------------------------------------------------------------------------
        */
        $config = [
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name" => "prime256v1",
        ];

        $privateKeyResource = openssl_pkey_new($config);

        openssl_pkey_export($privateKeyResource, $privateKeyPem);

        Storage::put("zimra/device_private.key", $privateKeyPem);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generate CSR
        |--------------------------------------------------------------------------
        */
        $dn = [
            "countryName" => "ZW",
            "organizationName" => "Zimbabwe Revenue Authority",
            "commonName" => $commonName,
        ];

        $csrResource = openssl_csr_new($dn, $privateKeyResource, [
            "digest_alg" => "sha256"
        ]);

        openssl_csr_export($csrResource, $csrPem);

        Storage::put("zimra/device.csr", $csrPem);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Convert CSR to JSON Format
        |--------------------------------------------------------------------------
        */
        $csrForJson = str_replace("\n", "\\n", trim($csrPem));

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Register Device
        |--------------------------------------------------------------------------
        */
        $baseUrl = $zimraConfig->base_url;

        $response = Http::withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->post("{$baseUrl}/Public/v1/{$deviceId}/RegisterDevice", [
            "certificateRequest" => $csrForJson,
            "activationKey" => $activationKey
        ]);

        if (!$response->successful()) {
            return $response->json();
        }

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Save Device Certificate
        |--------------------------------------------------------------------------
        */
        $certificatePem = $response->json()['certificate'];

        Storage::put("zimra/device_certificate.pem", $certificatePem);

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Update Config in Database
        |--------------------------------------------------------------------------
        */
        $zimraConfig->update([
            'device_id' => $deviceId,
            'serial_number' => $serialNumber,
            'activation_key' => $activationKey,
            'private_key' => $privateKeyPem,
            'certificate' => $certificatePem,
        ]);

        return [
            "message" => "Device registered successfully",
            "operationID" => $response->json()['operationID']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Config (mTLS)
    |--------------------------------------------------------------------------
    */
    public function getConfig(int $deviceId = null)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $deviceId ?? $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID provided or found in configuration.');
        }

        $baseUrl = $zimraConfig->base_url;

        // Use certificate from database if available, otherwise from file
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            $certPath = storage_path('app/zimra/device_certificate.pem');
            $keyPath = storage_path('app/zimra/device_private.key');

            // Write to temp files for mTLS
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        return Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
        ])->get("{$baseUrl}/Device/v1/{$deviceId}/GetConfig")
          ->json();
    }
}
