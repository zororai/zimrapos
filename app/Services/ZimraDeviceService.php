<?php

namespace App\Services;

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\DeviceState;

class ZimraDeviceService
{
    protected ReceiptQrCodeService $qrCodeService;

    public function __construct(ReceiptQrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }
    /**
     * ZIMRA Validation Error Code Descriptions
     * Reference: FDMS Technical Specification
     */
    private const VALIDATION_ERROR_MESSAGES = [
        'RCPT001' => 'Invalid receipt type',
        'RCPT002' => 'Invalid receipt currency',
        'RCPT003' => 'Invalid receipt counter - must be sequential',
        'RCPT004' => 'Invalid receipt global number',
        'RCPT005' => 'Invalid invoice number',
        'RCPT006' => 'Invalid buyer data',
        'RCPT007' => 'Invalid receipt date',
        'RCPT008' => 'Invalid credit/debit note reference',
        'RCPT009' => 'Invalid receipt lines',
        'RCPT010' => 'Invalid receipt line type',
        'RCPT011' => 'Receipt hash mismatch - signature verification failed',
        'RCPT012' => 'Invalid tax code',
        'RCPT013' => 'Invalid tax percent - does not match registered tax rate',
        'RCPT014' => 'Invalid tax ID - not registered for this device',
        'RCPT015' => 'Invalid tax amount calculation',
        'RCPT016' => 'Invalid sales amount with tax',
        'RCPT017' => 'Invalid payment method',
        'RCPT018' => 'Invalid payment amount',
        'RCPT019' => 'Payment total does not match receipt total',
        'RCPT020' => 'Invalid receipt total calculation',
        'RCPT021' => 'Invalid fiscal day number - day not open or mismatch',
        'RCPT022' => 'Invalid receipt print form',
        'RCPT023' => 'Invalid HS code',
        'RCPT024' => 'Invalid receipt line quantity',
        'RCPT025' => 'Invalid receipt device signature',
        'RCPT026' => 'Receipt line total mismatch (price * quantity)',
        'RCPT027' => 'Tax amount sum does not match receipt taxes',
        'RCPT028' => 'Duplicate receipt counter for fiscal day',
        'RCPT029' => 'Fiscal day is closed',
        'RCPT030' => 'Device not authorized',
        'RCPT031' => 'Certificate expired or invalid',
        'RCPT032' => 'Invalid receipt notes',
        'RCPT033' => 'Missing required field',
        'RCPT034' => 'Invalid VAT number format',
        'RCPT035' => 'Invalid TIN format',
    ];

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
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        // DEBUG: Log exact JSON being signed
        Log::debug('ZIMRA signData - JSON to sign', [
            'json' => $json,
            'json_length' => strlen($json),
        ]);

        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        // DEBUG: Log hash
        Log::debug('ZIMRA signData - Hash', [
            'hash_hex' => bin2hex($hashBinary),
            'hash_base64' => $hashBase64,
        ]);

        $privateKeyPath = storage_path('app/zimra/device_private.key');
        $certPath = storage_path('app/zimra/device_certificate.pem');
        
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('Device private key not found. Please register device first.');
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing.');
        }

        // VERIFY: Check if private key matches certificate
        if (file_exists($certPath)) {
            $cert = openssl_x509_read(file_get_contents($certPath));
            if ($cert) {
                $keyMatchesCert = openssl_x509_check_private_key($cert, $privateKey);
                Log::info('ZIMRA signData - Certificate/Key Match Check', [
                    'key_matches_certificate' => $keyMatchesCert,
                ]);
                if (!$keyMatchesCert) {
                    Log::error('ZIMRA signData - PRIVATE KEY DOES NOT MATCH CERTIFICATE! Re-register device required.');
                }
            }
        }

        // DEBUG: Log key details
        $keyDetails = openssl_pkey_get_details($privateKey);
        Log::debug('ZIMRA signData - Key details', [
            'key_type' => $keyDetails['type'] ?? 'unknown',
            'key_bits' => $keyDetails['bits'] ?? 'unknown',
        ]);

        openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signatureBinary);

        // DEBUG: Log signature
        Log::debug('ZIMRA signData - Signature', [
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signatureBinary),
        ]);

        // VERIFY: Local signature verification before sending
        if (file_exists($certPath)) {
            $cert = openssl_x509_read(file_get_contents($certPath));
            if ($cert) {
                $publicKey = openssl_pkey_get_public($cert);
                $verifyResult = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
                Log::info('ZIMRA signData - Local Signature Verification', [
                    'local_signature_valid' => $verifyResult,
                    'verify_meaning' => $verifyResult === 1 ? 'VALID' : ($verifyResult === 0 ? 'INVALID' : 'ERROR'),
                ]);
                if ($verifyResult !== 1) {
                    Log::error('ZIMRA signData - LOCAL SIGNATURE VERIFICATION FAILED!', [
                        'result' => $verifyResult,
                        'openssl_error' => openssl_error_string(),
                    ]);
                }
            }
        }

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

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Prepare Device Identifiers (ZIMRA v7.2 Spec)
        |--------------------------------------------------------------------------
        */
        $paddedId = str_pad($deviceId, 10, '0', STR_PAD_LEFT);
        $commonName = "ZIMRA-{$serialNumber}-{$paddedId}";

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generate ECC P-256 Private Key
        |--------------------------------------------------------------------------
        */
        $opensslConf = $this->findOpenSSLConfig();
        
        Log::info('Starting device registration', [
            'device_id' => $deviceId,
            'padded_device_id' => $paddedId,
            'serial_number' => $serialNumber,
            'common_name' => $commonName,
            'openssl_conf' => $opensslConf,
            'php_binary' => PHP_BINARY
        ]);
        
        $keyConfig = [
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name" => "prime256v1",
        ];
        
        if ($opensslConf) {
            $keyConfig["config"] = $opensslConf;
        }

        $privateKeyResource = openssl_pkey_new($keyConfig);

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
        | 3️⃣ Generate CSR with ZIMRA v7.2 Distinguished Name
        |--------------------------------------------------------------------------
        | DN MUST be exactly:
        |   C  = ZW
        |   ST = Zimbabwe
        |   O  = Zimbabwe Revenue Authority
        |   CN = ZIMRA-{serialNumber}-{zeroPaddedDeviceId}
        |--------------------------------------------------------------------------
        */
        $dn = [
            "countryName" => "ZW",
            "stateOrProvinceName" => "Zimbabwe",
            "organizationName" => "Zimbabwe Revenue Authority",
            "commonName" => $commonName,
        ];

        $csrConfig = [
            "digest_alg" => "sha256",
            "x509_extensions" => "v3_req",
            "req_extensions" => "v3_req",
        ];
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

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Validate CSR Before Sending
        |--------------------------------------------------------------------------
        */
        // Validate PEM format
        if (strpos($csrPem, '-----BEGIN CERTIFICATE REQUEST-----') === false ||
            strpos($csrPem, '-----END CERTIFICATE REQUEST-----') === false) {
            Log::error('CSR PEM format validation failed', ['csr' => $csrPem]);
            throw new \Exception('Generated CSR is not in valid PEM format');
        }

        // Validate and log CSR subject
        $csrSubject = openssl_csr_get_subject($csrResource);
        Log::info('CSR Subject (DN) verification', [
            'C' => $csrSubject['C'] ?? 'MISSING',
            'ST' => $csrSubject['ST'] ?? 'MISSING',
            'O' => $csrSubject['O'] ?? 'MISSING',
            'CN' => $csrSubject['CN'] ?? 'MISSING',
            'full_subject' => $csrSubject
        ]);

        // Verify no default OpenSSL values leaked in
        if (($csrSubject['ST'] ?? '') === 'Some-State') {
            Log::error('CSR contains default OpenSSL ST value', ['subject' => $csrSubject]);
            throw new \Exception('CSR generation failed: ST contains default "Some-State" value');
        }
        if (strpos($csrSubject['O'] ?? '', 'Internet Widgits') !== false) {
            Log::error('CSR contains default OpenSSL O value', ['subject' => $csrSubject]);
            throw new \Exception('CSR generation failed: O contains default "Internet Widgits" value');
        }

        // Verify expected values
        if (($csrSubject['C'] ?? '') !== 'ZW') {
            throw new \Exception('CSR validation failed: C must be ZW, got: ' . ($csrSubject['C'] ?? 'empty'));
        }
        if (($csrSubject['ST'] ?? '') !== 'Zimbabwe') {
            throw new \Exception('CSR validation failed: ST must be Zimbabwe, got: ' . ($csrSubject['ST'] ?? 'empty'));
        }
        if (($csrSubject['O'] ?? '') !== 'Zimbabwe Revenue Authority') {
            throw new \Exception('CSR validation failed: O must be Zimbabwe Revenue Authority, got: ' . ($csrSubject['O'] ?? 'empty'));
        }
        if (($csrSubject['CN'] ?? '') !== $commonName) {
            throw new \Exception('CSR validation failed: CN must be ' . $commonName . ', got: ' . ($csrSubject['CN'] ?? 'empty'));
        }

        Storage::put("zimra/device.csr", $csrPem);
        Log::info('CSR generated and validated successfully', ['csr_length' => strlen($csrPem)]);

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Register Device with ZIMRA
        |--------------------------------------------------------------------------
        */
        $baseUrl = $zimraConfig->base_url;

        Log::info('Sending registration request to ZIMRA', [
            'url' => "{$baseUrl}/Public/v1/{$deviceId}/RegisterDevice",
            'csr_length' => strlen($csrPem)
        ]);

        $response = Http::withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->post("{$baseUrl}/Public/v1/{$deviceId}/RegisterDevice", [
            "certificateRequest" => trim($csrPem),
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
            
            $errorBody = $response->json();
            $errorMessage = $errorBody['detail'] ?? $errorBody['title'] ?? 'Registration failed';
            $errorCode = $errorBody['errorCode'] ?? 'UNKNOWN';
            
            return [
                'error' => true,
                'message' => $errorMessage,
                'errorCode' => $errorCode,
                'details' => $errorBody
            ];
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

        $response = Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
        ])->get("{$baseUrl}/Device/v1/{$deviceId}/GetConfig")
          ->json();

        // Store important config data from ZIMRA response
        if ($response && !isset($response['error'])) {
            // CRITICAL: Field is 'applicableTaxes' NOT 'taxes' per FDMS spec
            $zimraConfig->update([
                'qr_url' => $response['qrUrl'] ?? null,
                'taxes' => $response['applicableTaxes'] ?? null,
                'device_operating_mode' => $response['deviceOperatingMode'] ?? null,
                'certificate_valid_till' => isset($response['certificateValidTill']) 
                    ? \Carbon\Carbon::parse($response['certificateValidTill']) 
                    : null,
            ]);
            
            Log::info('ZIMRA GetConfig Response', [
                'qr_url' => $response['qrUrl'] ?? null,
                'vatNumber' => $response['vatNumber'] ?? 'NOT_REGISTERED',
                'deviceOperatingMode' => $response['deviceOperatingMode'] ?? null,
                'applicableTaxes' => $response['applicableTaxes'] ?? 'NOT_FOUND',
            ]);
        }

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Status (mTLS)
    |--------------------------------------------------------------------------
    */
    public function getStatus(int $deviceId = null)
    {
        // CRITICAL: device_id MUST be provided
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required for getStatus(). ' .
                'Device ID must be provided by caller.'
            );
        }

        // Load config for THIS specific device
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();

        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
        }

        $baseUrl = $zimraConfig->base_url;
        $mtls = $this->prepareMtlsCertificates($zimraConfig);

        $response = Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
        ])->get("{$baseUrl}/Device/v1/{$deviceId}/GetStatus");

        if (!$response->successful()) {
            // Safely parse response - might be HTML error page instead of JSON
            try {
                $body = $response->json();
            } catch (\Exception $e) {
                $body = [
                    'error' => 'Invalid JSON response from server',
                    'raw_response' => $response->body(),
                    'content_type' => $response->header('Content-Type')
                ];
            }
            
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $body
            ];
        }

        // Safely parse successful response
        try {
            return $response->json();
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Invalid JSON response from server: ' . $e->getMessage(),
                'raw_response' => $response->body(),
                'content_type' => $response->header('Content-Type')
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Open Fiscal Day (mTLS) - With Database Tracking
    |--------------------------------------------------------------------------
    */
    public function openDay(int $fiscalDayNo = null, int $deviceId = null)
    {
        // CRITICAL: device_id MUST be provided
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required for openDay(). ' .
                'Device ID must be provided by ResolveCompanyDevice middleware.'
            );
        }

        // Load config for THIS specific device
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();

        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
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
        $mtls = $this->prepareMtlsCertificates($zimraConfig);

        $openedAt = now();

        $response = Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/OpenDay", [
            "fiscalDayOpened" => $openedAt->format('Y-m-d\TH:i:s'),
            "fiscalDayNo" => $fiscalDayNo
        ]);

        if (!$response->successful()) {
            // Safely parse response - might be HTML error page instead of JSON
            try {
                $body = $response->json();
            } catch (\Exception $e) {
                $body = [
                    'error' => 'Invalid JSON response from server',
                    'raw_response' => $response->body(),
                    'content_type' => $response->header('Content-Type')
                ];
            }
            
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $body
            ];
        }

        // Safely parse successful response
        try {
            $responseData = $response->json();
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Invalid JSON response from server: ' . $e->getMessage(),
                'raw_response' => $response->body(),
                'content_type' => $response->header('Content-Type')
            ];
        }

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
    | Get Current Open Fiscal Day (with ZIMRA sync)
    |--------------------------------------------------------------------------
    */
    public function getCurrentFiscalDay(): ?FiscalDay
    {
        $zimraConfig = ZimraConfig::getActive();
        if (!$zimraConfig || !$zimraConfig->device_id) {
            return null;
        }

        $deviceId = $zimraConfig->device_id;

        // First check local database
        $localFiscalDay = FiscalDay::getCurrentOpen($deviceId);
        if ($localFiscalDay) {
            return $localFiscalDay;
        }

        // If no local record, check ZIMRA status and sync if needed
        try {
            Log::info('Checking ZIMRA status for fiscal day sync', ['device_id' => $deviceId]);
            $status = $this->getStatus($deviceId);
            Log::info('ZIMRA GetStatus response', ['status' => $status]);
            
            // Check if there's an error in the response
            if (isset($status['error']) && $status['error']) {
                Log::warning('ZIMRA GetStatus returned error', ['error' => $status]);
                return null;
            }
            
            if (isset($status['fiscalDayStatus']) && $status['fiscalDayStatus'] === 'FiscalDayOpened') {
                // ZIMRA says day is open but we don't have a local record - create one
                $fiscalDayNo = $status['lastFiscalDayNo'] ?? 1;
                
                Log::info('Creating local fiscal day from ZIMRA sync', ['fiscal_day_no' => $fiscalDayNo, 'device_id' => $deviceId]);
                
                $fiscalDay = FiscalDay::create([
                    'fiscal_day_no' => $fiscalDayNo,
                    'device_id' => $deviceId,
                    'status' => 'open',
                    'opened_at' => now(),
                    'receipt_counter' => 0,
                    'fiscal_counters' => [],
                ]);
                
                Log::info('Local fiscal day created', ['id' => $fiscalDay->id]);
                
                return $fiscalDay;
            } else {
                Log::info('ZIMRA fiscal day status is not FiscalDayOpened', ['status' => $status['fiscalDayStatus'] ?? 'unknown']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync fiscal day from ZIMRA', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return null;
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

        try {
            $mtls = $this->prepareMtlsCertificates($zimraConfig);
        } catch (\Exception $e) {
            Log::error('ZIMRA Ping Failed: ' . $e->getMessage());
            return false;
        }

        $response = Http::withOptions($mtls)->withHeaders([
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
    public function closeDay(array $payload = null, int $deviceId = null)
    {
        // CRITICAL: device_id MUST be provided
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required for closeDay(). ' .
                'Device ID must be provided by ResolveCompanyDevice middleware.'
            );
        }

        // Load config for THIS specific device
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();

        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
        }

        // Get current open fiscal day OR last fiscal day that needs retry
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        
        // If no open day, check ZIMRA status
        if (!$fiscalDay) {
            $zimraStatus = $this->getStatus($deviceId);
            $status = $zimraStatus['fiscalDayStatus'] ?? null;
            
            // FiscalDayCloseInitiated = close is in progress, do NOT retry
            if ($status === 'FiscalDayCloseInitiated') {
                Log::info('ZIMRA CloseDay - Close already in progress, waiting for completion', [
                    'status' => $status,
                    'last_fiscal_day_no' => $zimraStatus['lastFiscalDayNo'] ?? 0,
                ]);
                return [
                    'success' => false,
                    'status' => 'pending',
                    'message' => 'Fiscal day close is in progress. Please wait and check status again.',
                    'zimra_status' => $zimraStatus,
                ];
            }
            
            // FiscalDayClosed = already closed, nothing to do
            if ($status === 'FiscalDayClosed') {
                Log::info('ZIMRA CloseDay - Day already closed', [
                    'status' => $status,
                    'last_fiscal_day_no' => $zimraStatus['lastFiscalDayNo'] ?? 0,
                ]);
                return [
                    'success' => true,
                    'message' => 'Fiscal day is already closed.',
                    'zimra_status' => $zimraStatus,
                ];
            }
            
            // FiscalDayCloseFailed = retry needed
            if ($status === 'FiscalDayCloseFailed') {
                // Find the fiscal day that failed to close
                $fiscalDay = FiscalDay::where('device_id', $deviceId)
                    ->where('fiscal_day_no', $zimraStatus['lastFiscalDayNo'] ?? 0)
                    ->first();
                
                if ($fiscalDay) {
                    Log::info('ZIMRA CloseDay - Retrying failed close', [
                        'fiscal_day_no' => $fiscalDay->fiscal_day_no,
                        'zimra_status' => $status,
                        'error_code' => $zimraStatus['fiscalDayClosingErrorCode'] ?? 'unknown',
                    ]);
                }
            }
        }
        
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

        // Build payload dynamically from stored receipts
        if (!$payload) {
            $payload = $this->buildCloseDayPayload($fiscalDay, $deviceId);
        }

        // DEBUG: Log payload before signing
        Log::debug('ZIMRA CloseDay v7.2 - Payload before signing', [
            'payload' => $payload,
        ]);

        // Extract fiscalDayDate for canonical string (YYYY-MM-DD when day was opened)
        $fiscalDayDate = $payload['_fiscalDayDate'];
        unset($payload['_fiscalDayDate']); // Remove internal field before signing

        // Build canonical string for signature (per FDMS spec 13.3)
        $canonicalString = $this->buildCloseDayCanonicalString($payload, $deviceId, $fiscalDayDate);
        
        // Sign the canonical string - required by ZIMRA v7.2
        $payload['fiscalDayDeviceSignature'] = $this->signCanonicalString($canonicalString);

        // Validate payload before sending (v7.2 compliance check)
        $validation = \App\Services\CloseDayValidator::validate($payload, $fiscalDay, $deviceId);
        if (!$validation['valid']) {
            Log::error('ZIMRA CloseDay v7.2 - Validation failed', [
                'errors' => $validation['errors'],
            ]);
            return [
                'error' => true,
                'message' => 'CloseDay validation failed',
                'validation_errors' => $validation['errors'],
            ];
        }

        // DEBUG: Log final payload being sent
        Log::debug('ZIMRA CloseDay - Final payload', [
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
        ]);

        // DEBUG: Verify certificate and key match
        $certPath = storage_path('app/zimra/device_certificate.pem');
        $keyPath = storage_path('app/zimra/device_private.key');
        if (file_exists($certPath) && file_exists($keyPath)) {
            $cert = openssl_x509_read(file_get_contents($certPath));
            $key = openssl_pkey_get_private(file_get_contents($keyPath));
            $certPubKey = openssl_pkey_get_public($cert);
            $certDetails = openssl_pkey_get_details($certPubKey);
            $keyDetails = openssl_pkey_get_details($key);
            Log::debug('ZIMRA CloseDay - Certificate/Key verification', [
                'cert_key_type' => $certDetails['type'] ?? 'unknown',
                'priv_key_type' => $keyDetails['type'] ?? 'unknown',
                'keys_match' => ($certDetails['key'] ?? '') === ($keyDetails['key'] ?? ''),
            ]);
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
            // Safely parse response - might be HTML error page instead of JSON
            try {
                $body = $response->json();
            } catch (\Exception $e) {
                $body = [
                    'error' => 'Invalid JSON response from server',
                    'raw_response' => $response->body(),
                    'content_type' => $response->header('Content-Type')
                ];
            }
            
            Log::error('ZIMRA CloseDay Failed', [
                'status' => $response->status(),
                'body' => $body
            ]);
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $body
            ];
        }

        // Safely parse successful response
        try {
            $responseData = $response->json();
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Invalid JSON response from server: ' . $e->getMessage(),
                'raw_response' => $response->body(),
                'content_type' => $response->header('Content-Type')
            ];
        }

        Log::info('ZIMRA CloseDay - Request accepted, polling for completion', [
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'operation_id' => $responseData['operationID'] ?? null
        ]);

        // Poll getStatus until FiscalDayClosed or FiscalDayCloseFailed (max 5 attempts)
        $maxAttempts = 5;
        $pollDelaySeconds = 3;
        $finalStatus = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            sleep($pollDelaySeconds);
            
            $statusResponse = $this->getStatus($deviceId);
            $currentStatus = $statusResponse['fiscalDayStatus'] ?? null;
            
            Log::info('ZIMRA CloseDay - Polling status', [
                'attempt' => $attempt,
                'status' => $currentStatus,
            ]);

            if ($currentStatus === 'FiscalDayClosed') {
                $finalStatus = 'closed';
                break;
            }

            if ($currentStatus === 'FiscalDayCloseFailed') {
                $finalStatus = 'failed';
                Log::error('ZIMRA CloseDay - Close failed after polling', [
                    'error_code' => $statusResponse['fiscalDayClosingErrorCode'] ?? 'unknown',
                    'fiscal_day_no' => $fiscalDay->fiscal_day_no,
                ]);
                
                // CRITICAL: Do NOT update local status to 'closed' when FDMS reports failure
                // Keep status as 'open' to allow retry
                return [
                    'error' => true,
                    'message' => 'Fiscal day close failed: ' . ($statusResponse['fiscalDayClosingErrorCode'] ?? 'unknown'),
                    'body' => $statusResponse,
                ];
            }

            // FiscalDayCloseInitiated - continue polling
            if ($currentStatus !== 'FiscalDayCloseInitiated') {
                Log::warning('ZIMRA CloseDay - Unexpected status during polling', [
                    'status' => $currentStatus,
                ]);
            }
        }

        // CRITICAL: Only update database to 'closed' if FDMS confirmed success
        if ($finalStatus !== 'closed') {
            Log::error('ZIMRA CloseDay - Polling timeout without confirmation', [
                'fiscal_day_no' => $fiscalDay->fiscal_day_no,
                'final_status' => $finalStatus,
                'attempts' => $maxAttempts,
            ]);
            
            // Keep status as 'open' to allow retry
            return [
                'error' => true,
                'message' => 'CloseDay polling timeout. FDMS did not confirm closure. Status remains open for retry.',
                'body' => ['detail' => 'Polling timeout after ' . $maxAttempts . ' attempts'],
            ];
        }

        // Update database only when FDMS confirms FiscalDayClosed
        $fiscalDay->update([
            'status' => 'closed',
            'closed_at' => now(),
            'close_operation_id' => $responseData['operationID'] ?? null,
            'close_response' => $responseData,
        ]);

        Log::info('ZIMRA Fiscal Day Closed', [
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'operation_id' => $responseData['operationID'] ?? null,
            'final_status' => $finalStatus,
        ]);

        return [
            'success' => true,
            'data' => $responseData,
            'fiscal_day' => $fiscalDay->fresh()
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build CloseDay Payload from Stored Receipts (v7.2 Compliant)
    |--------------------------------------------------------------------------
    | ZIMRA Fiscal Device Gateway API v7.2 Specification
    | 
    | Payload Structure:
    | - deviceID: int (included in payload, not just URL)
    | - fiscalDayNo: int
    | - fiscalCounters: array (NOT fiscalDayCounters)
    | - fiscalDayDeviceSignature: {hash, signature} object
    | - receiptCounter: int (max receipt counter, NOT count)
    | - fiscalDayClosed: ISO datetime string
    |--------------------------------------------------------------------------
    */
    private function buildCloseDayPayload(FiscalDay $fiscalDay, int $deviceId): array
    {
        // Get all valid receipts for this fiscal day
        $receipts = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->get();

        Log::info('CloseDay v7.2 - Building payload from receipts', [
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'receipt_count' => $receipts->count(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check for Red or Gray errors - Block CloseDay if found
        |--------------------------------------------------------------------------
        */
        $receiptsWithRedErrors = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('has_red_errors', true)
            ->get();

        $receiptsWithGrayErrors = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('has_gray_errors', true)
            ->get();

        if ($receiptsWithRedErrors->isNotEmpty()) {
            $errorIds = $receiptsWithRedErrors->pluck('id')->implode(', ');
            Log::error('CloseDay - Blocked due to RED validation errors', [
                'fiscal_day_no' => $fiscalDay->fiscal_day_no,
                'receipt_ids_with_red_errors' => $errorIds,
            ]);
            throw new \Exception(
                "Cannot close fiscal day: {$receiptsWithRedErrors->count()} receipt(s) have RED validation errors. " .
                "Receipt IDs: {$errorIds}"
            );
        }

        if ($receiptsWithGrayErrors->isNotEmpty()) {
            $errorIds = $receiptsWithGrayErrors->pluck('id')->implode(', ');
            Log::error('CloseDay - Blocked due to GRAY validation errors', [
                'fiscal_day_no' => $fiscalDay->fiscal_day_no,
                'receipt_ids_with_gray_errors' => $errorIds,
            ]);
            throw new \Exception(
                "Cannot close fiscal day: {$receiptsWithGrayErrors->count()} receipt(s) have GRAY validation errors. " .
                "Receipt IDs: {$errorIds}"
            );
        }

        if ($receipts->isEmpty()) {
            Log::warning('CloseDay - No valid receipts found for fiscal day', [
                'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Calculate receiptCounter (v7.2 SPEC: max receipt counter)
        |--------------------------------------------------------------------------
        | CRITICAL: receiptCounter MUST be the receiptCounter of the LAST receipt
        | in the fiscal day, NOT the count of receipts.
        | Use max(receipt_counter) from database.
        |--------------------------------------------------------------------------
        */
        $receiptCounter = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->max('receipt_counter') ?? 0;

        Log::debug('CloseDay v7.2 - Receipt counter (max)', [
            'receipt_counter' => $receiptCounter,
            'receipt_count' => $receipts->count(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Calculate fiscalCounters from receipts (FDMS Reconciliation Logic)
        |--------------------------------------------------------------------------
        | ZIMRA FDMS derives THREE counter types from submitted fiscal invoices:
        | Per Section 5.4.4 - Valid FiscalCounterType enum values:
        |
        | A) SaleByTax - Group by: currency, taxID, taxPercent
        |    Value: salesAmountWithTax from receiptTaxes
        |
        | B) SaleTaxByTax - Group by: currency, taxID, taxPercent
        |    Value: taxAmount from receiptTaxes
        |
        | C) BalanceByMoneyType - Group by: currency, moneyType
        |    Value: paymentAmount from receiptPayments
        |
        | CRITICAL: Use integer cents internally to avoid float drift
        | Convert back to 2-decimal format only when building payload
        |
        | NOTE: SaleByMoneyType and SalesTotal are NOT valid enum values!
        |--------------------------------------------------------------------------
        */
        $counters = [];
        $totalReceiptValueCents = 0;

        foreach ($receipts as $receipt) {
            $receiptTaxes = $receipt->receipt_taxes ?? [];
            $receiptPayments = $receipt->receipt_payments ?? [];
            $receiptTotal = (float) $receipt->receipt_total;
            $receiptType = $receipt->receipt_type ?? 'FiscalInvoice';
            $currency = $receipt->receipt_currency ?? 'USD';

            // Convert to cents for integer-safe arithmetic
            $receiptTotalCents = (int) round($receiptTotal * 100);
            $totalReceiptValueCents += $receiptTotalCents;

            /*
            |----------------------------------------------------------------------
            | A) SaleByTax / CreditNoteByTax / DebitNoteByTax Counters
            |----------------------------------------------------------------------
            | Per FDMS spec Section 6:
            | - FiscalInvoice → SaleByTax
            | - CreditNote → CreditNoteByTax (with negative values)
            | - DebitNote → DebitNoteByTax
            | 
            | Group by: currency, taxID, taxPercent
            | Value: salesAmountWithTax
            |----------------------------------------------------------------------
            */
            // Determine counter type based on receipt type
            $salesCounterType = 'SaleByTax';
            if ($receiptType === 'CreditNote') {
                $salesCounterType = 'CreditNoteByTax';
            } elseif ($receiptType === 'DebitNote') {
                $salesCounterType = 'DebitNoteByTax';
            }

            foreach ($receiptTaxes as $tax) {
                $taxPercent = (float) ($tax['taxPercent'] ?? 0);
                $taxID = (int) ($tax['taxID'] ?? 1);
                $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);
                $salesAmountCents = (int) round($salesAmountWithTax * 100);

                // Skip if exactly zero (not negative)
                if ($salesAmountCents == 0) {
                    continue;
                }

                // Create unique key: Type_Currency_TaxID_TaxPercent
                $taxKey = "{$salesCounterType}_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
                
                if (!isset($counters[$taxKey])) {
                    $counters[$taxKey] = [
                        'fiscalCounterType' => $salesCounterType,
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterTaxPercent' => $taxPercent,
                        'fiscalCounterTaxID' => $taxID,
                        'fiscalCounterValueCents' => 0,
                    ];
                }
                $counters[$taxKey]['fiscalCounterValueCents'] += $salesAmountCents;
            }

            /*
            |----------------------------------------------------------------------
            | B) SaleTaxByTax / CreditNoteTaxByTax / DebitNoteTaxByTax Counters
            |----------------------------------------------------------------------
            | Per FDMS spec Section 6:
            | - FiscalInvoice → SaleTaxByTax
            | - CreditNote → CreditNoteTaxByTax (with negative values)
            | - DebitNote → DebitNoteTaxByTax
            | 
            | Group by: currency, taxID, taxPercent
            | Value: taxAmount from receiptTaxes
            |----------------------------------------------------------------------
            */
            // Determine counter type based on receipt type
            $taxCounterType = 'SaleTaxByTax';
            if ($receiptType === 'CreditNote') {
                $taxCounterType = 'CreditNoteTaxByTax';
            } elseif ($receiptType === 'DebitNote') {
                $taxCounterType = 'DebitNoteTaxByTax';
            }

            foreach ($receiptTaxes as $tax) {
                $taxPercent = (float) ($tax['taxPercent'] ?? 0);
                $taxID = (int) ($tax['taxID'] ?? 1);
                $taxAmount = (float) ($tax['taxAmount'] ?? 0);
                $taxAmountCents = (int) round($taxAmount * 100);

                // Skip if exactly zero (not negative)
                if ($taxAmountCents == 0) {
                    continue;
                }

                // Create unique key: Type_Currency_TaxID_TaxPercent
                $saleTaxKey = "{$taxCounterType}_{$currency}_{$taxID}_" . number_format($taxPercent, 2, '_', '');
                
                if (!isset($counters[$saleTaxKey])) {
                    $counters[$saleTaxKey] = [
                        'fiscalCounterType' => $taxCounterType,
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterTaxPercent' => $taxPercent,
                        'fiscalCounterTaxID' => $taxID,
                        'fiscalCounterValueCents' => 0,
                    ];
                }
                $counters[$saleTaxKey]['fiscalCounterValueCents'] += $taxAmountCents;
            }

            /*
            |----------------------------------------------------------------------
            | C) BalanceByMoneyType Counters
            |----------------------------------------------------------------------
            | Group by: currency, moneyType
            | Value: paymentAmount from receiptPayments
            | 
            | Per ZIMRA spec Section 5.4.4: BalanceByMoneyType tracks payment balance
            |----------------------------------------------------------------------
            */
            foreach ($receiptPayments as $payment) {
                $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
                $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);
                $paymentAmountCents = (int) round($paymentAmount * 100);

                // CRITICAL: Include credit notes (negative values) in counter aggregation
                // Only skip if exactly zero (not negative)
                if ($paymentAmountCents == 0) {
                    continue;
                }

                $balanceKey = "BalanceByMoneyType_{$currency}_{$moneyType}";
                
                if (!isset($counters[$balanceKey])) {
                    $counters[$balanceKey] = [
                        'fiscalCounterType' => 'BalanceByMoneyType',
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterMoneyType' => $moneyType,
                        'fiscalCounterValueCents' => 0,
                    ];
                }
                $counters[$balanceKey]['fiscalCounterValueCents'] += $paymentAmountCents;
            }
        }

        // Convert cents back to decimal format for payload
        foreach ($counters as &$counter) {
            $counter['fiscalCounterValue'] = round($counter['fiscalCounterValueCents'] / 100, 2);
            unset($counter['fiscalCounterValueCents']); // Remove internal cents field
        }
        unset($counter);

        // Filter out zero-value counters (v7.2 requirement)
        // CRITICAL: Keep negative values (credit notes), only filter exactly zero
        $filteredCounters = array_filter($counters, function ($c) {
            return $c['fiscalCounterValue'] != 0;
        });

        // v7.2 SPEC: Field name is 'fiscalDayCounters' per Section 13.3.1
        $fiscalCounters = array_values($filteredCounters);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Validate Totals (FDMS Reconciliation Check)
        |--------------------------------------------------------------------------
        */
        $totalSalesByTax = 0;
        $totalSaleTaxByTax = 0;
        $totalBalanceByMoneyType = 0;

        foreach ($fiscalCounters as $counter) {
            if (in_array($counter['fiscalCounterType'], ['SaleByTax', 'CreditNoteByTax', 'DebitNoteByTax'])) {
                $totalSalesByTax += $counter['fiscalCounterValue'];
            } elseif (in_array($counter['fiscalCounterType'], ['SaleTaxByTax', 'CreditNoteTaxByTax', 'DebitNoteTaxByTax'])) {
                $totalSaleTaxByTax += $counter['fiscalCounterValue'];
            } elseif ($counter['fiscalCounterType'] === 'BalanceByMoneyType') {
                $totalBalanceByMoneyType += $counter['fiscalCounterValue'];
            }
        }

        $totalSalesByTax = round($totalSalesByTax, 2);
        $totalSaleTaxByTax = round($totalSaleTaxByTax, 2);
        $totalBalanceByMoneyType = round($totalBalanceByMoneyType, 2);
        $totalReceiptValue = round($totalReceiptValueCents / 100, 2);

        Log::info('CloseDay v7.2 - Counter aggregation summary', [
            'receipt_counter' => $receiptCounter,
            'total_receipt_value' => $totalReceiptValue,
            'total_sales_by_tax' => $totalSalesByTax,
            'total_sale_tax_by_tax' => $totalSaleTaxByTax,
            'total_balance_by_money_type' => $totalBalanceByMoneyType,
            'counter_count' => count($fiscalCounters),
        ]);

        // Log each counter for debugging
        foreach ($fiscalCounters as $index => $counter) {
            $logData = [
                'type' => $counter['fiscalCounterType'],
                'currency' => $counter['fiscalCounterCurrency'],
                'value' => $counter['fiscalCounterValue'],
            ];
            
            if (isset($counter['fiscalCounterTaxID'])) {
                $logData['taxID'] = $counter['fiscalCounterTaxID'];
            }
            if (isset($counter['fiscalCounterTaxPercent'])) {
                $logData['taxPercent'] = $counter['fiscalCounterTaxPercent'];
            }
            if (isset($counter['fiscalCounterMoneyType'])) {
                $logData['moneyType'] = $counter['fiscalCounterMoneyType'];
            }
            
            Log::debug("CloseDay v7.2 - Counter #{$index}", $logData);
        }

        // Validate: Counter types should match expectations
        $tolerance = 0.01;
        
        // Validate SaleByTax matches total
        if (abs($totalSalesByTax - $totalReceiptValue) > $tolerance) {
            Log::error('CloseDay v7.2 - SaleByTax mismatch', [
                'total_sales_by_tax' => $totalSalesByTax,
                'total_receipt_value' => $totalReceiptValue,
                'difference' => $totalSalesByTax - $totalReceiptValue,
            ]);
            throw new \Exception(
                "CloseDay validation failed: SaleByTax ({$totalSalesByTax}) != totalReceiptValue ({$totalReceiptValue})"
            );
        }

        // Validate BalanceByMoneyType matches total
        if (abs($totalBalanceByMoneyType - $totalReceiptValue) > $tolerance) {
            Log::error('CloseDay v7.2 - BalanceByMoneyType mismatch', [
                'total_balance_by_money_type' => $totalBalanceByMoneyType,
                'total_receipt_value' => $totalReceiptValue,
                'difference' => $totalBalanceByMoneyType - $totalReceiptValue,
            ]);
            throw new \Exception(
                "CloseDay validation failed: BalanceByMoneyType ({$totalBalanceByMoneyType}) != totalReceiptValue ({$totalReceiptValue})"
            );
        }
        
        // SaleTaxByTax validation: should equal total tax amount (may be 0 for exempt)
        Log::info('CloseDay v7.2 - SaleTaxByTax total', [
            'total_sale_tax_by_tax' => $totalSaleTaxByTax,
        ]);

        // Validate: receiptCounter must be > 0 if receipts exist
        if ($receiptCounter <= 0 && $receipts->isNotEmpty()) {
            Log::error('CloseDay v7.2 - Invalid receipt counter', [
                'receipt_counter' => $receiptCounter,
                'receipt_count' => $receipts->count(),
            ]);
            throw new \Exception(
                "CloseDay validation failed: receiptCounter ({$receiptCounter}) is invalid"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Build v7.2 Compliant Payload Structure
        |--------------------------------------------------------------------------
        | CRITICAL FIELD NAMES (v7.2 SPEC Section 13.3.1):
        | - deviceID (int) - MUST be in payload
        | - fiscalDayNo (int)
        | - fiscalDayCounters (array) - Per API spec
        | - receiptCounter (int) - max receipt counter
        | - fiscalDayClosed (string) - ISO datetime, NOT fiscalDayDate
        |--------------------------------------------------------------------------
        */
        
        // v7.2 SPEC: fiscalDayClosed is the closing timestamp (ISO format)
        // Use current time as the closing time
        $fiscalDayClosed = now()->format('Y-m-d\TH:i:s');

        $payload = [
            'deviceID' => $deviceId,
            'fiscalDayNo' => $fiscalDay->fiscal_day_no,
            'fiscalDayCounters' => $fiscalCounters,
            'receiptCounter' => $receiptCounter,
            'fiscalDayClosed' => $fiscalDayClosed,
        ];

        Log::info('CloseDay v7.2 - Final payload built (without signature)', [
            'device_id' => $payload['deviceID'],
            'fiscal_day_no' => $payload['fiscalDayNo'],
            'fiscal_day_closed' => $payload['fiscalDayClosed'],
            'receipt_counter' => $payload['receiptCounter'],
            'counter_count' => count($payload['fiscalDayCounters']),
        ]);

        // Store fiscalDayDate for canonical string (YYYY-MM-DD when day was opened)
        $fiscalDayDate = $fiscalDay->opened_at->format('Y-m-d');
        $payload['_fiscalDayDate'] = $fiscalDayDate; // Internal use only for signature

        return $payload;
    }

    /*
    |--------------------------------------------------------------------------
    | Build Canonical String for CloseDay Signature (v7.2 Spec)
    |--------------------------------------------------------------------------
    | ZIMRA v7.2 CloseDay Canonical String Format (Section 13.3.1):
    | deviceID || fiscalDayNo || fiscalDayDate || fiscalDayCounters
    | 
    | Field Order (CRITICAL - DO NOT CHANGE):
    | 1. deviceID (int as string)
    | 2. fiscalDayNo (int as string)
    | 3. fiscalDayDate (YYYY-MM-DD - date when fiscal day was OPENED, NOT closed)
    | 4. fiscalDayCounters (concatenated counter strings)
    |
    | CRITICAL: fiscalDayDate is the opening date (YYYY-MM-DD), NOT fiscalDayClosed!
    | The payload contains fiscalDayClosed (closing timestamp), but signature uses
    | fiscalDayDate (opening date) per spec Section 13.3.1
    | 
    | NOTE: receiptCounter is NOT part of the canonical string for signature!
    |--------------------------------------------------------------------------
    */
    private function buildCloseDayCanonicalString(array $payload, int $deviceId, string $fiscalDayDate): string
    {
        $parts = [];
        
        // 1. deviceID
        $parts[] = (string) $deviceId;
        
        // 2. fiscalDayNo
        $parts[] = (string) $payload['fiscalDayNo'];
        
        // 3. fiscalDayDate - YYYY-MM-DD format (when day was OPENED)
        $parts[] = $fiscalDayDate;
        
        // 4. fiscalDayCounters - concatenated string (NO receiptCounter!)
        $countersString = $this->buildCloseDayCountersString($payload['fiscalDayCounters']);
        $parts[] = $countersString;
        
        $canonicalString = implode('', $parts);
        
        Log::info('CloseDay v7.2 - Canonical String Built', [
            'deviceID' => $parts[0],
            'fiscalDayNo' => $parts[1],
            'fiscalDayDate' => $parts[2],
            'fiscalDayCounters_string' => $parts[3],
            'full_canonical_string' => $canonicalString,
            'canonical_length' => strlen($canonicalString),
        ]);
        
        return $canonicalString;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Build Fiscal Day Counters String for Signature
    |--------------------------------------------------------------------------
    | Per FDMS spec: fiscalCounterType || fiscalCounterCurrency || 
    | fiscalCounterTaxPercent or fiscalCounterMoneyType || fiscalCounterValue
    | Sorted by: fiscalCounterType, fiscalCounterCurrency, fiscalCounterTaxID
    */
    private function buildCloseDayCountersString(array $counters): string
    {
        if (empty($counters)) {
            return '';
        }
        
        /*
        |--------------------------------------------------------------------------
        | Deterministic 4-Level Sorting for All Counter Types
        |--------------------------------------------------------------------------
        | Level 1: fiscalCounterType (SaleByTax < SaleByMoneyType < SalesTotal)
        | Level 2: fiscalCounterCurrency (alphabetical)
        | Level 3: fiscalCounterTaxID (if present, ascending) OR fiscalCounterMoneyType (alphabetical)
        | Level 4: fiscalCounterTaxPercent (if present, ascending)
        |--------------------------------------------------------------------------
        */
        usort($counters, function ($a, $b) {
            // Level 1: Sort by fiscalCounterType
            $typeOrder = [
                'SaleByTax' => 1,
                'SaleTaxByTax' => 2,
                'CreditNoteByTax' => 3,
                'CreditNoteTaxByTax' => 4,
                'DebitNoteByTax' => 5,
                'DebitNoteTaxByTax' => 6,
                'BalanceByMoneyType' => 7,
            ];
            $typeCompare = ($typeOrder[$a['fiscalCounterType']] ?? 99) <=> ($typeOrder[$b['fiscalCounterType']] ?? 99);
            if ($typeCompare !== 0) return $typeCompare;
            
            // Level 2: Sort by currency (alphabetical)
            $currencyCompare = strcmp($a['fiscalCounterCurrency'], $b['fiscalCounterCurrency']);
            if ($currencyCompare !== 0) return $currencyCompare;
            
            // Level 3: Sort by taxID (if present) OR moneyType (if present)
            $aTaxID = $a['fiscalCounterTaxID'] ?? null;
            $bTaxID = $b['fiscalCounterTaxID'] ?? null;
            $aMoneyType = $a['fiscalCounterMoneyType'] ?? null;
            $bMoneyType = $b['fiscalCounterMoneyType'] ?? null;
            
            if ($aTaxID !== null && $bTaxID !== null) {
                // Both have taxID - compare numerically
                $taxIDCompare = $aTaxID <=> $bTaxID;
                if ($taxIDCompare !== 0) return $taxIDCompare;
            } elseif ($aMoneyType !== null && $bMoneyType !== null) {
                // Both have moneyType - compare alphabetically
                $moneyTypeCompare = strcmp($aMoneyType, $bMoneyType);
                if ($moneyTypeCompare !== 0) return $moneyTypeCompare;
            } elseif ($aTaxID !== null && $bTaxID === null) {
                return -1; // taxID comes before no taxID
            } elseif ($aTaxID === null && $bTaxID !== null) {
                return 1; // no taxID comes after taxID
            }
            
            // Level 4: Sort by taxPercent (if present)
            $aTaxPercent = $a['fiscalCounterTaxPercent'] ?? null;
            $bTaxPercent = $b['fiscalCounterTaxPercent'] ?? null;
            
            if ($aTaxPercent !== null && $bTaxPercent !== null) {
                return $aTaxPercent <=> $bTaxPercent;
            } elseif ($aTaxPercent !== null && $bTaxPercent === null) {
                return -1;
            } elseif ($aTaxPercent === null && $bTaxPercent !== null) {
                return 1;
            }
            
            return 0; // Equal
        });
        
        $counterStrings = [];
        foreach ($counters as $counter) {
            $parts = [];
            
            // fiscalCounterType (uppercase)
            $parts[] = strtoupper($counter['fiscalCounterType']);
            
            // fiscalCounterCurrency (uppercase)
            $parts[] = strtoupper($counter['fiscalCounterCurrency']);
            
            // fiscalCounterTaxPercent (with .00 format) OR fiscalCounterMoneyType
            if (isset($counter['fiscalCounterTaxPercent'])) {
                $taxPercent = (float) $counter['fiscalCounterTaxPercent'];
                $parts[] = number_format($taxPercent, 2, '.', '');
            } elseif (isset($counter['fiscalCounterMoneyType'])) {
                $parts[] = strtoupper($counter['fiscalCounterMoneyType']);
            }
            
            // fiscalCounterValue (in cents)
            $valueInCents = (int) round($counter['fiscalCounterValue'] * 100);
            $parts[] = (string) $valueInCents;
            
            // Concatenate without separators (|| is documentation notation)
            $counterStrings[] = implode('', $parts);
        }
        
        return implode('', $counterStrings);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Sign Canonical String (for both receipts and CloseDay)
    |--------------------------------------------------------------------------
    */
    private function signCanonicalString(string $canonicalString): array
    {
        // Hash the canonical string for the 'hash' field
        $hash = hash('sha256', $canonicalString, true);
        $hashBase64 = base64_encode($hash);
        
        Log::debug('ZIMRA signCanonicalString - Hash', [
            'canonical_string' => $canonicalString,
            'hash_hex' => bin2hex($hash),
            'hash_base64' => $hashBase64,
        ]);
        
        // Sign with device private key
        $zimraConfig = ZimraConfig::getActive();
        $privateKey = openssl_pkey_get_private($zimraConfig->private_key);
        
        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing');
        }
        
        $signature = '';
        // CRITICAL: Sign the canonical string directly, NOT the hash
        // openssl_sign() with OPENSSL_ALGO_SHA256 hashes internally
        $signResult = openssl_sign($canonicalString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$signResult) {
            throw new \Exception('Failed to sign canonical string');
        }
        
        $signatureBase64 = base64_encode($signature);
        
        Log::debug('ZIMRA signCanonicalString - Signature', [
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signature),
        ]);
        
        // Verify locally
        $publicKey = openssl_pkey_get_public($zimraConfig->certificate);
        $verifyResult = openssl_verify($canonicalString, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        
        Log::info('ZIMRA signCanonicalString - Local Verification', [
            'local_signature_valid' => $verifyResult,
            'verify_meaning' => $verifyResult === 1 ? 'VALID' : ($verifyResult === 0 ? 'INVALID' : 'ERROR'),
        ]);
        
        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Force Close Fiscal Day (Local Only - No ZIMRA API call)
    |--------------------------------------------------------------------------
    | Use this when ZIMRA API fails and you need to manually close the day
    */
    public function forceCloseDay(): array
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            throw new \Exception('No active ZIMRA configuration found.');
        }

        $deviceId = $zimraConfig->device_id;

        if (!$deviceId) {
            throw new \Exception('No device ID found in configuration.');
        }

        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        
        if (!$fiscalDay) {
            return [
                'error' => true,
                'message' => 'No open fiscal day found to close.'
            ];
        }

        // Force close locally without calling ZIMRA
        $fiscalDay->update([
            'status' => 'closed',
            'closed_at' => now(),
            'close_response' => ['forced' => true, 'reason' => 'Manual force close by user'],
        ]);

        Log::warning('ZIMRA Fiscal Day Force Closed (Local Only)', [
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'device_id' => $deviceId
        ]);

        return [
            'success' => true,
            'forced' => true,
            'message' => 'Fiscal day force closed locally. Note: ZIMRA was not notified.',
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
    public function submitReceipt(array $receiptData, int $deviceId = null)
    {
        // CRITICAL: device_id MUST be provided (no fallback to getActive)
        if (!$deviceId) {
            throw new \Exception(
                'CRITICAL: device_id is required. ' .
                'Device ID must be provided by ResolveCompanyDevice middleware. ' .
                'Do not call this method without specifying device_id.'
            );
        }

        // Load config for THIS specific device (not getActive)
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();

        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}.");
        }

        $baseUrl = $zimraConfig->base_url;

        // Write certificates from database to files for mTLS
        if ($zimraConfig->certificate && $zimraConfig->private_key) {
            Storage::put('zimra/device_certificate.pem', $zimraConfig->certificate);
            Storage::put('zimra/device_private.key', $zimraConfig->private_key);
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Get Fiscal Day Status from FDMS (Fix RCPT021)
        |--------------------------------------------------------------------------
        */
        $fdmsStatus = $this->getStatus($deviceId);
        
        if (isset($fdmsStatus['error'])) {
            throw new \Exception('Failed to get device status from FDMS: ' . json_encode($fdmsStatus));
        }

        // Validate FDMS response has required fields
        if (!isset($fdmsStatus['fiscalDayStatus'], $fdmsStatus['lastFiscalDayNo'])) {
            throw new \Exception('Invalid FDMS status response - missing fiscalDayStatus or lastFiscalDayNo');
        }

        $fdmsFiscalDayStatus = $fdmsStatus['fiscalDayStatus'];
        $fdmsFiscalDayNo = $fdmsStatus['lastFiscalDayNo'];

        Log::info('ZIMRA SubmitReceipt - FDMS Status', [
            'fiscalDayStatus' => $fdmsFiscalDayStatus,
            'lastFiscalDayNo' => $fdmsFiscalDayNo,
        ]);

        // Verify fiscal day is open on FDMS
        if ($fdmsFiscalDayStatus !== 'FiscalDayOpened') {
            $errorMsg = "RCPT021: FDMS fiscal day not open. Current status: {$fdmsFiscalDayStatus}. ";
            
            if ($fdmsFiscalDayStatus === 'FiscalDayClosed' || $fdmsFiscalDayStatus === 'FiscalDayNotOpened') {
                $errorMsg .= "Please open a fiscal day before submitting receipts.";
            } elseif ($fdmsFiscalDayStatus === 'FiscalDayCloseFailed') {
                $errorMsg .= "Previous fiscal day close failed. Resolve this on FDMS portal before opening a new day.";
            } elseif ($fdmsFiscalDayStatus === 'FiscalDayCloseInitiated') {
                $errorMsg .= "Fiscal day close is in progress. Wait for it to complete.";
            }
            
            throw new \Exception($errorMsg);
        }

        // Use FDMS fiscal day number
        $fiscalDayNo = (int) $fdmsFiscalDayNo;
        
        Log::info('Using FDMS fiscalDayNo', ['fiscalDayNo' => $fiscalDayNo]);

        // Check local fiscal day exists
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        if (!$fiscalDay) {
            return [
                'error' => true,
                'message' => 'No open fiscal day locally. Open a fiscal day before submitting receipts.'
            ];
        }

        // Sync local fiscal day with FDMS if different
        if ($fiscalDay->fiscal_day_no !== $fiscalDayNo) {
            Log::warning('ZIMRA SubmitReceipt - Local fiscal day mismatch, using FDMS value', [
                'local_fiscal_day_no' => $fiscalDay->fiscal_day_no,
                'fdms_fiscal_day_no' => $fiscalDayNo,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Get Tax Configuration from FDMS (GetConfig)
        |--------------------------------------------------------------------------
        */
        $configResponse = $this->getConfig($deviceId);
        $fdmsTaxes = $this->getFdmsTaxConfig($zimraConfig);
        $vatNumber = $configResponse['vatNumber'] ?? null;
        
        Log::info('ZIMRA SubmitReceipt - Tax Config from FDMS', [
            'taxes' => $fdmsTaxes,
            'vatNumber' => $vatNumber ?? 'NOT_REGISTERED',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Validate VAT Registration (Fix RCPT021)
        |--------------------------------------------------------------------------
        */
        // If device not VAT registered, only allow 0% tax
        if (!$vatNumber || $vatNumber === 'NOT_REGISTERED') {
            foreach ($receiptData['receiptLines'] ?? [] as $line) {
                $lineTaxPercent = (float) ($line['taxPercent'] ?? 0);
                if ($lineTaxPercent > 0) {
                    throw new \Exception(
                        "RCPT021: Device not VAT registered (vatNumber={$vatNumber}). " .
                        "Only 0% tax allowed. Line has {$lineTaxPercent}% tax."
                    );
                }
            }
            Log::info('VAT Validation: Device not registered, verified all lines are 0% tax');
        }

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Validate Invoice Number Uniqueness
        |--------------------------------------------------------------------------
        */
        $invoiceNo = $receiptData['invoiceNo'] ?? null;
        if ($invoiceNo) {
            $existingReceipt = Receipt::where('device_id', $deviceId)
                ->where('invoice_no', $invoiceNo)
                ->first();
            
            if ($existingReceipt) {
                throw new \Exception(
                    "Invoice number '{$invoiceNo}' already exists for this device. Use a unique invoice number."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Calculate Receipt Counters from device_states (SINGLE SOURCE OF TRUTH)
        |--------------------------------------------------------------------------
        | CRITICAL: Counters come ONLY from device_states, NOT from receipts table
        | This ensures atomic counter management and FDMS alignment
        |--------------------------------------------------------------------------
        */
        
        // Check device_state reconciliation status BEFORE proceeding
        $deviceState = DeviceState::where('device_id', $deviceId)->first();
        
        if (!$deviceState) {
            throw new \Exception(
                "CRITICAL: No device_state record found for device {$deviceId}. " .
                "Run migration: php artisan migrate"
            );
        }
        
        if ($deviceState->requires_reconciliation) {
            throw new \Exception(
                "CRITICAL: Device {$deviceId} requires reconciliation. " .
                "FDMS accepted a receipt but DB persistence failed. " .
                "Error: {$deviceState->reconciliation_error}. " .
                "Manual intervention required."
            );
        }
        
        // Verify FDMS state alignment with device_state
        $fdmsLastGlobal = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;
        $deviceStateLastGlobal = $deviceState->last_receipt_global_no;
        
        if ($fdmsLastGlobal !== $deviceStateLastGlobal) {
            Log::critical('FDMS_DEVICE_STATE_MISALIGNMENT', [
                'device_id' => $deviceId,
                'fdms_last_global' => $fdmsLastGlobal,
                'device_state_last_global' => $deviceStateLastGlobal,
                'difference' => $fdmsLastGlobal - $deviceStateLastGlobal,
            ]);
            
            throw new \Exception(
                "CRITICAL: FDMS/device_state misalignment detected. " .
                "FDMS lastReceiptGlobalNo: {$fdmsLastGlobal}, " .
                "device_state last_receipt_global_no: {$deviceStateLastGlobal}. " .
                "Reconciliation required before proceeding."
            );
        }
        
        // Calculate counters from device_state (NOT from receipts table)
        DB::transaction(function () use ($deviceId, $fiscalDayNo, &$receiptData) {
            // Lock device_state row (prevents concurrent access)
            $lockedDeviceState = DeviceState::where('device_id', $deviceId)
                ->lockForUpdate()
                ->first();
            
            if (!$lockedDeviceState) {
                throw new \Exception("CRITICAL: device_state row disappeared during transaction");
            }
            
            // Calculate next counters from device_state (SINGLE SOURCE OF TRUTH)
            $nextReceiptCounter = $lockedDeviceState->getNextReceiptCounter($fiscalDayNo);
            $nextGlobalNo = $lockedDeviceState->getNextGlobalNo();
            
            Log::info('ZIMRA SubmitReceipt - Counters from device_state (LOCKED)', [
                'device_state_last_fiscal_day' => $lockedDeviceState->last_fiscal_day_no,
                'device_state_last_counter' => $lockedDeviceState->last_receipt_counter,
                'device_state_last_global' => $lockedDeviceState->last_receipt_global_no,
                'fdms_fiscal_day_no' => $fiscalDayNo,
                'next_receipt_counter' => $nextReceiptCounter,
                'next_global_no' => $nextGlobalNo,
            ]);
            
            // Override counters with calculated values
            $receiptData['receiptCounter'] = $nextReceiptCounter;
            $receiptData['receiptGlobalNo'] = $nextGlobalNo;
        });

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Build Canonical Receipt Structure (Fix RCPT020/RCPT025)
        |--------------------------------------------------------------------------
        | Build the exact payload that will be signed AND sent - no re-encoding
        | NOTE: fiscalDayNo is NOT included in receipt - FDMS determines from openDay state
        |--------------------------------------------------------------------------
        */
        // Build canonical receipt using BCMath for precision
        $canonicalReceipt = $this->buildAndValidateReceiptBCMath(
            $receiptData,
            $fiscalDayNo,
            $fdmsTaxes
        );
        
        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Validate Receipt Totals (RCPT020 Prevention)
        |--------------------------------------------------------------------------
        */
        $this->validateReceiptTotals($canonicalReceipt);

        /*
        |--------------------------------------------------------------------------
        | 7️⃣ Build Payload, Sign, Inject Signature, Send
        |--------------------------------------------------------------------------
        | CRITICAL: Per FDMS Swagger spec, request body is {"receipt": {...}}
        | deviceID is a PATH parameter, NOT in the JSON body
        | Sign the exact payload structure that will be sent
        |--------------------------------------------------------------------------
        */
        // Step 1: Build payload structure WITHOUT signature (deviceID is path param, not in body)
        $requestPayload = [
            'receipt' => $canonicalReceipt,
        ];
        
        // Step 2: Build canonical string for signature per FDMS spec section 13.2.1
        // CRITICAL: Signature is NOT calculated from JSON, but from canonical concatenated string
        $canonicalString = $this->buildCanonicalStringForSignature($canonicalReceipt, $deviceId, $fiscalDayNo);
        
        Log::info('CANONICAL_STRING_FOR_SIGNING', [
            'canonical_string' => $canonicalString,
            'length' => strlen($canonicalString),
            'format' => 'deviceID||receiptType||receiptCurrency||receiptGlobalNo||receiptDate||receiptTotal(cents)||receiptTaxes',
        ]);
        
        // Step 3: Sign the canonical string (NOT the JSON payload)
        $signatureData = $this->signCanonicalString($canonicalString);
        
        // Step 4: Inject signature into the receipt object
        $requestPayload['receipt']['receiptDeviceSignature'] = $signatureData;
        
        // Step 5: Encode final payload with signature
        $finalJson = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
        
        Log::info('FINAL_JSON_SENT', [
            'json' => $finalJson,
            'length' => strlen($finalJson),
            'device_id' => $deviceId,
            'endpoint' => "/Device/v1/{$deviceId}/SubmitReceipt",
        ]);
        
        // CRITICAL: Verify canonical string was signed and signature added to JSON
        Log::info('SIGNING_VERIFICATION', [
            'canonical_string_length' => strlen($canonicalString),
            'final_json_length' => strlen($finalJson),
            'signature_added' => isset($requestPayload['receipt']['receiptDeviceSignature']),
            'signing_method' => 'FDMS canonical concatenated string (section 13.2.1)',
        ]);
        
        // Debug: Write payload to file for inspection
        $debugDir = storage_path('app/zimra/debug');
        if (!is_dir($debugDir)) {
            mkdir($debugDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $invoiceNo = $canonicalReceipt['invoiceNo'] ?? 'unknown';
        
        // Write canonical string that was signed
        file_put_contents(
            "{$debugDir}/receipt_{$timestamp}_{$invoiceNo}_canonical_string.txt",
            $canonicalString
        );
        
        // Write final JSON sent to FDMS (with signature)
        file_put_contents(
            "{$debugDir}/receipt_{$timestamp}_{$invoiceNo}_final.json",
            json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // Write debug summary
        $debugSummary = [
            'timestamp' => $timestamp,
            'device_id' => $deviceId,
            'invoice_no' => $invoiceNo,
            'receipt_total' => $canonicalReceipt['receiptTotal'],
            'tax_amount' => $canonicalReceipt['receiptTaxes'][0]['taxAmount'] ?? 0,
            'payment_amount' => $canonicalReceipt['receiptPayments'][0]['paymentAmount'] ?? 0,
            'hash' => $signatureData['hash'],
            'signature' => $signatureData['signature'],
            'canonical_string' => $canonicalString,
            'final_json_length' => strlen($finalJson),
        ];
        file_put_contents(
            "{$debugDir}/receipt_{$timestamp}_{$invoiceNo}_debug.json",
            json_encode($debugSummary, JSON_PRETTY_PRINT)
        );
        
        Log::info('DEBUG_FILES_CREATED', [
            'directory' => $debugDir,
            'device_id' => $deviceId,
            'invoice_no' => $invoiceNo,
            'receipt_total' => $canonicalReceipt['receiptTotal'],
            'tax_amount' => $canonicalReceipt['receiptTaxes'][0]['taxAmount'] ?? 0,
            'payment_amount' => $canonicalReceipt['receiptPayments'][0]['paymentAmount'] ?? 0,
        ]);
        
        // Store receiptData for database saving later (now includes signature)
        $receiptData = $requestPayload['receipt'];

        /*
        |--------------------------------------------------------------------------
        | 7️⃣ Send To ZIMRA using withBody() to prevent re-encoding
        |--------------------------------------------------------------------------
        */
        Log::info('ZIMRA SubmitReceipt Request', [
            'device_id' => $deviceId,
            'fdms_fiscal_day_no' => $fiscalDayNo,
            'receipt_counter' => $canonicalReceipt['receiptCounter'] ?? null,
            'receipt_global_no' => $canonicalReceipt['receiptGlobalNo'] ?? null,
            'receipt_total' => $canonicalReceipt['receiptTotal'] ?? null,
            'invoice_no' => $canonicalReceipt['invoiceNo'] ?? 'N/A',
        ]);

        $response = Http::withOptions([
            'cert' => storage_path('app/zimra/device_certificate.pem'),
            'ssl_key' => storage_path('app/zimra/device_private.key'),
        ])->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withBody($finalJson, 'application/json')
          ->post("{$baseUrl}/Device/v1/{$deviceId}/SubmitReceipt");

        /*
        |--------------------------------------------------------------------------
        | 7️⃣ Log Full Raw FDMS Response
        |--------------------------------------------------------------------------
        */
        $rawResponseBody = $response->body();
        $responseData = $response->json();

        Log::info('ZIMRA SubmitReceipt Raw Response', [
            'status' => $response->status(),
            'raw_body' => $rawResponseBody
        ]);

        Log::info('ZIMRA SubmitReceipt Parsed Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'response' => $responseData
        ]);

        if (!$response->successful()) {
            Log::error('ZIMRA SubmitReceipt Failed', [
                'status' => $response->status(),
                'body' => $responseData,
                'error_code' => $responseData['errorCode'] ?? null,
                'detail' => $responseData['detail'] ?? null,
            ]);
            return [
                'error' => true,
                'status' => $response->status(),
                'body' => $responseData
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 8️⃣ Parse Validation Errors (validationErrorCode + validationErrorColor)
        |--------------------------------------------------------------------------
        */
        $validationErrors = $responseData['validationErrors'] ?? [];
        $fdmsReceiptId = $responseData['receiptID'] ?? null;
        
        // Parse validation error codes and colors, track flags
        $hasRedErrors = false;
        $hasGrayErrors = false;
        $parsedErrors = [];
        
        if (!empty($validationErrors)) {
            foreach ($validationErrors as $error) {
                $errorCode = $error['validationErrorCode'] ?? $error['errorCode'] ?? null;
                $errorColor = $error['validationErrorColor'] ?? null;
                
                // Get error message from lookup table, fallback to response message or code description
                $errorMessage = $error['errorMessage'] 
                    ?? $error['message'] 
                    ?? self::VALIDATION_ERROR_MESSAGES[$errorCode] 
                    ?? "Unknown error ({$errorCode})";
                
                // Track error color flags
                if ($errorColor === 'Red') {
                    $hasRedErrors = true;
                } elseif ($errorColor === 'Gray' || $errorColor === 'Grey') {
                    $hasGrayErrors = true;
                }
                
                // Store parsed error with normalized structure
                $parsedErrors[] = [
                    'validationErrorCode' => $errorCode,
                    'validationErrorColor' => $errorColor,
                    'errorMessage' => $errorMessage,
                    'field' => $error['field'] ?? null,
                ];
                
                Log::error('ZIMRA Validation Error', [
                    'validationErrorCode' => $errorCode,
                    'validationErrorColor' => $errorColor,
                    'errorMessage' => $errorMessage,
                    'field' => $error['field'] ?? null,
                    'receipt_id' => $fdmsReceiptId,
                ]);
            }
        }

        // Fix validation logic: if any red errors exist, set validation_code to Red
        // Do not mark receipt as Green if red errors exist
        if ($hasRedErrors) {
            $receiptValidationCode = 'Red';
        } elseif ($hasGrayErrors) {
            $receiptValidationCode = 'Gray';
        } else {
            $receiptValidationCode = $responseData['receiptValidationCode'] ?? 'Green';
        }
        
        $isValid = !$hasRedErrors && !$hasGrayErrors && empty($validationErrors);

        Log::info('ZIMRA SubmitReceipt Validation Result', [
            'receipt_id' => $fdmsReceiptId,
            'receiptValidationCode' => $receiptValidationCode,
            'has_red_errors' => $hasRedErrors,
            'has_gray_errors' => $hasGrayErrors,
            'error_count' => count($parsedErrors),
            'is_valid' => $isValid,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9️⃣ Generate QR Code for Receipt Verification
        |--------------------------------------------------------------------------
        | QR Format: {qrUrl}/Receipt/Result?DeviceId={DeviceId}&ReceiptDate={ReceiptDate}&ReceiptCounterReceiptGlobalNo={ReceiptCounterReceiptGlobalNo}&ReceiptQrData={ReceiptQrData}
        | CRITICAL: Use exact values from submitReceipt REQUEST (not getStatus)
        */
        $qrCodeString = null;
        $qrCodeImageUrl = null;
        $verificationCode = null;
        
        if ($zimraConfig->qr_url && $fdmsReceiptId) {
            // Format device ID with leading zeros (10 digits)
            $formattedDeviceId = str_pad($deviceId, 10, '0', STR_PAD_LEFT);
            
            // Format receipt counter/global number with leading zeros (10 digits)
            $formattedGlobalNo = str_pad($receiptData['receiptGlobalNo'], 10, '0', STR_PAD_LEFT);
            
            // Format receipt date
            $receiptDate = $receiptData['receiptDate'] ?? now()->format('Y-m-d\TH:i:s');
            $formattedDate = urlencode(date('m/d/Y H:i:s', strtotime($receiptDate)));
            
            // Use FDMS server signature hash for verification code (ReceiptQrData)
            // CRITICAL: ZIMRA expects hexadecimal format (0-9, A-F only)
            // Server signature hash is base64-encoded, must decode and convert to hex
            $serverSignatureHash = $responseData['receiptServerSignature']['hash'] ?? null;
            
            if ($serverSignatureHash) {
                // Decode base64 hash and convert to hexadecimal
                $binary = base64_decode($serverSignatureHash);
                $hex = strtoupper(bin2hex($binary));
                
                // Take first 16 hex characters
                $code = substr($hex, 0, 16);
                
                // Format as XXXX-XXXX-XXXX-XXXX
                $verificationCode = sprintf(
                    '%s-%s-%s-%s',
                    substr($code, 0, 4),
                    substr($code, 4, 4),
                    substr($code, 8, 4),
                    substr($code, 12, 4)
                );
                
                Log::info('QR Code verification code generated from server signature', [
                    'server_signature_base64' => $serverSignatureHash,
                    'hex_full' => $hex,
                    'verification_code' => $verificationCode,
                ]);
            } else {
                // Fallback: generate from device signature if server signature not available
                $deviceSignatureHash = $receiptData['receiptDeviceSignature']['hash'] ?? '';
                $binary = base64_decode($deviceSignatureHash);
                $hex = strtoupper(bin2hex($binary));
                $code = substr($hex, 0, 16);
                
                $verificationCode = sprintf(
                    '%s-%s-%s-%s',
                    substr($code, 0, 4),
                    substr($code, 4, 4),
                    substr($code, 8, 4),
                    substr($code, 12, 4)
                );
                
                Log::warning('QR Code using device signature hash (server signature not available)', [
                    'fdms_receipt_id' => $fdmsReceiptId,
                    'device_signature_hash' => $deviceSignatureHash,
                    'verification_code' => $verificationCode,
                ]);
            }
            
            // Build QR string using ZIMRA validation portal format
            $qrCodeString = $zimraConfig->qr_url .
                '/Receipt/Result?DeviceId=' . $formattedDeviceId .
                '&ReceiptDate=' . $formattedDate .
                '&ReceiptCounterReceiptGlobalNo=' . $formattedGlobalNo .
                '&ReceiptQrData=' . $verificationCode;
            
            // Generate QR code image using ReceiptQrCodeService
            try {
                $qrData = $this->qrCodeService->generateQrCode($qrCodeString, $fdmsReceiptId);
                $qrCodeImageUrl = $qrData['qr_url'];
                
                Log::info('QR Code Image Generated', [
                    'qr_string' => $qrCodeString,
                    'qr_image_url' => $qrCodeImageUrl,
                    'verification_code' => $verificationCode,
                    'device_id' => $formattedDeviceId,
                    'receipt_id' => $fdmsReceiptId,
                    'fiscal_day_no' => $fiscalDayNo,
                    'receipt_global_no' => $formattedGlobalNo,
                    'receipt_date' => $formattedDate,
                ]);
            } catch (\Exception $e) {
                Log::error('QR Code Image Generation Failed', [
                    'error' => $e->getMessage(),
                    'receipt_id' => $fdmsReceiptId,
                ]);
            }
        } else {
            Log::warning('QR Code NOT Generated', [
                'qr_url_exists' => !empty($zimraConfig->qr_url),
                'receipt_id_exists' => !empty($fdmsReceiptId),
                'message' => 'Missing qr_url or receiptID - call getConfig first',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔟 Save Receipt with Validation Status to Database
        |--------------------------------------------------------------------------
        */
        $primaryTax = $receiptData['receiptTaxes'][0] ?? [];

        // Determine receiptType based on VAT registration (fallback if not set in receiptData)
        // NOTE: FDMS only supports FiscalInvoice, CreditNote, DebitNote, Refund
        // Non-VAT devices use FiscalInvoice with 0% tax
        $defaultReceiptType = 'FiscalInvoice';
        
        // For credit/debit notes, extract original_receipt_id from creditDebitNote
        $originalReceiptId = null;
        if (isset($receiptData['creditDebitNote']['receiptGlobalNo'])) {
            $originalReceipt = Receipt::where('receipt_global_no', $receiptData['creditDebitNote']['receiptGlobalNo'])
                ->where('device_id', $receiptData['creditDebitNote']['deviceID'] ?? $deviceId)
                ->first();
            if ($originalReceipt) {
                $originalReceiptId = $originalReceipt->id;
            }
        }

        $receipt = Receipt::create([
            'device_id' => $deviceId,
            'invoice_no' => $receiptData['invoiceNo'] ?? 'N/A',
            'receipt_type' => $receiptData['receiptType'] ?? $defaultReceiptType,
            'original_receipt_id' => $originalReceiptId,
            'receipt_currency' => $receiptData['receiptCurrency'] ?? 'USD',
            'receipt_counter' => $receiptData['receiptCounter'],
            'receipt_global_no' => $receiptData['receiptGlobalNo'],
            'fiscal_day_no' => $fiscalDayNo,
            'receipt_total' => $receiptData['receiptTotal'],
            'tax_amount' => $primaryTax['taxAmount'] ?? 0,
            'tax_code' => $primaryTax['taxCode'] ?? 'A',
            'tax_percent' => $primaryTax['taxPercent'] ?? 0,
            'payment_method' => $receiptData['receiptPayments'][0]['moneyTypeCode'] ?? 'Cash',
            'receipt_lines' => $receiptData['receiptLines'],
            'receipt_taxes' => $receiptData['receiptTaxes'],
            'receipt_payments' => $receiptData['receiptPayments'],
            'buyer_data' => $buyerDataForDb ?? $receiptData['buyerData'] ?? null,
            'receipt_hash' => $receiptData['receiptDeviceSignature']['hash'] ?? null,
            'receipt_signature' => $receiptData['receiptDeviceSignature'] ?? null,
            'receipt_qr_code' => $qrCodeString,
            'qr_url' => $qrCodeImageUrl,
            'verification_code' => $verificationCode,
            'zimra_response' => $responseData,
            'receipt_date' => $receiptData['receiptDate'] ?? now(),
            'date_issued' => $receiptData['dateIssued'] ?? null,
            'payment_due' => $receiptData['paymentDue'] ?? (
                ($receiptData['receiptType'] ?? $defaultReceiptType) === 'FiscalInvoice' 
                    ? now()->addWeeks(2)->format('Y-m-d') 
                    : null
            ),
            'receipt_notes' => $receiptData['receiptNotes'] ?? null,
            'validation_code' => $receiptValidationCode,
            'validation_errors' => $parsedErrors,
            'is_valid' => $isValid,
            'has_red_errors' => $hasRedErrors,
            'has_gray_errors' => $hasGrayErrors,
            'fdms_receipt_id' => $fdmsReceiptId,
        ]);

        Log::info('Receipt Saved to Database', [
            'receipt_id' => $receipt->id,
            'fdms_receipt_id' => $fdmsReceiptId,
            'is_valid' => $isValid,
            'has_red_errors' => $hasRedErrors,
            'has_gray_errors' => $hasGrayErrors,
            'validation_code' => $receiptValidationCode,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 🔟 Increment device_state Counters (CRITICAL)
        |--------------------------------------------------------------------------
        | ONLY increment after successful FDMS submission AND DB persistence
        |--------------------------------------------------------------------------
        */
        $deviceState->incrementCounters(
            $fiscalDayNo,
            $receiptData['receiptCounter'],
            $receiptData['receiptGlobalNo']
        );
        
        Log::info('device_state counters incremented', [
            'device_id' => $deviceId,
            'fiscal_day_no' => $fiscalDayNo,
            'receipt_counter' => $receiptData['receiptCounter'],
            'global_no' => $receiptData['receiptGlobalNo'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1️⃣1️⃣ Throw Exception if Red or Gray errors (Block CloseDay)
        |--------------------------------------------------------------------------
        */
        if ($hasRedErrors) {
            $errorSummary = collect($parsedErrors)->map(function ($e) {
                $code = $e['validationErrorCode'] ?? 'UNKNOWN';
                $msg = $e['errorMessage'] ?? 'No message';
                return "{$code}: {$msg}";
            })->implode('; ');

            Log::error('ZIMRA Receipt RED Validation - CloseDay Blocked', [
                'receipt_id' => $fdmsReceiptId,
                'validation_code' => $receiptValidationCode,
                'has_red_errors' => $hasRedErrors,
                'error_summary' => $errorSummary,
                'db_receipt_id' => $receipt->id,
            ]);

            throw new \Exception(
                "ZIMRA Receipt Validation Failed [RED]: {$errorSummary}. " .
                "Receipt saved to database (ID: {$receipt->id}). CloseDay will fail with this receipt."
            );
        }

        if ($hasGrayErrors) {
            $errorSummary = collect($parsedErrors)->map(function ($e) {
                $code = $e['validationErrorCode'] ?? 'UNKNOWN';
                $msg = $e['errorMessage'] ?? 'No message';
                return "{$code}: {$msg}";
            })->implode('; ');

            Log::warning('ZIMRA Receipt GRAY Validation - CloseDay Blocked', [
                'receipt_id' => $fdmsReceiptId,
                'validation_code' => $receiptValidationCode,
                'has_gray_errors' => $hasGrayErrors,
                'error_summary' => $errorSummary,
                'db_receipt_id' => $receipt->id,
            ]);

            throw new \Exception(
                "ZIMRA Receipt Validation Warning [GRAY]: {$errorSummary}. " .
                "Receipt saved to database (ID: {$receipt->id}). CloseDay will fail with this receipt."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8️⃣ Update Fiscal Day Counters (Only if Valid)
        |--------------------------------------------------------------------------
        */
        $this->updateFiscalCounters($fiscalDay, $receiptData);

        Log::info('ZIMRA Receipt Submitted Successfully', [
            'receipt_id' => $fdmsReceiptId,
            'db_receipt_id' => $receipt->id,
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'validation_code' => $receiptValidationCode,
            'receipt_counter' => $receiptData['receiptCounter'],
            'receipt_global_no' => $receiptData['receiptGlobalNo'],
        ]);

        return [
            'success' => true,
            'data' => $responseData,
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'validation_code' => $receiptValidationCode,
            'db_receipt_id' => $receipt->id,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Get Tax Configuration from FDMS
    |--------------------------------------------------------------------------
    */
    private function getFdmsTaxConfig(ZimraConfig $zimraConfig): array
    {
        // ALWAYS fetch from FDMS first to get registered tax IDs (RCPT014 fix)
        try {
            $configResponse = $this->getConfig($zimraConfig->device_id);
            
            // CRITICAL: Field is 'applicableTaxes' NOT 'taxes' per FDMS spec
            Log::info('RCPT014 DEBUG: Raw FDMS GetConfig response', [
                'applicableTaxes' => $configResponse['applicableTaxes'] ?? 'NOT_FOUND',
                'vatNumber' => $configResponse['vatNumber'] ?? 'NOT_REGISTERED',
            ]);
            
            if (isset($configResponse['applicableTaxes']) && is_array($configResponse['applicableTaxes'])) {
                // Normalize tax data: FDMS sandbox uses 'validFrom' not 'taxValidFrom'
                $normalizedTaxes = [];
                foreach ($configResponse['applicableTaxes'] as $idx => $tax) {
                    // Normalize field names for sandbox compatibility
                    $normalizedTax = [
                        'taxID' => $tax['taxID'] ?? null,
                        'taxPercent' => $tax['taxPercent'] ?? 0.0,
                        'taxName' => $tax['taxName'] ?? 'Unknown',
                        'taxCode' => $tax['taxCode'] ?? null, // Optional - sandbox doesn't provide
                        'taxValidFrom' => $tax['taxValidFrom'] ?? $tax['validFrom'] ?? null,
                        'taxValidTill' => $tax['taxValidTill'] ?? $tax['validTill'] ?? null,
                    ];
                    
                    Log::info("RCPT014 DEBUG: Tax[$idx]", [
                        'taxID' => $normalizedTax['taxID'],
                        'taxCode' => $normalizedTax['taxCode'] ?? 'NOT_PROVIDED',
                        'taxPercent' => $normalizedTax['taxPercent'],
                        'taxName' => $normalizedTax['taxName'],
                        'taxValidFrom' => $normalizedTax['taxValidFrom'] ?? 'NOT_PROVIDED',
                        'taxValidTill' => $normalizedTax['taxValidTill'] ?? 'NOT_PROVIDED',
                    ]);
                    
                    $normalizedTaxes[] = $normalizedTax;
                }
                return $normalizedTaxes;
            }
        } catch (\Exception $e) {
            Log::error('RCPT014 DEBUG: Failed to fetch FDMS tax config', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Check cached taxes as fallback
        if (!empty($zimraConfig->taxes)) {
            Log::warning('RCPT014 DEBUG: Using cached taxes (may cause RCPT014)', [
                'cached_taxes' => $zimraConfig->taxes
            ]);
            return $zimraConfig->taxes;
        }

        // THROW ERROR instead of using defaults - defaults will cause RCPT014
        throw new \Exception('RCPT014: Cannot get tax configuration from FDMS. Call GetConfig first to register taxes for this device.');
    }

    /*
    |--------------------------------------------------------------------------
    | Build & Validate Receipt with BCMath Precision (Fix RCPT020)
    |--------------------------------------------------------------------------
    | Uses BCMath for all tax calculations to ensure exact decimal precision
    |--------------------------------------------------------------------------
    */
    private function buildAndValidateReceiptBCMath(array $receiptData, int $fiscalDayNo, array $fdmsTaxes = []): array
    {
        // Determine receiptLinesTaxInclusive based on VAT status and tax rates
        $configResponse = $this->getConfig();
        $vatNumber = $configResponse['vatNumber'] ?? null;

        // Check if all receipt line taxPercent values are 0.0
        $allTaxPercentZero = true;
        foreach ($receiptData['receiptLines'] ?? [] as $line) {
            $lineTaxPercent = (float) ($line['taxPercent'] ?? 0.0);
            if ($lineTaxPercent > 0.0) {
                $allTaxPercentZero = false;
                break;
            }
        }

        if (!$vatNumber || $vatNumber === 'NOT_REGISTERED' || $allTaxPercentZero) {
            // Non-VAT device OR all tax rates are 0% → use FALSE
            $taxInclusive = false;
        } else {
            // VAT taxpayer with non-zero tax → use TRUE
            $taxInclusive = true;
        }

        $receiptData['receiptLinesTaxInclusive'] = $taxInclusive;

        // Build tax lookup from FDMS config - CRITICAL for RCPT012/RCPT014
        // Map by taxPercent (sandbox doesn't provide taxCode)
        // NORMALIZE taxPercent to 2 decimals for safe comparison
        $taxLookupByPercent = [];
        $taxLookupByCode = [];
        
        foreach ($fdmsTaxes as $tax) {
            $taxID = (int) ($tax['taxID'] ?? null);
            $taxPercentRaw = (float) ($tax['taxPercent'] ?? 0.0);
            $taxPercentNormalized = number_format($taxPercentRaw, 2, '.', '');
            $taxName = $tax['taxName'] ?? 'Unknown';
            $taxCode = $tax['taxCode'] ?? null;
            
            if ($taxID === null) {
                continue; // Skip invalid tax entries
            }
            
            $taxInfo = [
                'taxID' => $taxID,
                'taxPercent' => $taxPercentNormalized,
                'taxName' => $taxName,
                'taxCode' => $taxCode,
                'taxValidFrom' => $tax['taxValidFrom'] ?? null,
                'taxValidTill' => $tax['taxValidTill'] ?? null,
            ];
            
            // Map by normalized taxPercent (primary - always available)
            $taxLookupByPercent[$taxPercentNormalized] = $taxInfo;
            
            // Map by taxCode (secondary - only if provided)
            if ($taxCode !== null) {
                $taxLookupByCode[$taxCode] = $taxInfo;
            }
        }

        // If no taxes from FDMS, throw error - we MUST have valid tax config
        if (empty($taxLookupByPercent)) {
            throw new \Exception('RCPT012/RCPT014: No tax configuration from FDMS. Call GetConfig first.');
        }

        Log::debug('Receipt Tax Mode (BCMath)', [
            'tax_inclusive' => $taxInclusive,
            'fdms_tax_lookup_by_percent' => $taxLookupByPercent,
            'fdms_tax_lookup_by_code' => $taxLookupByCode,
            'available_tax_percents' => array_keys($taxLookupByPercent),
            'available_tax_codes' => array_keys($taxLookupByCode),
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sanitize Buyer Data (FDMS Validation)
        |--------------------------------------------------------------------------
        */
        // CRITICAL: Credit/Debit notes should NOT include buyerData in FDMS submission
        // They reference the original invoice via creditDebitNote
        // CRITICAL: Preserve buyerData for database before removing from FDMS payload
        // Credit/Debit notes should NOT send buyerData to FDMS (causes RCPT035 errors)
        // but we MUST save it to database for PDF display
        $buyerDataForDb = null;
        $receiptType = $receiptData['receiptType'] ?? 'FiscalInvoice';
        if (in_array($receiptType, ['CreditNote', 'DebitNote'])) {
            if (isset($receiptData['buyerData'])) {
                // Save buyer data for database
                $buyerDataForDb = $receiptData['buyerData'];
                
                Log::info('CREDIT_DEBIT_NOTE_BUYER_DATA_PRESERVED', [
                    'receipt_type' => $receiptType,
                    'action' => 'Preserving buyerData for DB, removing from FDMS payload',
                    'buyer_data' => $buyerDataForDb,
                ]);
                
                // Remove from FDMS payload to avoid RCPT035 validation errors
                unset($receiptData['buyerData']);
            }
        } elseif (isset($receiptData['buyerData'])) {
            // For regular invoices, validate buyer data format
            
            // FDMS requires VAT number to be exactly 9 characters
            if (isset($receiptData['buyerData']['vatNumber'])) {
                $vatNum = trim($receiptData['buyerData']['vatNumber']);
                if (strlen($vatNum) !== 9) {
                    Log::warning('BUYER_VAT_INVALID_LENGTH', [
                        'vat_number' => $vatNum,
                        'length' => strlen($vatNum),
                        'required_length' => 9,
                        'action' => 'Removing from payload'
                    ]);
                    unset($receiptData['buyerData']['vatNumber']);
                }
            }
            
            // FDMS requires TIN to be exactly 10 digits (no letters or special chars)
            if (isset($receiptData['buyerData']['buyerTIN'])) {
                $tin = trim($receiptData['buyerData']['buyerTIN']);
                if (!preg_match('/^\d{10}$/', $tin)) {
                    Log::warning('BUYER_TIN_INVALID_FORMAT', [
                        'tin' => $tin,
                        'length' => strlen($tin),
                        'required_format' => '10 digits only',
                        'action' => 'Removing buyerData entirely to prevent RCPT035'
                    ]);
                    // Remove entire buyerData if TIN is invalid
                    // FDMS validates TIN strictly and rejects the entire receipt
                    unset($receiptData['buyerData']);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Line Totals & Taxes using BCMath
        |--------------------------------------------------------------------------
        */
        $receiptLines = $receiptData['receiptLines'] ?? [];
        $taxTotals = [];
        $calculatedReceiptTotal = '0.00';

        foreach ($receiptLines as $index => &$line) {
            // Store original values before rebuilding line
            $originalLineType = $line['receiptLineType'] ?? 'Sale';
            $originalLineName = $line['receiptLineName'] ?? 'Item';
            $originalHSCode = $line['receiptLineHSCode'] ?? '00000000';
            
            // CRITICAL: Round price and quantity FIRST, then calculate lineTotal
            // FDMS recalculates: rounded_price × rounded_quantity
            // We must use the SAME rounded values to avoid RCPT020
            $priceRounded = $this->bcRound($this->bcFormat($line['receiptLinePrice'] ?? '0'), 2);
            $quantityRounded = $this->bcRound($this->bcFormat($line['receiptLineQuantity'] ?? '1'), 2);
            
            // Get tax info from line (could be taxCode or taxPercent)
            $lineTaxCode = $line['taxCode'] ?? null;
            $lineTaxPercent = isset($line['taxPercent']) ? number_format((float)$line['taxPercent'], 2, '.', '') : null;
            
            // CRITICAL: Map to FDMS tax by taxPercent (sandbox doesn't provide taxCode)
            // Try taxCode first (if provided and available), then fall back to taxPercent
            $fdmsTax = null;
            
            if ($lineTaxCode && isset($taxLookupByCode[$lineTaxCode])) {
                // Use taxCode if available (production)
                $fdmsTax = $taxLookupByCode[$lineTaxCode];
            } elseif ($lineTaxPercent && isset($taxLookupByPercent[$lineTaxPercent])) {
                // Use taxPercent (sandbox)
                $fdmsTax = $taxLookupByPercent[$lineTaxPercent];
            } else {
                // Neither worked - throw error
                $availablePercents = implode(', ', array_keys($taxLookupByPercent));
                $availableCodes = implode(', ', array_keys($taxLookupByCode));
                throw new \Exception(
                    "RCPT012: Invalid tax. Line has taxPercent={$lineTaxPercent}, taxCode={$lineTaxCode}. " .
                    "Available from FDMS: percents=[{$availablePercents}], codes=[{$availableCodes}]"
                );
            }
            
            // CRITICAL: Always use taxID from FDMS config (RCPT014) - never from input
            $taxID = (int) $fdmsTax['taxID'];
            $taxPercent = $this->bcFormat($fdmsTax['taxPercent']);
            $taxCode = $fdmsTax['taxCode'] ?? null;
            
            // Get receiptDate for tax validity check
            $receiptDate = $receiptData['receiptDate'] ?? date('Y-m-d\TH:i:s');
            
            // RCPT014 DEBUG: Log tax validity period check
            Log::info('RCPT014 TAX VALIDATION CHECK', [
                'line_no' => $index + 1,
                'receiptDate' => $receiptDate,
                'taxCode' => $taxCode,
                'taxID' => $taxID,
                'taxPercent' => $taxPercent,
                'taxValidFrom' => $fdmsTax['taxValidFrom'] ?? 'NOT_SET',
                'taxValidTill' => $fdmsTax['taxValidTill'] ?? 'NOT_SET',
            ]);

            // FDMS RULE: Calculate lineTotal from ROUNDED price × quantity (scale 2)
            // This matches exactly what FDMS will recalculate
            $lineTotal = bcmul($priceRounded, $quantityRounded, 2);

            // ZIMRA RULE: receiptLinesTaxInclusive determines tax calculation
            if ($taxInclusive) {
                // TRUE = Prices INCLUDE tax (GROSS) - lineTotal already includes tax
                $salesAmountWithTax = $lineTotal;
                $divisor = bcadd('100', $taxPercent, 6);
                $taxAmount = $this->bcRound(
                    bcdiv(bcmul($lineTotal, $taxPercent, 6), $divisor, 6),
                    2
                );
            } else {
                // FALSE = Prices EXCLUDE tax (NET) - add tax to lineTotal
                $taxAmount = $this->bcRound(
                    bcdiv(bcmul($lineTotal, $taxPercent, 6), '100', 6),
                    2
                );
                $salesAmountWithTax = bcadd($lineTotal, $taxAmount, 2);
            }
            
            // CRITICAL: Normalize zero values to prevent "-0.00" (RCPT015)
            // ZIMRA rejects "-0.00" for tax amounts, especially for 0% tax on credit notes
            if (bccomp($taxAmount, '0', 2) === 0) {
                $taxAmount = '0.00';
            }
            if (bccomp($salesAmountWithTax, '0', 2) === 0) {
                $salesAmountWithTax = '0.00';
            }

            // CRITICAL: Build line with EXACT CANONICAL ORDER per FDMS API specification
            // Order from FDMS spec: receiptLineType, receiptLineNo, receiptLineHSCode (if VAT),
            //        receiptLineName, receiptLinePrice, receiptLineQuantity, receiptLineTotal,
            //        taxCode, taxPercent, taxID
            // DO NOT use dynamic field insertion - construct in fixed order
            $line = [
                'receiptLineType' => $originalLineType,
                'receiptLineNo' => (int) ($index + 1),
            ];
            
            // Only include receiptLineHSCode for VAT registered taxpayers
            if ($vatNumber && $vatNumber !== 'NOT_REGISTERED') {
                $line['receiptLineHSCode'] = $originalHSCode;
            }
            
            $line['receiptLineName'] = $originalLineName;
            // CRITICAL: FDMS expects numeric types, not strings
            $line['receiptLinePrice'] = (float) number_format((float) bcadd($priceRounded, '0', 2), 2, '.', '');
            $line['receiptLineQuantity'] = (float) number_format((float) bcadd($quantityRounded, '0', 2), 2, '.', '');
            $line['receiptLineTotal'] = (float) number_format((float) bcadd($lineTotal, '0', 2), 2, '.', '');
            // CRITICAL: Only include taxCode if it has a value (FDMS rejects null)
            if ($taxCode !== null && $taxCode !== '') {
                $line['taxCode'] = $taxCode;
            }
            $line['taxPercent'] = (float) number_format((float) bcadd($taxPercent, '0', 2), 2, '.', '');
            $line['taxID'] = (int) $taxID;
            
            // Log canonical field order for verification
            Log::info('CANONICAL_RECEIPT_LINE_ORDER', [
                'line_no' => $line['receiptLineNo'],
                'field_order' => array_keys($line),
            ]);

            // Accumulate tax totals by taxID (use scale 2 - accumulate ROUNDED values)
            $taxKey = (string) $taxID;
            if (!isset($taxTotals[$taxKey])) {
                $taxTotals[$taxKey] = [
                    'taxPercent' => number_format((float) bcadd($taxPercent, '0', 2), 2, '.', ''),
                    'taxID' => (int) $taxID,
                    'taxAmount' => '0.00',
                    'salesAmountWithTax' => '0.00',
                ];
                // Only include taxCode if it has a value
                if ($taxCode !== null && $taxCode !== '') {
                    $taxTotals[$taxKey]['taxCode'] = $taxCode;
                }
            }
            $taxTotals[$taxKey]['taxAmount'] = bcadd($taxTotals[$taxKey]['taxAmount'], $taxAmount, 2);
            $taxTotals[$taxKey]['salesAmountWithTax'] = bcadd($taxTotals[$taxKey]['salesAmountWithTax'], $salesAmountWithTax, 2);

            // Accumulate receipt total (use scale 2 - accumulate ROUNDED values)
            $calculatedReceiptTotal = bcadd($calculatedReceiptTotal, $salesAmountWithTax, 2);

            Log::debug('Receipt Line Calculation (BCMath)', [
                'line_no' => $line['receiptLineNo'] ?? $index + 1,
                'price' => $priceRounded,
                'quantity' => $quantityRounded,
                'tax_percent' => $taxPercent,
                'tax_inclusive' => $taxInclusive,
                'line_total' => $lineTotal,
                'tax_amount' => $taxAmount,
                'sales_amount_with_tax' => $salesAmountWithTax,
            ]);
        }
        unset($line);

        $receiptData['receiptLines'] = $receiptLines;

        // Receipt total already accumulated at scale 2 - no additional rounding needed
        // $calculatedReceiptTotal is already correct
        
        // Convert tax totals to numeric values with 2 decimal precision
        $formattedTaxes = [];
        foreach ($taxTotals as $tax) {
            // CRITICAL: Normalize zero values to prevent "-0.00" (RCPT015)
            $taxAmount = $tax['taxAmount'];
            $salesAmountWithTax = $tax['salesAmountWithTax'];
            
            if (bccomp($taxAmount, '0', 2) === 0) {
                $taxAmount = '0.00';
            }
            if (bccomp($salesAmountWithTax, '0', 2) === 0) {
                $salesAmountWithTax = '0.00';
            }
            
            $taxEntry = [];
            // CRITICAL: Only include taxCode if it has a value (FDMS rejects null)
            if (isset($tax['taxCode']) && $tax['taxCode'] !== null && $tax['taxCode'] !== '') {
                $taxEntry['taxCode'] = $tax['taxCode'];
            }
            // CRITICAL: FDMS expects numeric types, not strings
            $taxEntry['taxPercent'] = (float) number_format((float) bcadd($tax['taxPercent'], '0', 2), 2, '.', '');
            $taxEntry['taxID'] = (int) $tax['taxID'];
            $taxEntry['taxAmount'] = (float) number_format((float) bcadd($taxAmount, '0', 2), 2, '.', '');
            $taxEntry['salesAmountWithTax'] = (float) number_format((float) bcadd($salesAmountWithTax, '0', 2), 2, '.', '');
            
            $formattedTaxes[] = $taxEntry;
        }
        
        // FDMS requires receiptTaxes even for non-VAT devices
        $receiptData['receiptTaxes'] = $formattedTaxes;

        // Set receipt total as numeric type with exact 2 decimal precision
        $receiptData['receiptTotal'] = (float) number_format((float) bcadd($calculatedReceiptTotal, '0', 2), 2, '.', '');

        Log::debug('Receipt Tax Totals (BCMath)', [
            'taxes' => $formattedTaxes,
            'calculated_receipt_total' => $calculatedReceiptTotal,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validate Payments Match Total
        |--------------------------------------------------------------------------
        */
        $payments = $receiptData['receiptPayments'] ?? [];
        $totalPayments = '0.00';

        foreach ($payments as &$payment) {
            $paymentAmount = $this->bcFormat($payment['paymentAmount'] ?? '0');
            // CRITICAL: FDMS expects numeric types, not strings
            $payment['paymentAmount'] = (float) number_format((float) bcadd($this->bcRound($paymentAmount, 2), '0', 2), 2, '.', '');
            $totalPayments = bcadd($totalPayments, $paymentAmount, 2);
        }
        unset($payment);
        
        // Auto-fill payment if empty or total is zero
        if (empty($payments) || bccomp($totalPayments, '0.00', 2) === 0) {
            $payments = [
                [
                    'moneyTypeCode' => 'Cash',
                    // CRITICAL: FDMS expects numeric types, not strings
                    'paymentAmount' => (float) number_format((float) bcadd($calculatedReceiptTotal, '0', 2), 2, '.', ''),
                ]
            ];
            $totalPayments = $calculatedReceiptTotal;
        }
        
        $receiptData['receiptPayments'] = $payments;

        Log::debug('Receipt Payments Validation (BCMath)', [
            'receipt_total' => $calculatedReceiptTotal,
            'total_payments' => $totalPayments,
            'payment_count' => count($payments),
        ]);

        // Validate: receiptTotal == sum(paymentAmount)
        if (bccomp($totalPayments, $calculatedReceiptTotal, 2) !== 0) {
            Log::error('Receipt Payment Mismatch (BCMath)', [
                'receipt_total' => $calculatedReceiptTotal,
                'total_payments' => $totalPayments,
            ]);
            throw new \Exception(
                "Payment mismatch: receiptTotal ({$calculatedReceiptTotal}) != sum of payments ({$totalPayments})"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate salesAmountWithTax Sum Matches receiptTotal
        |--------------------------------------------------------------------------
        */
        $totalSalesWithTax = '0.00';
        foreach ($formattedTaxes as $tax) {
            $totalSalesWithTax = bcadd($totalSalesWithTax, $this->bcFormat($tax['salesAmountWithTax']), 2);
        }

        Log::debug('Receipt Tax Total Validation (BCMath)', [
            'receipt_total' => $calculatedReceiptTotal,
            'sum_sales_with_tax' => $totalSalesWithTax,
        ]);

        // STRICT VALIDATION: receiptTotal == sum(salesAmountWithTax)
        if (bccomp($totalSalesWithTax, $calculatedReceiptTotal, 2) !== 0) {
            Log::error('Receipt Tax Sum Mismatch (BCMath)', [
                'receipt_total' => $calculatedReceiptTotal,
                'sum_sales_with_tax' => $totalSalesWithTax,
            ]);
            throw new \Exception(
                "Tax mismatch: receiptTotal ({$calculatedReceiptTotal}) != sum of salesAmountWithTax ({$totalSalesWithTax})"
            );
        }

        Log::info('Receipt Validated Successfully (BCMath)', [
            'receipt_total' => $calculatedReceiptTotal,
            'tax_inclusive' => $taxInclusive,
            'line_count' => count($receiptLines),
            'tax_groups' => count($formattedTaxes),
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Build Canonical Receipt Structure
        |--------------------------------------------------------------------------
        */
        // Determine receiptType based on VAT registration
        // NOTE: FDMS only supports FiscalInvoice, CreditNote, DebitNote, Refund
        // Non-VAT devices use FiscalInvoice with 0% tax
        $defaultReceiptType = 'FiscalInvoice';
        
        $canonicalReceipt = [
            'receiptType' => $receiptData['receiptType'] ?? $defaultReceiptType,
            'receiptCurrency' => $receiptData['receiptCurrency'] ?? 'USD',
            'receiptCounter' => (int) ($receiptData['receiptCounter'] ?? 1),
            'receiptGlobalNo' => (int) ($receiptData['receiptGlobalNo'] ?? 1),
            'invoiceNo' => $receiptData['invoiceNo'] ?? '',
            'receiptDate' => $receiptData['receiptDate'] ?? date('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => $taxInclusive,
            'receiptLines' => $receiptData['receiptLines'],
        ];
        
        // FDMS requires receiptTaxes for all devices (including non-VAT)
        $canonicalReceipt['receiptTaxes'] = $formattedTaxes;
        $canonicalReceipt['receiptPayments'] = $receiptData['receiptPayments'];
        $canonicalReceipt['receiptTotal'] = $receiptData['receiptTotal'];
        $canonicalReceipt['receiptPrintForm'] = $receiptData['receiptPrintForm'] ?? 'Receipt48';

        // Add optional fields if present
        if (!empty($receiptData['buyerData'])) {
            $canonicalReceipt['buyerData'] = $receiptData['buyerData'];
        }
        if (!empty($receiptData['receiptNotes'])) {
            $canonicalReceipt['receiptNotes'] = $receiptData['receiptNotes'];
        }
        if (!empty($receiptData['creditDebitNote'])) {
            $canonicalReceipt['creditDebitNote'] = $receiptData['creditDebitNote'];
        }

        return $canonicalReceipt;
    }

    /**
     * Format number for BCMath (ensure string with proper decimals)
     */
    private function bcFormat($value, int $decimals = 2): string
    {
        if (is_string($value)) {
            return number_format((float) $value, $decimals, '.', '');
        }
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Round using BCMath
     */
    private function bcRound(string $value, int $precision = 2): string
    {
        $pow = bcpow('10', (string) $precision, 0);
        return bcdiv(bcadd(bcmul($value, $pow, $precision + 1), '0.5', $precision + 1), $pow, $precision);
    }

    /**
     * Format monetary value as string with exact 2 decimal places
     * CRITICAL: FDMS requires exact decimal formatting - never use (float) cast
     */
    private function money(string $value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    /*
    |--------------------------------------------------------------------------
    | Build Canonical Receipt Payload - FDMS API v7.2 Compliant
    |--------------------------------------------------------------------------
    | Builds the exact receipt structure that will be signed and sent
    | NOTE: fiscalDayNo is NOT included - FDMS determines from openDay state
    | NOTE: receiptDeviceSignature is NOT included - added after signing
    |--------------------------------------------------------------------------
    */
    private function buildCanonicalReceiptPayload(array $receiptData, array $fdmsTaxes = []): array
    {
        // Get VAT registration status
        $configResponse = $this->getConfig();
        $vatNumber = $configResponse['vatNumber'] ?? null;
        
        // Default to tax-inclusive unless explicitly set
        $taxInclusive = $receiptData['receiptLinesTaxInclusive'] ?? true;

        // Build tax lookup from FDMS config
        $taxLookup = [];
        foreach ($fdmsTaxes as $tax) {
            $code = $tax['taxCode'] ?? 'A';
            $taxLookup[$code] = [
                'taxID' => $tax['taxID'] ?? 1,
                'taxPercent' => (float) ($tax['taxPercent'] ?? 15.00),
            ];
        }

        // Process receipt lines with strict rounding
        $receiptLines = $receiptData['receiptLines'] ?? [];
        $taxTotals = [];
        $sumOfLineTotals = 0.0;

        foreach ($receiptLines as $index => &$line) {
            $price = (float) ($line['receiptLinePrice'] ?? 0);
            $quantity = (float) ($line['receiptLineQuantity'] ?? 1);
            $taxCode = $line['taxCode'] ?? 'A';
            
            // Get taxID and taxPercent from FDMS config
            $fdmsTax = $taxLookup[$taxCode] ?? ['taxID' => 1, 'taxPercent' => 15.00];
            $taxID = (int) ($line['taxID'] ?? $fdmsTax['taxID']);
            $taxPercent = (float) ($line['taxPercent'] ?? $fdmsTax['taxPercent']);

            // Calculate line total
            $lineTotal = round($price * $quantity, 2, PHP_ROUND_HALF_UP);

            // Calculate tax amount per FDMS v7.2 spec
            if ($taxInclusive) {
                // taxAmount = lineTotal * taxPercent / (100 + taxPercent)
                $taxAmount = round($lineTotal * $taxPercent / (100 + $taxPercent), 2, PHP_ROUND_HALF_UP);
                $salesAmountWithTax = $lineTotal;
            } else {
                // taxAmount = lineTotal * taxPercent / 100
                $taxAmount = round($lineTotal * $taxPercent / 100, 2, PHP_ROUND_HALF_UP);
                $salesAmountWithTax = round($lineTotal + $taxAmount, 2, PHP_ROUND_HALF_UP);
            }

            // Store original HSCode before updating line
            $originalHSCode = $line['receiptLineHSCode'] ?? '00000000';
            
            // Update line with calculated values
            $line['receiptLineType'] = $line['receiptLineType'] ?? 'Sale';
            $line['receiptLineNo'] = (int) ($line['receiptLineNo'] ?? $index + 1);
            
            // Only include receiptLineHSCode for VAT registered taxpayers
            if ($vatNumber && $vatNumber !== 'NOT_REGISTERED') {
                $line['receiptLineHSCode'] = $originalHSCode;
            } else {
                unset($line['receiptLineHSCode']);
            }
            
            $line['receiptLineName'] = $line['receiptLineName'] ?? 'Item';
            $line['receiptLinePrice'] = $price;
            $line['receiptLineQuantity'] = $quantity;
            $line['receiptLineTotal'] = $lineTotal;
            $line['taxCode'] = $taxCode;
            $line['taxID'] = $taxID;
            $line['taxPercent'] = $taxPercent;

            // Accumulate tax totals by taxCode
            $taxKey = "{$taxCode}_{$taxID}";
            if (!isset($taxTotals[$taxKey])) {
                $taxTotals[$taxKey] = [
                    'taxCode' => $taxCode,
                    'taxPercent' => $taxPercent,
                    'taxID' => $taxID,
                    'taxAmount' => 0.0,
                    'salesAmountWithTax' => 0.0,
                ];
            }
            $taxTotals[$taxKey]['taxAmount'] += $taxAmount;
            $taxTotals[$taxKey]['salesAmountWithTax'] += $salesAmountWithTax;

            // Accumulate receipt total
            $sumOfLineTotals += $salesAmountWithTax;
        }
        unset($line);

        // Round accumulated totals
        $receiptTotal = (float) number_format(round($sumOfLineTotals, 2, PHP_ROUND_HALF_UP), 2, '.', '');

        // Format taxes with rounding
        $formattedTaxes = [];
        $sumOfTaxSales = 0.0;
        foreach ($taxTotals as $tax) {
            $roundedTaxAmount = (float) number_format(round($tax['taxAmount'], 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $roundedSalesWithTax = (float) number_format(round($tax['salesAmountWithTax'], 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $formattedTaxes[] = [
                'taxCode' => $tax['taxCode'],
                'taxPercent' => (float) number_format($tax['taxPercent'], 2, '.', ''),
                'taxID' => $tax['taxID'],
                'taxAmount' => $roundedTaxAmount,
                'salesAmountWithTax' => $roundedSalesWithTax,
            ];
            $sumOfTaxSales += $roundedSalesWithTax;
        }

        // Format payments - must equal receiptTotal
        $payments = $receiptData['receiptPayments'] ?? [];
        $formattedPayments = [];
        $sumOfPayments = 0.0;
        foreach ($payments as $payment) {
            $paymentAmount = (float) number_format(round((float) ($payment['paymentAmount'] ?? $receiptTotal), 2, PHP_ROUND_HALF_UP), 2, '.', '');
            $formattedPayments[] = [
                'moneyTypeCode' => $payment['moneyTypeCode'] ?? 'Cash',
                'paymentAmount' => $paymentAmount,
            ];
            $sumOfPayments += $paymentAmount;
        }

        // If no payments provided, add default payment matching receiptTotal
        if (empty($formattedPayments)) {
            $formattedPayments[] = [
                'moneyTypeCode' => 'Cash',
                'paymentAmount' => (float) number_format($receiptTotal, 2, '.', ''),
            ];
            $sumOfPayments = $receiptTotal;
        }

        // Build canonical receipt - NO fiscalDayNo, NO receiptDeviceSignature
        // Determine receiptType based on VAT registration
        // NOTE: FDMS only supports FiscalInvoice, CreditNote, DebitNote, Refund
        // Non-VAT devices use FiscalInvoice with 0% tax
        $defaultReceiptType = 'FiscalInvoice';
        
        $canonical = [
            'receiptType' => $receiptData['receiptType'] ?? $defaultReceiptType,
            'receiptCurrency' => $receiptData['receiptCurrency'] ?? 'USD',
            'receiptCounter' => (int) ($receiptData['receiptCounter'] ?? 1),
            'receiptGlobalNo' => (int) ($receiptData['receiptGlobalNo'] ?? 1),
            'invoiceNo' => $receiptData['invoiceNo'] ?? '',
            'receiptDate' => $receiptData['receiptDate'] ?? date('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => $taxInclusive,
            'receiptLines' => $receiptLines,
        ];
        
        // FDMS requires receiptTaxes for all devices (including non-VAT)
        $canonical['receiptTaxes'] = $formattedTaxes;
        $canonical['receiptPayments'] = $formattedPayments;
        $canonical['receiptTotal'] = $receiptTotal;
        $canonical['receiptPrintForm'] = $receiptData['receiptPrintForm'] ?? 'Receipt48';

        // Add optional fields if present
        if (!empty($receiptData['buyerData'])) {
            $canonical['buyerData'] = $receiptData['buyerData'];
        }
        if (!empty($receiptData['receiptNotes'])) {
            $canonical['receiptNotes'] = $receiptData['receiptNotes'];
        }
        if (!empty($receiptData['creditDebitNote'])) {
            $canonical['creditDebitNote'] = $receiptData['creditDebitNote'];
        }

        Log::debug('Built Canonical Receipt Payload', [
            'receipt_total' => $receiptTotal,
            'sum_of_line_totals' => $sumOfLineTotals,
            'sum_of_tax_sales' => $sumOfTaxSales,
            'sum_of_payments' => $sumOfPayments,
            'tax_count' => count($formattedTaxes),
            'line_count' => count($receiptLines),
        ]);

        return $canonical;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Receipt Totals - RCPT020 Prevention
    |--------------------------------------------------------------------------
    | Validates that receiptTotal = SUM(receiptLineTotal) = SUM(salesAmountWithTax) = SUM(paymentAmount)
    |--------------------------------------------------------------------------
    */
 /*
|--------------------------------------------------------------------------
| Validate Receipt Totals - RCPT020 Prevention (FDMS v7.2 Correct Logic)
|--------------------------------------------------------------------------
| FDMS validates ONLY:
|   1) receiptTotal == SUM(salesAmountWithTax)
|   2) receiptTotal == SUM(paymentAmount)
|
| FDMS DOES NOT validate against receiptLineTotal.
|--------------------------------------------------------------------------
*/
private function validateReceiptTotals(array $receipt): void
{
    $tolerance = 0.01;

    // Normalize receiptTotal
    $receiptTotal = round((float) ($receipt['receiptTotal'] ?? 0), 2, PHP_ROUND_HALF_UP);

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Validate Against salesAmountWithTax
    |--------------------------------------------------------------------------
    */
    $sumSalesWithTax = 0.0;

    foreach ($receipt['receiptTaxes'] ?? [] as $tax) {
        $sumSalesWithTax += (float) ($tax['salesAmountWithTax'] ?? 0);
    }

    $sumSalesWithTax = round($sumSalesWithTax, 2, PHP_ROUND_HALF_UP);

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Validate Against Payments
    |--------------------------------------------------------------------------
    */
    $sumPayments = 0.0;

    foreach ($receipt['receiptPayments'] ?? [] as $payment) {
        $sumPayments += (float) ($payment['paymentAmount'] ?? 0);
    }

    $sumPayments = round($sumPayments, 2, PHP_ROUND_HALF_UP);

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    */
    Log::info('VALIDATE_RECEIPT_TOTALS', [
        'receiptTotal'        => $receiptTotal,
        'sumSalesWithTax'     => $sumSalesWithTax,
        'sumPayments'         => $sumPayments,
        'difference_tax'      => round($receiptTotal - $sumSalesWithTax, 4),
        'difference_payment'  => round($receiptTotal - $sumPayments, 4),
    ]);

    /*
    |--------------------------------------------------------------------------
    | FDMS RULE #1
    |--------------------------------------------------------------------------
    */
    if (abs($receiptTotal - $sumSalesWithTax) > $tolerance) {
        throw new \Exception(
            "RCPT020 Prevention: receiptTotal ({$receiptTotal}) != SUM(salesAmountWithTax) ({$sumSalesWithTax})"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FDMS RULE #2
    |--------------------------------------------------------------------------
    */
    if (abs($receiptTotal - $sumPayments) > $tolerance) {
        throw new \Exception(
            "RCPT020 Prevention: receiptTotal ({$receiptTotal}) != SUM(paymentAmount) ({$sumPayments})"
        );
    }

    Log::info('VALIDATE_RECEIPT_TOTALS_PASSED');
}

    /*
    |--------------------------------------------------------------------------
    | Sign JSON String - FDMS API v7.2 Section 13.2
    |--------------------------------------------------------------------------
    | 1. Sign the RAW JSON string using openssl_sign with OPENSSL_ALGO_SHA256
    | 2. Generate SHA256 hash separately for the 'hash' field
    | NOTE: openssl_sign with OPENSSL_ALGO_SHA256 hashes internally before signing
    |--------------------------------------------------------------------------
    */
    private function signJsonString(string $json): array
    {
        Log::info('SIGNING_JSON_INPUT', [
            'json' => $json,
            'length' => strlen($json),
        ]);

        // Step 0: Validate certificate and private key
        $certPath = storage_path('app/zimra/device_certificate.pem');
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($certPath)) {
            throw new \Exception('RCPT025: Device certificate not found. Please register device first.');
        }
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('RCPT025: Device private key not found. Please register device first.');
        }

        // Load and validate certificate
        $certPem = file_get_contents($certPath);
        $cert = openssl_x509_read($certPem);
        if (!$cert) {
            throw new \Exception('RCPT025: Failed to read device certificate: ' . openssl_error_string());
        }

        // Check certificate expiry
        $certInfo = openssl_x509_parse($cert);
        if ($certInfo) {
            $validTo = $certInfo['validTo_time_t'] ?? 0;
            $validFrom = $certInfo['validFrom_time_t'] ?? 0;
            $now = time();
            
            Log::info('SIGNING_CERTIFICATE_INFO', [
                'subject' => $certInfo['subject'] ?? [],
                'issuer' => $certInfo['issuer'] ?? [],
                'valid_from' => date('Y-m-d H:i:s', $validFrom),
                'valid_to' => date('Y-m-d H:i:s', $validTo),
                'is_valid' => ($now >= $validFrom && $now <= $validTo),
                'days_until_expiry' => round(($validTo - $now) / 86400),
            ]);

            if ($now < $validFrom) {
                throw new \Exception('RCPT025: Device certificate not yet valid. Valid from: ' . date('Y-m-d H:i:s', $validFrom));
            }
            if ($now > $validTo) {
                throw new \Exception('RCPT025: Device certificate has EXPIRED. Expired on: ' . date('Y-m-d H:i:s', $validTo));
            }
        }

        // Load private key
        $privateKeyPem = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new \Exception('RCPT025: Failed to load private key: ' . openssl_error_string());
        }

        // Verify private key matches certificate
        $publicKeyFromCert = openssl_pkey_get_public($cert);
        $publicKeyFromPrivate = openssl_pkey_get_details($privateKey);
        $certKeyDetails = openssl_pkey_get_details($publicKeyFromCert);
        
        if ($publicKeyFromPrivate['key'] !== $certKeyDetails['key']) {
            Log::error('RCPT025: Private key does NOT match certificate public key');
            throw new \Exception('RCPT025: Private key does not match the registered certificate. Re-register the device.');
        }
        
        Log::info('SIGNING_KEY_VALIDATION', [
            'private_key_type' => $publicKeyFromPrivate['type'] ?? 'unknown',
            'private_key_bits' => $publicKeyFromPrivate['bits'] ?? 0,
            'key_match' => true,
        ]);

        // Step 1: Generate SHA256 hash for the 'hash' field
        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        Log::info('SIGNING_HASH_GENERATED', [
            'hash_base64' => $hashBase64,
            'hash_hex' => bin2hex($hashBinary),
        ]);

        // Step 3: Sign the RAW JSON string (openssl_sign hashes internally with OPENSSL_ALGO_SHA256)
        $signResult = openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$signResult) {
            throw new \Exception('Failed to sign receipt: ' . openssl_error_string());
        }

        $signatureBase64 = base64_encode($signatureBinary);

        Log::info('SIGNING_RESULT', [
            'hash_base64' => $hashBase64,
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signatureBinary),
        ]);

        // Step 4: Verify signature locally before sending (CRITICAL for RCPT025 debugging)
        $publicKey = openssl_pkey_get_public($cert);
        $verifyResult = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
        
        Log::info('SIGNING_LOCAL_VERIFY', [
            'verified' => $verifyResult === 1,
            'verify_result_code' => $verifyResult,
            'json_first_100_chars' => substr($json, 0, 100),
            'json_last_100_chars' => substr($json, -100),
        ]);
        
        if ($verifyResult !== 1) {
            $opensslError = openssl_error_string();
            Log::error('RCPT025_LOCAL_VERIFY_FAILED', [
                'openssl_error' => $opensslError,
                'verify_result_code' => $verifyResult,
                'json_length' => strlen($json),
            ]);
            throw new \Exception("RCPT025: Local signature verification FAILED. OpenSSL error: {$opensslError}. This indicates a signing problem.");
        }
        
        Log::info('RCPT025_LOCAL_VERIFY_PASSED', [
            'signature_verified' => true,
            'hash' => $hashBase64,
        ]);

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sign Receipt Data with Canonical JSON (Fix RCPT025) - DEPRECATED
    |--------------------------------------------------------------------------
    | Ensures canonical JSON formatting with 2 decimal places for all numbers
    |--------------------------------------------------------------------------
    */
    private function signReceiptDataCanonical(array $receiptData): array
    {
        // Build canonical receipt with exact field order and numeric formatting
        $canonicalReceipt = $this->buildCanonicalReceiptWithDecimals($receiptData);
        
        // Encode with flags to preserve formatting
        $json = json_encode($canonicalReceipt, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        Log::info('ZIMRA signReceiptDataCanonical - JSON to sign', [
            'json' => $json,
            'json_length' => strlen($json),
        ]);

        // Hash for payload field (separate from signing)
        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        Log::info('ZIMRA signReceiptDataCanonical - Hash', [
            'hash_base64' => $hashBase64,
        ]);

        // Load private key
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('Device private key not found. Please register device first.');
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing.');
        }

        // SIGN RAW JSON (NOT hashBinary) - openssl_sign with OPENSSL_ALGO_SHA256 will hash internally
        openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signatureBinary);

        Log::info('ZIMRA signReceiptDataCanonical - Signature', [
            'signature_base64' => $signatureBase64,
        ]);

        // Verify signature against public key before sending
        $certPath = storage_path('app/zimra/device_certificate.pem');
        if (file_exists($certPath)) {
            $cert = openssl_x509_read(file_get_contents($certPath));
            if ($cert) {
                $publicKey = openssl_pkey_get_public($cert);
                // Verify against raw JSON (same as signing)
                $verifyResult = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
                
                Log::info('ZIMRA signReceiptDataCanonical - Verification', [
                    'verified' => $verifyResult === 1,
                ]);
                
                if ($verifyResult !== 1) {
                    Log::error('ZIMRA signReceiptDataCanonical - Verification FAILED');
                }
            }
        }

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build Canonical Receipt with 2 Decimal Formatting
    |--------------------------------------------------------------------------
    */
    private function buildCanonicalReceiptWithDecimals(array $receiptData): array
    {
        $canonical = [];

        // Canonical field order per FDMS spec
        $fieldOrder = [
            'receiptType',
            'receiptCurrency',
            'receiptCounter',
            'receiptGlobalNo',
            'invoiceNo',
            'buyerData',
            'receiptNotes',
            'receiptDate',
            'creditDebitNote',
            'receiptLinesTaxInclusive',
            'receiptLines',
            'receiptTaxes',
            'receiptPayments',
            'receiptTotal',
            'receiptPrintForm',
            'fiscalDayNo',
        ];

        foreach ($fieldOrder as $field) {
            // Only include fields that exist and are not null
            if (!isset($receiptData[$field])) {
                continue;
            }

            $value = $receiptData[$field];
            
            // Skip null values
            if ($value === null) {
                continue;
            }
            
            if ($field === 'receiptLines') {
                $canonical[$field] = $this->formatReceiptLinesCanonical($value);
            } elseif ($field === 'receiptTaxes') {
                $canonical[$field] = $this->formatReceiptTaxesCanonical($value);
            } elseif ($field === 'receiptPayments') {
                $canonical[$field] = $this->formatReceiptPaymentsCanonical($value);
            } elseif ($field === 'receiptTotal') {
                // Ensure 2 decimal places as float (not string)
                $canonical[$field] = round((float) $value, 2);
            } elseif ($field === 'buyerData' && is_array($value)) {
                $canonical[$field] = $this->formatBuyerDataCanonical($value);
            } else {
                $canonical[$field] = $value;
            }
        }

        return $canonical;
    }

    private function formatReceiptLinesCanonical(array $lines): array
    {
        $result = [];
        $lineOrder = [
            'receiptLineNo',
            'receiptLineType',
            'receiptLineName',
            'receiptLineQuantity',
            'receiptLinePrice',
            'receiptLineTotal',
            'taxCode',
            'taxPercent',
            'taxID',
            'receiptLineHSCode',
        ];

        foreach ($lines as $line) {
            $canonicalLine = [];
            foreach ($lineOrder as $field) {
                if (!array_key_exists($field, $line)) {
                    continue;
                }
                $value = $line[$field];
                
                // Format numeric fields per FDMS spec as floats (not strings):
                // receiptLinePrice, receiptLineQuantity → 6 decimals
                // receiptLineTotal, taxPercent → 2 decimals
                if (in_array($field, ['receiptLinePrice', 'receiptLineQuantity'])) {
                    $canonicalLine[$field] = round((float) $value, 6);
                } elseif (in_array($field, ['receiptLineTotal', 'taxPercent'])) {
                    $canonicalLine[$field] = round((float) $value, 2);
                } else {
                    $canonicalLine[$field] = $value;
                }
            }
            $result[] = $canonicalLine;
        }
        return $result;
    }

    private function formatReceiptTaxesCanonical(array $taxes): array
    {
        $result = [];
        $taxOrder = ['taxCode', 'taxPercent', 'taxID', 'taxAmount', 'salesAmountWithTax'];

        foreach ($taxes as $tax) {
            $canonicalTax = [];
            foreach ($taxOrder as $field) {
                if (!array_key_exists($field, $tax)) {
                    continue;
                }
                $value = $tax[$field];
                
                // Format numeric fields with 2 decimals as floats (not strings)
                if (in_array($field, ['taxPercent', 'taxAmount', 'salesAmountWithTax'])) {
                    $canonicalTax[$field] = round((float) $value, 2);
                } else {
                    $canonicalTax[$field] = $value;
                }
            }
            $result[] = $canonicalTax;
        }
        return $result;
    }

    private function formatReceiptPaymentsCanonical(array $payments): array
    {
        $result = [];
        $paymentOrder = ['moneyTypeCode', 'paymentAmount'];

        foreach ($payments as $payment) {
            $canonicalPayment = [];
            foreach ($paymentOrder as $field) {
                if (!array_key_exists($field, $payment)) {
                    continue;
                }
                $value = $payment[$field];
                
                // Format numeric fields with 2 decimals as floats (not strings)
                if ($field === 'paymentAmount') {
                    $canonicalPayment[$field] = round((float) $value, 2);
                } else {
                    $canonicalPayment[$field] = $value;
                }
            }
            $result[] = $canonicalPayment;
        }
        return $result;
    }

    private function formatBuyerDataCanonical(array $buyerData): array
    {
        $buyerOrder = ['buyerRegisterName', 'buyerTradeName', 'vatNumber', 'buyerTIN', 'buyerContacts', 'buyerAddress'];
        $canonical = [];
        foreach ($buyerOrder as $field) {
            if (array_key_exists($field, $buyerData)) {
                $canonical[$field] = $buyerData[$field];
            }
        }
        return $canonical;
    }

    /*
    |--------------------------------------------------------------------------
    | Sign Receipt Data per FDMS Spec Section 13.2
    |--------------------------------------------------------------------------
    | Creates canonical JSON with ordered fields, SHA256 hash, ECDSA signature
    |--------------------------------------------------------------------------
    */
    private function signReceiptData(array $receiptData): array
    {
        // Build canonical receipt data with ordered fields per FDMS spec section 13.2
        $canonicalReceipt = $this->buildCanonicalReceiptForSigning($receiptData);
        
        // JSON encode with consistent formatting
        $json = json_encode($canonicalReceipt, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        Log::debug('ZIMRA signReceiptData - Canonical JSON', [
            'json' => $json,
            'json_length' => strlen($json),
        ]);

        // SHA256 hash
        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        Log::debug('ZIMRA signReceiptData - Hash', [
            'hash_hex' => bin2hex($hashBinary),
            'hash_base64' => $hashBase64,
        ]);

        // Load private key
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('Device private key not found. Please register device first.');
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing.');
        }

        // Sign with ECDSA (prime256v1) using SHA256
        openssl_sign($json, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signatureBinary);

        Log::debug('ZIMRA signReceiptData - Signature', [
            'signature_base64' => $signatureBase64,
            'signature_length' => strlen($signatureBinary),
        ]);

        // Verify signature locally
        $certPath = storage_path('app/zimra/device_certificate.pem');
        if (file_exists($certPath)) {
            $cert = openssl_x509_read(file_get_contents($certPath));
            if ($cert) {
                $publicKey = openssl_pkey_get_public($cert);
                $verifyResult = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
                Log::debug('ZIMRA signReceiptData - Local Verification', [
                    'valid' => $verifyResult === 1,
                ]);
            }
        }

        return [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build Canonical Receipt for Signing (FDMS Spec Section 13.2)
    |--------------------------------------------------------------------------
    | Orders fields in the exact sequence required by FDMS for hash consistency
    |--------------------------------------------------------------------------
    */
    private function buildCanonicalReceiptForSigning(array $receiptData): array
    {
        // Canonical field order per FDMS spec section 13.2
        $canonical = [];

        // Required fields in order
        $fieldOrder = [
            'receiptType',
            'receiptCurrency',
            'receiptCounter',
            'receiptGlobalNo',
            'invoiceNo',
            'buyerData',
            'receiptNotes',
            'receiptDate',
            'creditDebitNote',
            'receiptLinesTaxInclusive',
            'receiptLines',
            'receiptTaxes',
            'receiptPayments',
            'receiptTotal',
            'receiptPrintForm',
            'fiscalDayNo',
        ];

        foreach ($fieldOrder as $field) {
            if (array_key_exists($field, $receiptData)) {
                $value = $receiptData[$field];
                
                // Handle nested arrays (receiptLines, receiptTaxes, receiptPayments)
                if ($field === 'receiptLines' && is_array($value)) {
                    $canonical[$field] = $this->canonicalizeReceiptLines($value);
                } elseif ($field === 'receiptTaxes' && is_array($value)) {
                    $canonical[$field] = $this->canonicalizeReceiptTaxes($value);
                } elseif ($field === 'receiptPayments' && is_array($value)) {
                    $canonical[$field] = $this->canonicalizeReceiptPayments($value);
                } elseif ($field === 'buyerData' && is_array($value)) {
                    $canonical[$field] = $this->canonicalizeBuyerData($value);
                } else {
                    $canonical[$field] = $value;
                }
            }
        }

        return $canonical;
    }

    private function canonicalizeReceiptLines(array $lines): array
    {
        $result = [];
        $lineOrder = [
            'receiptLineNo',
            'receiptLineType',
            'receiptLineName',
            'receiptLineQuantity',
            'receiptLinePrice',
            'receiptLineTotal',
            'taxCode',
            'taxPercent',
            'taxID',
            'receiptLineHSCode',
        ];

        foreach ($lines as $line) {
            $canonicalLine = [];
            foreach ($lineOrder as $field) {
                if (array_key_exists($field, $line)) {
                    $canonicalLine[$field] = $line[$field];
                }
            }
            $result[] = $canonicalLine;
        }
        return $result;
    }

    private function canonicalizeReceiptTaxes(array $taxes): array
    {
        $result = [];
        $taxOrder = ['taxCode', 'taxPercent', 'taxID', 'taxAmount', 'salesAmountWithTax'];

        foreach ($taxes as $tax) {
            $canonicalTax = [];
            foreach ($taxOrder as $field) {
                if (array_key_exists($field, $tax)) {
                    $canonicalTax[$field] = $tax[$field];
                }
            }
            $result[] = $canonicalTax;
        }
        return $result;
    }

    private function canonicalizeReceiptPayments(array $payments): array
    {
        $result = [];
        $paymentOrder = ['moneyTypeCode', 'paymentAmount'];

        foreach ($payments as $payment) {
            $canonicalPayment = [];
            foreach ($paymentOrder as $field) {
                if (array_key_exists($field, $payment)) {
                    $canonicalPayment[$field] = $payment[$field];
                }
            }
            $result[] = $canonicalPayment;
        }
        return $result;
    }

    private function canonicalizeBuyerData(array $buyerData): array
    {
        $buyerOrder = ['buyerRegisterName', 'buyerTradeName', 'vatNumber', 'buyerTIN', 'buyerContacts', 'buyerAddress'];
        $canonical = [];
        foreach ($buyerOrder as $field) {
            if (array_key_exists($field, $buyerData)) {
                $canonical[$field] = $buyerData[$field];
            }
        }
        return $canonical;
    }

    /*
    |--------------------------------------------------------------------------
    | Build & Validate Receipt with Tax Calculations
    |--------------------------------------------------------------------------
    | Handles tax-inclusive vs tax-exclusive calculations per ZIMRA spec.
    | Default: receiptLinesTaxInclusive = false
    |--------------------------------------------------------------------------
    */
    private function buildAndValidateReceipt(array $receiptData, FiscalDay $fiscalDay, array $fdmsTaxes = []): array
    {
        // Determine receiptLinesTaxInclusive based on VAT status and tax rates
        $configResponse = $this->getConfig();
        $vatNumber = $configResponse['vatNumber'] ?? null;

        // Check if all receipt line taxPercent values are 0.0
        $allTaxPercentZero = true;
        foreach ($receiptData['receiptLines'] ?? [] as $line) {
            $lineTaxPercent = (float) ($line['taxPercent'] ?? 0.0);
            if ($lineTaxPercent > 0.0) {
                $allTaxPercentZero = false;
                break;
            }
        }

        if (!$vatNumber || $vatNumber === 'NOT_REGISTERED' || $allTaxPercentZero) {
            // Non-VAT device OR all tax rates are 0% → use FALSE
            $taxInclusive = false;
        } else {
            // VAT taxpayer with non-zero tax → use TRUE
            $taxInclusive = true;
        }

        $receiptData['receiptLinesTaxInclusive'] = $taxInclusive;

        // Build tax lookup from FDMS config
        $taxLookup = [];
        foreach ($fdmsTaxes as $tax) {
            $code = $tax['taxCode'] ?? 'A';
            $taxLookup[$code] = [
                'taxID' => $tax['taxID'] ?? 1,
                'taxPercent' => (float) ($tax['taxPercent'] ?? 0),
            ];
        }

        Log::debug('Receipt Tax Mode', [
            'tax_inclusive' => $taxInclusive,
            'fdms_tax_lookup' => $taxLookup,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Calculate Line Totals & Taxes (using FDMS taxID)
        |--------------------------------------------------------------------------
        */
        $receiptLines = $receiptData['receiptLines'] ?? [];
        $taxTotals = []; // Group by taxCode
        $calculatedReceiptTotal = 0;

        foreach ($receiptLines as $index => &$line) {
            $price = (float) ($line['receiptLinePrice'] ?? 0);
            $quantity = (float) ($line['receiptLineQuantity'] ?? 1);
            $taxCode = $line['taxCode'] ?? 'A';
            
            // Get taxID and taxPercent from FDMS config, not hardcoded
            $fdmsTax = $taxLookup[$taxCode] ?? ['taxID' => 1, 'taxPercent' => 15.0];
            $taxID = $line['taxID'] ?? $fdmsTax['taxID'];
            $taxPercent = (float) ($line['taxPercent'] ?? $fdmsTax['taxPercent']);
            
            // Update line with correct taxID from FDMS
            $line['taxID'] = $taxID;
            $line['taxPercent'] = $taxPercent;

            if ($taxInclusive) {
                // receiptLineTotal already includes tax
                $lineTotal = $price * $quantity;
                $taxAmount = $lineTotal * $taxPercent / (100 + $taxPercent);
                $salesAmountWithTax = $lineTotal;
            } else {
                // receiptLineTotal is tax-exclusive, add tax on top
                $lineTotal = $price * $quantity;
                $taxAmount = $lineTotal * $taxPercent / 100;
                $salesAmountWithTax = $lineTotal + $taxAmount;
            }

            // Round to 2 decimal places
            $lineTotal = round($lineTotal, 2);
            $taxAmount = round($taxAmount, 2);
            $salesAmountWithTax = round($salesAmountWithTax, 2);

            // Update line
            $line['receiptLineTotal'] = $lineTotal;

            // Accumulate tax totals by taxCode
            $taxKey = "{$taxCode}_{$taxPercent}_{$taxID}";
            if (!isset($taxTotals[$taxKey])) {
                $taxTotals[$taxKey] = [
                    'taxCode' => $taxCode,
                    'taxPercent' => $taxPercent,
                    'taxID' => $taxID,
                    'taxAmount' => 0,
                    'salesAmountWithTax' => 0,
                ];
            }
            $taxTotals[$taxKey]['taxAmount'] += $taxAmount;
            $taxTotals[$taxKey]['salesAmountWithTax'] += $salesAmountWithTax;

            // Accumulate receipt total
            $calculatedReceiptTotal += $salesAmountWithTax;

            Log::debug('Receipt Line Calculation', [
                'line_no' => $line['receiptLineNo'] ?? $index + 1,
                'price' => $price,
                'quantity' => $quantity,
                'tax_percent' => $taxPercent,
                'tax_inclusive' => $taxInclusive,
                'line_total' => $lineTotal,
                'tax_amount' => $taxAmount,
                'sales_amount_with_tax' => $salesAmountWithTax,
            ]);
        }
        unset($line);

        $receiptData['receiptLines'] = $receiptLines;

        // Round accumulated totals
        foreach ($taxTotals as &$tax) {
            $tax['taxAmount'] = round($tax['taxAmount'], 2);
            $tax['salesAmountWithTax'] = round($tax['salesAmountWithTax'], 2);
        }
        unset($tax);

        $receiptData['receiptTaxes'] = array_values($taxTotals);
        $calculatedReceiptTotal = round($calculatedReceiptTotal, 2);

        Log::debug('Receipt Tax Totals', [
            'taxes' => $receiptData['receiptTaxes'],
            'calculated_receipt_total' => $calculatedReceiptTotal,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Set Receipt Total
        |--------------------------------------------------------------------------
        */
        $receiptData['receiptTotal'] = $calculatedReceiptTotal;

        /*
        |--------------------------------------------------------------------------
        | Validate Counters
        |--------------------------------------------------------------------------
        */
        $expectedCounter = ($fiscalDay->receipt_counter ?? 0) + 1;
        $receiptCounter = $receiptData['receiptCounter'] ?? $expectedCounter;
        $receiptGlobalNo = $receiptData['receiptGlobalNo'] ?? $expectedCounter;

        // Auto-set if not provided
        $receiptData['receiptCounter'] = $receiptCounter;
        $receiptData['receiptGlobalNo'] = $receiptGlobalNo;

        Log::debug('Receipt Counters', [
            'expected_counter' => $expectedCounter,
            'receipt_counter' => $receiptCounter,
            'receipt_global_no' => $receiptGlobalNo,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validate Payments Match Total
        |--------------------------------------------------------------------------
        */
        $payments = $receiptData['receiptPayments'] ?? [];
        $totalPayments = 0;

        foreach ($payments as $payment) {
            $totalPayments += (float) ($payment['paymentAmount'] ?? 0);
        }
        $totalPayments = round($totalPayments, 2);

        Log::debug('Receipt Payments Validation', [
            'receipt_total' => $calculatedReceiptTotal,
            'total_payments' => $totalPayments,
            'payment_count' => count($payments),
        ]);

        if (abs($totalPayments - $calculatedReceiptTotal) > 0.01) {
            Log::error('Receipt Payment Mismatch', [
                'receipt_total' => $calculatedReceiptTotal,
                'total_payments' => $totalPayments,
                'difference' => $calculatedReceiptTotal - $totalPayments,
            ]);
            throw new \Exception(
                "Payment mismatch: receiptTotal ({$calculatedReceiptTotal}) != sum of payments ({$totalPayments})"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate salesAmountWithTax Sum Matches receiptTotal
        |--------------------------------------------------------------------------
        */
        $totalSalesWithTax = 0;
        foreach ($receiptData['receiptTaxes'] as $tax) {
            $totalSalesWithTax += $tax['salesAmountWithTax'];
        }
        $totalSalesWithTax = round($totalSalesWithTax, 2);

        Log::debug('Receipt Tax Total Validation', [
            'receipt_total' => $calculatedReceiptTotal,
            'sum_sales_with_tax' => $totalSalesWithTax,
        ]);

        if (abs($totalSalesWithTax - $calculatedReceiptTotal) > 0.01) {
            Log::error('Receipt Tax Sum Mismatch', [
                'receipt_total' => $calculatedReceiptTotal,
                'sum_sales_with_tax' => $totalSalesWithTax,
                'difference' => $calculatedReceiptTotal - $totalSalesWithTax,
            ]);
            throw new \Exception(
                "Tax mismatch: receiptTotal ({$calculatedReceiptTotal}) != sum of salesAmountWithTax ({$totalSalesWithTax})"
            );
        }

        Log::info('Receipt Validated Successfully', [
            'receipt_total' => $calculatedReceiptTotal,
            'tax_inclusive' => $taxInclusive,
            'line_count' => count($receiptLines),
            'tax_groups' => count($receiptData['receiptTaxes']),
        ]);

        return $receiptData;
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
                $taxCode = $tax['taxCode'] ?? '';
                $key = $taxCode . '_' . ($tax['taxPercent'] ?? 0);

                if (!isset($counters[$key])) {
                    // SaleByTax counters must NOT include fiscalCounterMoneyType per ZIMRA FDMS v7.2
                    $counters[$key] = [
                        'fiscalCounterType' => 'SaleByTax',
                        'fiscalCounterCurrency' => $receiptData['receiptCurrency'] ?? 'USD',
                        'fiscalCounterTaxPercent' => $tax['taxPercent'] ?? 0,
                        'fiscalCounterTaxID' => $tax['taxID'] ?? 1,
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

    /**
     * Get previous receipt hash for chaining
     */
    private function getPreviousReceiptHash(int $deviceId, int $currentReceiptCounter, int $fiscalDayNo): ?string
    {
        // First receipt in fiscal day has no previous hash
        if ($currentReceiptCounter <= 1) {
            return null;
        }
        
        // Get the previous receipt (counter - 1) from the SAME fiscal day
        $previousReceipt = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDayNo)
            ->where('receipt_counter', $currentReceiptCounter - 1)
            ->first();
        
        if (!$previousReceipt) {
            Log::warning('PREVIOUS_RECEIPT_NOT_FOUND', [
                'device_id' => $deviceId,
                'current_counter' => $currentReceiptCounter,
                'looking_for_counter' => $currentReceiptCounter - 1,
            ]);
            return null;
        }
        
        // Extract hash from receiptDeviceSignature JSON
        $signature = $previousReceipt->receipt_signature;
        if (is_string($signature)) {
            $signature = json_decode($signature, true);
        }
        
        $hash = $signature['hash'] ?? null;
        
        Log::info('PREVIOUS_RECEIPT_HASH_RETRIEVED', [
            'device_id' => $deviceId,
            'current_counter' => $currentReceiptCounter,
            'previous_counter' => $currentReceiptCounter - 1,
            'previous_receipt_id' => $previousReceipt->id,
            'previous_hash' => $hash,
        ]);
        
        return $hash;
    }
    
    /**
     * Build canonical string for signature per FDMS spec section 13.2.1
     * 
     * Format: deviceID||receiptType||receiptCurrency||receiptGlobalNo||receiptDate||receiptTotal(cents)||receiptTaxes||previousReceiptHash
     * 
     * @param array $receipt Receipt data
     * @param int $deviceId Device ID
     * @param int $fiscalDayNo Fiscal day number
     * @return string Canonical concatenated string
     */
    private function buildCanonicalStringForSignature(array $receipt, int $deviceId, int $fiscalDayNo): string
    {
        $parts = [];
        
        // 1. deviceID (integer)
        $parts[] = (string) $deviceId;
        
        // 2. receiptType (uppercase)
        $parts[] = strtoupper($receipt['receiptType'] ?? 'FISCALINVOICE');
        
        // 3. receiptCurrency (uppercase)
        $parts[] = strtoupper($receipt['receiptCurrency'] ?? 'USD');
        
        // 4. receiptGlobalNo (integer)
        $parts[] = (string) ($receipt['receiptGlobalNo'] ?? 0);
        
        // 5. receiptDate (ISO 8601 format)
        $parts[] = $receipt['receiptDate'] ?? '';
        
        // 6. receiptTotal (in CENTS - multiply by 100 and remove decimals)
        $receiptTotal = $receipt['receiptTotal'] ?? '0.00';
        $receiptTotalCents = (int) round((float) $receiptTotal * 100);
        $parts[] = (string) $receiptTotalCents;
        
        // 7. receiptTaxes (concatenated: taxCode||taxPercent||taxAmount||salesAmountWithTax)
        $receiptTaxes = $receipt['receiptTaxes'] ?? [];
        $taxesString = $this->buildCanonicalTaxesString($receiptTaxes);
        $parts[] = $taxesString;
        
        // 8. previousReceiptHash (required for non-first receipts in fiscal day)
        // Get previous receipt hash from database
        $previousHash = $this->getPreviousReceiptHash($deviceId, $receipt['receiptCounter'] ?? 0, $fiscalDayNo);
        if ($previousHash) {
            $parts[] = $previousHash;
        }
        
        // Concatenate with no separator
        $canonicalString = implode('', $parts);
        
        Log::info('CANONICAL_STRING_PARTS', [
            'deviceID' => $parts[0],
            'receiptType' => $parts[1],
            'receiptCurrency' => $parts[2],
            'receiptGlobalNo' => $parts[3],
            'receiptDate' => $parts[4],
            'receiptTotal_cents' => $parts[5],
            'receiptTaxes' => $parts[6],
            'previousReceiptHash' => $parts[7] ?? 'NOT_INCLUDED',
            'full_string' => $canonicalString,
        ]);
        
        return $canonicalString;
    }
    
    /**
     * Build canonical taxes string per FDMS spec section 13.2.1
     */
    private function buildCanonicalTaxesString(array $taxes): string
    {
        if (empty($taxes)) {
            return '';
        }
        
        // Sort taxes by taxID ascending, then taxCode alphabetically
        usort($taxes, function ($a, $b) {
            $taxIdCompare = ($a['taxID'] ?? 0) <=> ($b['taxID'] ?? 0);
            if ($taxIdCompare !== 0) {
                return $taxIdCompare;
            }
            
            $taxCodeA = $a['taxCode'] ?? '';
            $taxCodeB = $b['taxCode'] ?? '';
            
            if ($taxCodeA === '' && $taxCodeB !== '') return -1;
            if ($taxCodeA !== '' && $taxCodeB === '') return 1;
            
            return strcmp($taxCodeA, $taxCodeB);
        });
        
        $taxParts = [];
        
        foreach ($taxes as $tax) {
            $taxCode = $tax['taxCode'] ?? '';
            $taxPercent = $tax['taxPercent'] ?? '0.00';
            $taxAmount = $tax['taxAmount'] ?? '0.00';
            $salesAmountWithTax = $tax['salesAmountWithTax'] ?? '0.00';
            
            // Format taxPercent with .00 suffix
            $taxPercentFloat = (float) $taxPercent;
            $taxPercentFormatted = number_format($taxPercentFloat, 2, '.', '');
            
            // Convert amounts to cents
            $taxAmountCents = (int) round((float) $taxAmount * 100);
            $salesAmountWithTaxCents = (int) round((float) $salesAmountWithTax * 100);
            
            // Concatenate directly (no separators): taxCode + taxPercent + taxAmount + salesAmountWithTax
            // Note: || in spec is documentation notation showing field boundaries, not literal separators
            $taxString = $taxCode . $taxPercentFormatted . $taxAmountCents . $salesAmountWithTaxCents;
            $taxParts[] = $taxString;
        }
        
        return implode('', $taxParts);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Fiscal Day Status (wrapper for ReceiptService)
    |--------------------------------------------------------------------------
    */
    public function getFiscalDayStatus(int $deviceId): array
    {
        $status = $this->getStatus($deviceId);
        
        if (isset($status['error'])) {
            throw new \Exception('Failed to get device status from FDMS');
        }

        return [
            'fiscalDayStatus' => $status['fiscalDayStatus'] ?? 'Unknown',
            'fiscalDayNo' => $status['fiscalDayNo'] ?? null,
            'lastFiscalDayNo' => $status['lastFiscalDayNo'] ?? 1,
            'lastReceiptGlobalNo' => $status['lastReceiptGlobalNo'] ?? 0,
            'lastReceiptCounter' => $status['lastReceiptCounter'] ?? 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Receipt to FDMS (wrapper for ReceiptService)
    |--------------------------------------------------------------------------
    | This method handles only the FDMS communication.
    | Counter management and signature are handled by ReceiptService.
    */
    public function submitReceiptToFdms(array $receiptData, int $deviceId): array
    {
        $zimraConfig = ZimraConfig::where('device_id', $deviceId)->first();

        if (!$zimraConfig) {
            throw new \Exception("No ZIMRA configuration found for device {$deviceId}");
        }

        $baseUrl = $zimraConfig->base_url;
        $mtls = $this->prepareMtlsCertificates($zimraConfig);

        // Build the receipt payload
        $payload = [
            'receipt' => [
                'receiptType' => $receiptData['receiptType'],
                'receiptCurrency' => $receiptData['receiptCurrency'],
                'receiptCounter' => $receiptData['receiptCounter'],
                'receiptGlobalNo' => $receiptData['receiptGlobalNo'],
                'invoiceNo' => $receiptData['invoiceNo'] ?? 'INV-' . $receiptData['receiptGlobalNo'],
                'receiptDate' => $receiptData['receiptDate'],
                'receiptLinesTaxInclusive' => $receiptData['receiptLinesTaxInclusive'] ?? false,
                'receiptLines' => $receiptData['receiptLines'],
                'receiptTaxes' => $receiptData['receiptTaxes'],
                'receiptPayments' => $receiptData['receiptPayments'],
                'receiptTotal' => $receiptData['receiptTotal'],
                'receiptPrintForm' => $receiptData['receiptPrintForm'] ?? 'Receipt48',
                'receiptDeviceSignature' => $receiptData['receiptDeviceSignature'],
            ]
        ];

        // Add optional fields
        if (!empty($receiptData['buyerData'])) {
            $payload['receipt']['buyerData'] = $receiptData['buyerData'];
        }
        if (!empty($receiptData['receiptNotes'])) {
            $payload['receipt']['receiptNotes'] = $receiptData['receiptNotes'];
        }
        if (!empty($receiptData['creditDebitNote'])) {
            $payload['receipt']['creditDebitNote'] = $receiptData['creditDebitNote'];
        }

        Log::info('FINAL_JSON_SENT', [
            'json' => json_encode($payload),
            'length' => strlen(json_encode($payload)),
            'device_id' => $deviceId,
            'endpoint' => "/Device/v1/{$deviceId}/SubmitReceipt",
        ]);

        $response = Http::withOptions($mtls)->withHeaders([
            'DeviceModelName' => $zimraConfig->device_model,
            'DeviceModelVersion' => $zimraConfig->device_version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/Device/v1/{$deviceId}/SubmitReceipt", $payload);

        Log::info('ZIMRA SubmitReceipt Raw Response', [
            'status' => $response->status(),
            'raw_body' => $response->body(),
        ]);

        if (!$response->successful()) {
            return [
                'error' => true,
                'status' => $response->status(),
                'message' => 'FDMS request failed',
                'body' => $response->json(),
            ];
        }

        $result = $response->json();

        // Log validation errors
        if (!empty($result['validationErrors'])) {
            foreach ($result['validationErrors'] as $error) {
                $errorCode = $error['validationErrorCode'] ?? 'UNKNOWN';
                $errorColor = $error['validationErrorColor'] ?? 'Unknown';
                Log::error('ZIMRA Validation Error', [
                    'validationErrorCode' => $errorCode,
                    'validationErrorColor' => $errorColor,
                    'errorMessage' => self::VALIDATION_ERROR_MESSAGES[$errorCode] ?? "Unknown error ({$errorCode})",
                    'receipt_id' => $result['receiptID'] ?? null,
                ]);
            }
        }

        return $result;
    }
}
