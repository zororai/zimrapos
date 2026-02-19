<?php

namespace App\Services;

use App\Models\FiscalDay;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZimraDeviceService
{
    /**
     * Prepare mTLS certificates - validates and writes to storage
     * @throws \Exception if certificates are not found
     */
    private function prepareMtlsCertificates(ZimraConfig $zimraConfig): array
    {
        if (!$zimraConfig->certificate || !$zimraConfig->private_key) {
            throw new \Exception('Device certificates not found. Please register the device or upload certificates first.');
        }

        // Ensure directory exists
        $zimraDir = storage_path('app/zimra');
        if (!is_dir($zimraDir)) {
            mkdir($zimraDir, 0755, true);
        }

        // Write certificates directly to files (bypass Storage facade for reliability)
        $certPath = $zimraDir . DIRECTORY_SEPARATOR . 'device_certificate.pem';
        $keyPath = $zimraDir . DIRECTORY_SEPARATOR . 'device_private.key';

        file_put_contents($certPath, $zimraConfig->certificate);
        file_put_contents($keyPath, $zimraConfig->private_key);

        // Verify files were created
        if (!file_exists($certPath)) {
            throw new \Exception('Failed to write certificate file to: ' . $certPath);
        }
        if (!file_exists($keyPath)) {
            throw new \Exception('Failed to write private key file to: ' . $keyPath);
        }

        Log::info('mTLS certificates written', ['cert' => $certPath, 'key' => $keyPath]);

        return [
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ];
    }

    /**
     * Sign data for ZIMRA (SHA256 hash + ECDSA signature)
     */
    private function signData(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('Device private key not found. Please register device first.');
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing.');
        }

        openssl_sign($hashBinary, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signatureBinary);

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

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
        // Find OpenSSL config file for Windows compatibility
        $opensslConf = $this->findOpenSSLConfig();
        
        Log::info('Starting device registration', [
            'device_id' => $deviceId,
            'serial_number' => $serialNumber,
            'openssl_conf' => $opensslConf,
            'php_binary' => PHP_BINARY
        ]);
        
        $config = [
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name" => "prime256v1",
        ];
        
        if ($opensslConf) {
            $config["config"] = $opensslConf;
        }

        $privateKeyResource = openssl_pkey_new($config);

        if ($privateKeyResource === false) {
            $errors = [];
            while ($e = openssl_error_string()) {
                $errors[] = $e;
            }
            Log::error('OpenSSL key generation failed', ['errors' => $errors]);
            throw new \Exception('Failed to generate private key. OpenSSL errors: ' . implode('; ', $errors));
        }

        if (!openssl_pkey_export($privateKeyResource, $privateKeyPem, null, $opensslConf ? ['config' => $opensslConf] : [])) {
            $error = openssl_error_string();
            Log::error('OpenSSL key export failed', ['error' => $error]);
            throw new \Exception('Failed to export private key: ' . ($error ?: 'Unknown error'));
        }

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

        $csrConfig = ["digest_alg" => "sha256"];
        if ($opensslConf) {
            $csrConfig["config"] = $opensslConf;
        }

        $csrResource = openssl_csr_new($dn, $privateKeyResource, $csrConfig);

        if ($csrResource === false) {
            $errors = [];
            while ($e = openssl_error_string()) {
                $errors[] = $e;
            }
            Log::error('OpenSSL CSR generation failed', ['errors' => $errors]);
            throw new \Exception('Failed to generate CSR. OpenSSL errors: ' . implode('; ', $errors));
        }

        if (!openssl_csr_export($csrResource, $csrPem)) {
            $error = openssl_error_string();
            Log::error('OpenSSL CSR export failed', ['error' => $error]);
            throw new \Exception('Failed to export CSR: ' . ($error ?: 'Unknown error'));
        }

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

        Log::info('Sending registration request to ZIMRA', [
            'url' => "{$baseUrl}/Public/v1/{$deviceId}/RegisterDevice",
            'csr_length' => strlen($csrForJson)
        ]);

        $response = Http::withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->post("{$baseUrl}/Public/v1/{$deviceId}/RegisterDevice", [
            "certificateRequest" => $csrForJson,
            "activationKey" => $activationKey
        ]);

        Log::info('ZIMRA registration response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body()
        ]);

        if (!$response->successful()) {
            Log::error('ZIMRA registration failed', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
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
    | Upload Existing Certificates (for pre-registered devices)
    |--------------------------------------------------------------------------
    */
    public function uploadCertificates(int $deviceId, string $serialNumber, string $certificate, string $privateKey)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found. Please create one first.');
        }

        // Validate certificate format
        if (strpos($certificate, '-----BEGIN CERTIFICATE-----') === false) {
            throw new \Exception('Invalid certificate format. Must be PEM format.');
        }

        // Validate private key format
        if (strpos($privateKey, '-----BEGIN') === false || strpos($privateKey, 'PRIVATE KEY-----') === false) {
            throw new \Exception('Invalid private key format. Must be PEM format.');
        }

        // Save files to storage
        Storage::put('zimra/device_certificate.pem', $certificate);
        Storage::put('zimra/device_private.key', $privateKey);

        // Update config in database
        $zimraConfig->update([
            'device_id' => $deviceId,
            'serial_number' => $serialNumber,
            'certificate' => $certificate,
            'private_key' => $privateKey,
        ]);

        Log::info('Certificates uploaded for device', ['device_id' => $deviceId, 'serial_number' => $serialNumber]);

        return [
            "message" => "Certificates uploaded successfully",
            "device_id" => $deviceId
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
        $mtls = $this->prepareMtlsCertificates($zimraConfig);

        return Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
        ])->get("{$baseUrl}/Device/v1/{$deviceId}/GetConfig")
          ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Status (mTLS)
    |--------------------------------------------------------------------------
    */
    public function getStatus(int $deviceId = null)
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
        $mtls = $this->prepareMtlsCertificates($zimraConfig);

        $response = Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->get("{$baseUrl}/Device/v1/{$deviceId}/GetStatus");

        if (!$response->successful()) {
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json()
            ];
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Open Fiscal Day (mTLS) - With Database Tracking
    |--------------------------------------------------------------------------
    */
    public function openDay(int $fiscalDayNo = null)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        // Check if there's already an open fiscal day
        $existingOpen = FiscalDay::getCurrentOpen($deviceId);
        if ($existingOpen) {
            return [
                'error' => true,
                'message' => 'Fiscal day ' . $existingOpen->fiscal_day_no . ' is already open. Close it first.',
                'fiscal_day' => $existingOpen
            ];
        }

        // Auto-determine next fiscal day number if not provided
        $fiscalDayNo = $fiscalDayNo ?? FiscalDay::getNextFiscalDayNo($deviceId);

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        $openedAt = now();

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/OpenDay", [
            "fiscalDayOpened" => $openedAt->format('Y-m-d\TH:i:s'),
            "fiscalDayNo" => $fiscalDayNo
        ]);

        if (!$response->successful()) {
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json()
            ];
        }

        $responseData = $response->json();

        // Save to database
        $fiscalDay = FiscalDay::create([
            'fiscal_day_no' => $fiscalDayNo,
            'device_id' => $deviceId,
            'status' => 'open',
            'opened_at' => $openedAt,
            'open_operation_id' => $responseData['operationID'] ?? null,
            'receipt_counter' => 0,
            'fiscal_counters' => [],
        ]);

        return [
            'success' => true,
            'data' => $responseData,
            'fiscal_day' => $fiscalDay
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Check if Fiscal Day is Open
    |--------------------------------------------------------------------------
    */
    public function isFiscalDayOpen(): bool
    {
        $zimraConfig = ZimraConfig::getActive();
        if (!$zimraConfig || !$zimraConfig->device_id) {
            return false;
        }

        return FiscalDay::getCurrentOpen($zimraConfig->device_id) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Current Open Fiscal Day
    |--------------------------------------------------------------------------
    */
    public function getCurrentFiscalDay(): ?FiscalDay
    {
        $zimraConfig = ZimraConfig::getActive();
        if (!$zimraConfig || !$zimraConfig->device_id) {
            return null;
        }

        return FiscalDay::getCurrentOpen($zimraConfig->device_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Ping Device (mTLS) - Background Heartbeat
    |--------------------------------------------------------------------------
    */
    public function ping(): bool
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            Log::error('ZIMRA Ping Failed: No active configuration found.');
            return false;
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            Log::error('ZIMRA Ping Failed: No device ID found in configuration.');
            return false;
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/Ping", []);

        if (!$response->successful()) {
            Log::error('ZIMRA Ping Failed', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return false;
        }

        Log::info('ZIMRA Ping Successful', $response->json() ?? []);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Close Fiscal Day (mTLS) - With Database Tracking
    |--------------------------------------------------------------------------
    */
    public function closeDay(array $payload = null)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        // Get current open fiscal day
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        if (!$fiscalDay) {
            return [
                'error' => true,
                'message' => 'No open fiscal day found to close.'
            ];
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        // Build payload from database if not provided
        if (!$payload) {
            $payload = [
                'fiscalDayNo' => $fiscalDay->fiscal_day_no,
                'fiscalDayCounters' => $fiscalDay->fiscal_counters ?? [],
                'receiptCounter' => $fiscalDay->receipt_counter ?? 0,
            ];
        }

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/CloseDay", $payload);

        if (!$response->successful()) {
            Log::error('ZIMRA CloseDay Failed', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json()
            ];
        }

        $responseData = $response->json();

        // Update database
        $fiscalDay->update([
            'status' => 'closed',
            'closed_at' => now(),
            'close_operation_id' => $responseData['operationID'] ?? null,
            'close_response' => $responseData,
        ]);

        Log::info('ZIMRA Fiscal Day Closed', [
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'operation_id' => $responseData['operationID'] ?? null
        ]);

        return [
            'success' => true,
            'data' => $responseData,
            'fiscal_day' => $fiscalDay->fresh()
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Auto Close Fiscal Day (called by scheduler)
    |--------------------------------------------------------------------------
    */
    public function autoCloseDay(): array
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig || !$zimraConfig->device_id) {
            return ['skipped' => true, 'reason' => 'No active config'];
        }

        $fiscalDay = FiscalDay::getCurrentOpen($zimraConfig->device_id);

        if (!$fiscalDay) {
            return ['skipped' => true, 'reason' => 'No open fiscal day'];
        }

        // Check if fiscal day was opened today - only auto-close at end of day
        if ($fiscalDay->opened_at->isToday()) {
            // Only auto-close if it's past closing time (e.g., 11 PM)
            if (now()->hour < 23) {
                return ['skipped' => true, 'reason' => 'Not yet closing time'];
            }
        }

        return $this->closeDay();
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Receipt (mTLS + Signing)
    |--------------------------------------------------------------------------
    */
    public function submitReceipt(array $receiptData)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        // Check fiscal day is open
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        if (!$fiscalDay) {
            return [
                'error' => true,
                'message' => 'No open fiscal day. Open a fiscal day before submitting receipts.'
            ];
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Generate Device Signature
        |--------------------------------------------------------------------------
        */

        // Remove existing signature if present
        unset($receiptData['receiptDeviceSignature']);

        // Set fiscal day number from current open day
        $receiptData['fiscalDayNo'] = $fiscalDay->fiscal_day_no;

        $receiptJson = json_encode($receiptData, JSON_UNESCAPED_SLASHES);

        // Generate SHA256 hash (binary)
        $hashBinary = hash('sha256', $receiptJson, true);

        // Base64 encoded hash
        $hashBase64 = base64_encode($hashBinary);

        // Load private key
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing.');
        }

        // Sign hash with ECDSA
        openssl_sign($hashBinary, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureBase64 = base64_encode($signatureBinary);

        // Add signature to receipt
        $receiptData['receiptDeviceSignature'] = [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Send To ZIMRA
        |--------------------------------------------------------------------------
        */

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/SubmitReceipt", [
            'receipt' => $receiptData
        ]);

        if (!$response->successful()) {
            Log::error('ZIMRA SubmitReceipt Failed', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json()
            ];
        }

        $responseData = $response->json();

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Update Fiscal Day Counters
        |--------------------------------------------------------------------------
        */
        $this->updateFiscalCounters($fiscalDay, $receiptData);

        Log::info('ZIMRA Receipt Submitted', [
            'receipt_id' => $responseData['receiptID'] ?? null,
            'fiscal_day_no' => $fiscalDay->fiscal_day_no
        ]);

        return [
            'success' => true,
            'data' => $responseData,
            'fiscal_day_no' => $fiscalDay->fiscal_day_no
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Update Fiscal Counters After Receipt
    |--------------------------------------------------------------------------
    */
    private function updateFiscalCounters(FiscalDay $fiscalDay, array $receiptData): void
    {
        // Increment receipt counter
        $fiscalDay->increment('receipt_counter');

        // Update fiscal counters from receipt taxes
        $counters = $fiscalDay->fiscal_counters ?? [];

        if (isset($receiptData['receiptTaxes']) && is_array($receiptData['receiptTaxes'])) {
            foreach ($receiptData['receiptTaxes'] as $tax) {
                $key = $tax['taxCode'] . '_' . ($tax['taxPercent'] ?? 0);

                if (!isset($counters[$key])) {
                    $counters[$key] = [
                        'fiscalCounterType' => 'saleByTax',
                        'fiscalCounterCurrency' => $receiptData['receiptCurrency'] ?? 'USD',
                        'fiscalCounterTaxPercent' => $tax['taxPercent'] ?? 0,
                        'fiscalCounterTaxID' => $tax['taxID'] ?? 1,
                        'fiscalCounterMoneyType' => 'Cash',
                        'fiscalCounterValue' => 0,
                    ];
                }

                $counters[$key]['fiscalCounterValue'] += $tax['salesAmountWithTax'] ?? 0;
            }
        }

        $fiscalDay->update(['fiscal_counters' => array_values($counters)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit File (mTLS + text/plain)
    |--------------------------------------------------------------------------
    */
    public function submitFile(array $payload)
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        // Ensure deviceId inside header matches URL
        if (isset($payload['header'])) {
            $payload['header']['deviceId'] = $deviceId;
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Sign Each Receipt
        |--------------------------------------------------------------------------
        */
        if (isset($payload['content']['receipts']) && is_array($payload['content']['receipts'])) {
            foreach ($payload['content']['receipts'] as &$receipt) {
                unset($receipt['receiptDeviceSignature']);
                $receipt['receiptDeviceSignature'] = $this->signData($receipt);
            }
            unset($receipt);
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Sign Footer (Header + Content combined)
        |--------------------------------------------------------------------------
        */
        if (isset($payload['footer'])) {
            $footerData = [
                'header' => $payload['header'],
                'content' => $payload['content']
            ];
            $payload['footer']['fiscalDayDeviceSignature'] = $this->signData($footerData);
        }

        // Convert JSON to raw string
        $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        
        Log::info('Submitting file to ZIMRA', [
            'url' => "{$baseUrl}/Device/v1/{$deviceId}/SubmitFile",
            'payload_size' => strlen($rawJson)
        ]);

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'text/plain',
        ])->withBody($rawJson, 'text/plain')
          ->post("{$baseUrl}/Device/v1/{$deviceId}/SubmitFile");

        if (!$response->successful()) {
            Log::error('ZIMRA SubmitFile Failed', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $response->json()
            ];
        }

        Log::info('ZIMRA File Submitted', $response->json() ?? []);

        return $response->json();
    }

    /**
     * Find OpenSSL configuration file for Windows compatibility
     */
    private function findOpenSSLConfig(): ?string
    {
        // Check environment variable first
        if ($conf = getenv('OPENSSL_CONF')) {
            if (file_exists($conf)) {
                return $conf;
            }
        }

        // Common locations for OpenSSL config on Windows (Herd, XAMPP, etc.)
        $userProfile = getenv('USERPROFILE') ?: 'C:/Users/' . get_current_user();
        $phpDir = dirname(PHP_BINARY);
        
        $possiblePaths = [
            // Herd locations (various possible paths)
            $userProfile . '/.config/herd/bin/openssl.cnf',
            $userProfile . '/AppData/Local/Herd/bin/openssl.cnf',
            $userProfile . '/AppData/Roaming/Herd/bin/openssl.cnf',
            'C:/Program Files/Herd/resources/bin/openssl.cnf',
            'C:/Program Files/Herd/bin/openssl.cnf',
            getenv('HERD_HOME') . '/bin/openssl.cnf',
            // PHP binary directory (Herd embeds PHP)
            $phpDir . '/extras/ssl/openssl.cnf',
            $phpDir . '/ssl/openssl.cnf',
            $phpDir . '/../ssl/openssl.cnf',
            $phpDir . '/extras/openssl/openssl.cnf',
            // XAMPP
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            // Git for Windows
            'C:/Program Files/Git/usr/ssl/openssl.cnf',
            'C:/Program Files/Git/mingw64/ssl/openssl.cnf',
            // Standard Windows locations
            'C:/OpenSSL-Win64/bin/openssl.cfg',
            'C:/OpenSSL/bin/openssl.cfg',
        ];
        
        Log::debug('Looking for OpenSSL config', ['php_binary' => PHP_BINARY, 'php_dir' => $phpDir]);

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        // Try to find via PHP's openssl extension
        $opensslDir = null;
        if (defined('OPENSSL_VERSION_TEXT')) {
            // Check common relative paths from PHP
            $phpDir = dirname(PHP_BINARY);
            $checkPaths = [
                $phpDir . '/extras/ssl/openssl.cnf',
                $phpDir . '/../ssl/openssl.cnf',
                $phpDir . '/ssl/openssl.cnf',
            ];
            foreach ($checkPaths as $path) {
                if (file_exists($path)) {
                    return realpath($path);
                }
            }
        }

        return null;
    }
}
