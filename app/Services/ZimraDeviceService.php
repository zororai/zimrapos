<?php

namespace App\Services;

use App\Models\FiscalDay;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZimraDeviceService
{
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
            $zimraConfig->update([
                'qr_url' => $response['qrUrl'] ?? null,
                'taxes' => $response['taxes'] ?? null,
                'device_operating_mode' => $response['deviceOperatingMode'] ?? null,
                'certificate_valid_till' => isset($response['certificateValidTill']) 
                    ? \Carbon\Carbon::parse($response['certificateValidTill']) 
                    : null,
            ]);
            Log::info('ZIMRA Config updated from GetConfig', ['qr_url' => $response['qrUrl'] ?? null]);
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

        // Get current open fiscal day OR last fiscal day that needs retry
        $fiscalDay = FiscalDay::getCurrentOpen($deviceId);
        
        // If no open day, check ZIMRA status
        if (!$fiscalDay) {
            $zimraStatus = $this->getStatus();
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
        Log::debug('ZIMRA CloseDay - Payload before signing', [
            'payload' => $payload,
        ]);

        // Sign the payload - required by ZIMRA
        $payload['fiscalDayDeviceSignature'] = $this->signData($payload);

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
            
            $statusResponse = $this->getStatus();
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
                ]);
                return [
                    'error' => true,
                    'message' => 'Fiscal day close failed: ' . ($statusResponse['fiscalDayClosingErrorCode'] ?? 'unknown'),
                    'zimra_status' => $statusResponse,
                ];
            }

            // FiscalDayCloseInitiated - continue polling
            if ($currentStatus !== 'FiscalDayCloseInitiated') {
                Log::warning('ZIMRA CloseDay - Unexpected status during polling', [
                    'status' => $currentStatus,
                ]);
            }
        }

        // Update database
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
    | Build CloseDay Payload from Stored Receipts
    |--------------------------------------------------------------------------
    | Calculates fiscalDayCounters dynamically from receipts table.
    | Validates totals match before returning payload.
    |--------------------------------------------------------------------------
    */
    private function buildCloseDayPayload(FiscalDay $fiscalDay, int $deviceId): array
    {
        // Get all valid receipts for this fiscal day
        $receipts = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->get();

        Log::info('CloseDay - Building payload from receipts', [
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
        | 1️⃣ Calculate receiptCounter (last receipt counter for this day)
        |--------------------------------------------------------------------------
        */
        $lastReceipt = $receipts->sortByDesc('receipt_counter')->first();
        $receiptCounter = $lastReceipt ? $lastReceipt->receipt_counter : 0;

        Log::debug('CloseDay - Receipt counter from last receipt', [
            'receipt_counter' => $receiptCounter,
            'last_receipt_id' => $lastReceipt?->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Calculate fiscalDayCounters from receipts
        |--------------------------------------------------------------------------
        | Group by: taxID, taxPercent, paymentMethod (moneyType)
        |--------------------------------------------------------------------------
        */
        $counters = [];
        $totalReceiptValue = 0;

        foreach ($receipts as $receipt) {
            $receiptTaxes = $receipt->receipt_taxes ?? [];
            $receiptPayments = $receipt->receipt_payments ?? [];
            $receiptTotal = (float) $receipt->receipt_total;
            $currency = $receipt->receipt_currency ?? 'USD';

            $totalReceiptValue += $receiptTotal;

            // Process each tax group in the receipt
            foreach ($receiptTaxes as $tax) {
                $taxCode = $tax['taxCode'] ?? 'A';
                $taxPercent = (float) ($tax['taxPercent'] ?? 0);
                $taxID = (int) ($tax['taxID'] ?? 1);
                $salesAmountWithTax = (float) ($tax['salesAmountWithTax'] ?? 0);

                // SaleByTax counter (no moneyType per ZIMRA spec)
                $taxKey = "SaleByTax_{$taxID}_{$taxPercent}";
                if (!isset($counters[$taxKey])) {
                    $counters[$taxKey] = [
                        'fiscalCounterType' => 'SaleByTax',
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterTaxPercent' => $taxPercent,
                        'fiscalCounterTaxID' => $taxID,
                        'fiscalCounterValue' => 0,
                    ];
                }
                $counters[$taxKey]['fiscalCounterValue'] += $salesAmountWithTax;
            }

            // Process each payment method
            foreach ($receiptPayments as $payment) {
                $moneyType = $payment['moneyTypeCode'] ?? 'Cash';
                $paymentAmount = (float) ($payment['paymentAmount'] ?? 0);

                // SaleByMoneyType counter
                $paymentKey = "SaleByMoneyType_{$moneyType}";
                if (!isset($counters[$paymentKey])) {
                    $counters[$paymentKey] = [
                        'fiscalCounterType' => 'SaleByMoneyType',
                        'fiscalCounterCurrency' => $currency,
                        'fiscalCounterMoneyType' => $moneyType,
                        'fiscalCounterValue' => 0,
                    ];
                }
                $counters[$paymentKey]['fiscalCounterValue'] += $paymentAmount;
            }
        }

        // Round all counter values
        foreach ($counters as &$counter) {
            $counter['fiscalCounterValue'] = round($counter['fiscalCounterValue'], 2);
        }
        unset($counter);

        // Filter out zero-value counters
        $filteredCounters = array_filter($counters, function ($c) {
            return $c['fiscalCounterValue'] > 0;
        });

        $fiscalDayCounters = array_values($filteredCounters);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Validate Totals
        |--------------------------------------------------------------------------
        */
        $totalSalesByTax = 0;
        $totalSalesByMoney = 0;

        foreach ($fiscalDayCounters as $counter) {
            if ($counter['fiscalCounterType'] === 'SaleByTax') {
                $totalSalesByTax += $counter['fiscalCounterValue'];
            }
            if ($counter['fiscalCounterType'] === 'SaleByMoneyType') {
                $totalSalesByMoney += $counter['fiscalCounterValue'];
            }
        }

        $totalSalesByTax = round($totalSalesByTax, 2);
        $totalSalesByMoney = round($totalSalesByMoney, 2);
        $totalReceiptValue = round($totalReceiptValue, 2);

        Log::info('CloseDay - Payload validation', [
            'receipt_counter' => $receiptCounter,
            'total_receipt_value' => $totalReceiptValue,
            'total_sales_by_tax' => $totalSalesByTax,
            'total_sales_by_money' => $totalSalesByMoney,
            'counter_count' => count($fiscalDayCounters),
        ]);

        // Log each counter for debugging
        foreach ($fiscalDayCounters as $index => $counter) {
            Log::debug("CloseDay - Counter #{$index}", [
                'type' => $counter['fiscalCounterType'],
                'value' => $counter['fiscalCounterValue'],
                'taxID' => $counter['fiscalCounterTaxID'] ?? null,
                'taxPercent' => $counter['fiscalCounterTaxPercent'] ?? null,
                'moneyType' => $counter['fiscalCounterMoneyType'] ?? null,
            ]);
        }

        // Validate: SalesByTax should equal total receipt value
        if (abs($totalSalesByTax - $totalReceiptValue) > 0.01) {
            Log::error('CloseDay - SalesByTax mismatch', [
                'total_sales_by_tax' => $totalSalesByTax,
                'total_receipt_value' => $totalReceiptValue,
                'difference' => $totalSalesByTax - $totalReceiptValue,
            ]);
            throw new \Exception(
                "CloseDay validation failed: SalesByTax ({$totalSalesByTax}) != totalReceiptValue ({$totalReceiptValue})"
            );
        }

        // Validate: SalesByMoney should equal total receipt value
        if (abs($totalSalesByMoney - $totalReceiptValue) > 0.01) {
            Log::error('CloseDay - SalesByMoney mismatch', [
                'total_sales_by_money' => $totalSalesByMoney,
                'total_receipt_value' => $totalReceiptValue,
                'difference' => $totalSalesByMoney - $totalReceiptValue,
            ]);
            throw new \Exception(
                "CloseDay validation failed: SalesByMoney ({$totalSalesByMoney}) != totalReceiptValue ({$totalReceiptValue})"
            );
        }

        // Validate: receiptCounter should match receipt count or be > 0
        if ($receiptCounter <= 0 && $receipts->isNotEmpty()) {
            Log::error('CloseDay - Invalid receipt counter', [
                'receipt_counter' => $receiptCounter,
                'receipt_count' => $receipts->count(),
            ]);
            throw new \Exception(
                "CloseDay validation failed: receiptCounter ({$receiptCounter}) is invalid"
            );
        }

        $payload = [
            'fiscalDayNo' => $fiscalDay->fiscal_day_no,
            'fiscalDayCounters' => $fiscalDayCounters,
            'receiptCounter' => $receiptCounter,
        ];

        Log::info('CloseDay - Final payload built', [
            'fiscal_day_no' => $payload['fiscalDayNo'],
            'receipt_counter' => $payload['receiptCounter'],
            'counter_count' => count($payload['fiscalDayCounters']),
        ]);

        return $payload;
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
            throw new \Exception(
                "FDMS fiscal day not open. Current status: {$fdmsFiscalDayStatus}"
            );
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
        $fdmsTaxes = $this->getFdmsTaxConfig($zimraConfig);
        
        Log::info('ZIMRA SubmitReceipt - Tax Config from FDMS', [
            'taxes' => $fdmsTaxes,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Validate Invoice Number Uniqueness
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
        | 4️⃣ Get Correct Receipt Counter from Database (using FDMS fiscal day)
        |--------------------------------------------------------------------------
        */
        $lastReceipt = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDayNo)
            ->orderBy('receipt_counter', 'desc')
            ->first();
        
        $lastReceiptCounter = $lastReceipt ? (int) $lastReceipt->receipt_counter : 0;
        $nextReceiptCounter = $lastReceiptCounter + 1;

        // Get global counter (across all fiscal days for this device)
        $lastGlobalReceipt = Receipt::where('device_id', $deviceId)
            ->orderBy('receipt_global_no', 'desc')
            ->first();
        $lastGlobalNo = $lastGlobalReceipt ? (int) $lastGlobalReceipt->receipt_global_no : 0;
        $nextGlobalNo = $lastGlobalNo + 1;

        Log::info('ZIMRA SubmitReceipt - Counter Calculation', [
            'last_receipt_counter' => $lastReceiptCounter,
            'next_receipt_counter' => $nextReceiptCounter,
            'last_global_no' => $lastGlobalNo,
            'next_global_no' => $nextGlobalNo,
            'fdms_fiscal_day_no' => $fiscalDayNo,
        ]);

        // Override counters with calculated values
        $receiptData['receiptCounter'] = $nextReceiptCounter;
        $receiptData['receiptGlobalNo'] = $nextGlobalNo;

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Build Canonical Receipt Structure (Fix RCPT020/RCPT025)
        |--------------------------------------------------------------------------
        | Build the exact payload that will be signed AND sent - no re-encoding
        |--------------------------------------------------------------------------
        */
        // Set fiscal day number from FDMS
        $receiptData['fiscalDayNo'] = $fiscalDayNo;
        
        // Build canonical receipt with BCMath precision (no float casting)
        $canonicalReceipt = $this->buildCanonicalReceiptPayload($receiptData, $fdmsTaxes);
        
        /*
        |--------------------------------------------------------------------------
        | 6️⃣ JSON Encode ONCE, Sign, Then Send Exact Same JSON
        |--------------------------------------------------------------------------
        */
        // Step 1: Encode receipt WITHOUT signature
        $jsonBeforeSignature = json_encode(
            ['receipt' => $canonicalReceipt],
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        
        Log::info('JSON_BEFORE_SIGNING', ['json' => $jsonBeforeSignature]);
        
        // Step 2: Calculate hash and signature from the receipt object (not full payload)
        $receiptJson = json_encode($canonicalReceipt, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $signatureData = $this->signJsonString($receiptJson);
        
        // Step 3: Add signature to canonical receipt
        $canonicalReceipt['receiptDeviceSignature'] = $signatureData;
        
        // Step 4: Encode FINAL payload with signature - this is what gets sent
        $finalJson = json_encode(
            ['receipt' => $canonicalReceipt],
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        
        Log::info('FINAL_JSON_SENT', ['json' => $finalJson]);
        
        // Debug: Write payload to file for inspection
        $debugDir = storage_path('app/zimra/debug');
        if (!is_dir($debugDir)) {
            mkdir($debugDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $invoiceNo = $canonicalReceipt['invoiceNo'] ?? 'unknown';
        
        // Write JSON before signing (what gets hashed)
        file_put_contents(
            "{$debugDir}/receipt_{$timestamp}_{$invoiceNo}_before_sign.json",
            json_encode($canonicalReceipt, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT)
        );
        
        // Write final JSON sent to FDMS
        file_put_contents(
            "{$debugDir}/receipt_{$timestamp}_{$invoiceNo}_final.json",
            json_encode(json_decode($finalJson), JSON_PRETTY_PRINT)
        );
        
        Log::info('DEBUG_FILES_CREATED', [
            'directory' => $debugDir,
            'invoice_no' => $invoiceNo,
            'timestamp' => $timestamp,
        ]);
        
        // Store receiptData for database saving later
        $receiptData = $canonicalReceipt;

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
        | 9️⃣ Save Receipt with Validation Status to Database
        |--------------------------------------------------------------------------
        */
        $primaryTax = $receiptData['receiptTaxes'][0] ?? [];

        $receipt = Receipt::create([
            'device_id' => $deviceId,
            'invoice_no' => $receiptData['invoiceNo'] ?? 'N/A',
            'receipt_type' => $receiptData['receiptType'] ?? 'FiscalInvoice',
            'receipt_currency' => $receiptData['receiptCurrency'] ?? 'USD',
            'receipt_counter' => $receiptData['receiptCounter'],
            'receipt_global_no' => $receiptData['receiptGlobalNo'],
            'fiscal_day_no' => $receiptData['fiscalDayNo'],
            'receipt_total' => $receiptData['receiptTotal'],
            'tax_amount' => $primaryTax['taxAmount'] ?? 0,
            'tax_code' => $primaryTax['taxCode'] ?? 'A',
            'tax_percent' => $primaryTax['taxPercent'] ?? 0,
            'payment_method' => $receiptData['receiptPayments'][0]['moneyTypeCode'] ?? 'Cash',
            'receipt_lines' => $receiptData['receiptLines'],
            'receipt_taxes' => $receiptData['receiptTaxes'],
            'receipt_payments' => $receiptData['receiptPayments'],
            'receipt_hash' => $receiptData['receiptDeviceSignature']['hash'] ?? null,
            'receipt_signature' => $receiptData['receiptDeviceSignature'] ?? null,
            'zimra_response' => $responseData,
            'receipt_date' => $receiptData['receiptDate'] ?? now(),
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
        | 🔟 Throw Exception if Red or Gray errors (Block CloseDay)
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
        // First check if we have cached taxes in config
        if (!empty($zimraConfig->taxes)) {
            Log::debug('Using cached FDMS tax config', ['taxes' => $zimraConfig->taxes]);
            return $zimraConfig->taxes;
        }

        // Otherwise fetch from FDMS
        try {
            $configResponse = $this->getConfig($zimraConfig->device_id);
            
            if (isset($configResponse['taxes']) && is_array($configResponse['taxes'])) {
                Log::info('Fetched FDMS tax config', ['taxes' => $configResponse['taxes']]);
                return $configResponse['taxes'];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch FDMS tax config, using defaults', [
                'error' => $e->getMessage()
            ]);
        }

        // Default tax configuration if FDMS unavailable
        return [
            [
                'taxID' => 1,
                'taxCode' => 'A',
                'taxPercent' => 15.0,
                'taxName' => 'VAT Standard',
            ]
        ];
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
        // Default to tax-inclusive unless explicitly set
        $taxInclusive = $receiptData['receiptLinesTaxInclusive'] ?? false;
        $receiptData['receiptLinesTaxInclusive'] = $taxInclusive;

        // Build tax lookup from FDMS config
        $taxLookup = [];
        foreach ($fdmsTaxes as $tax) {
            $code = $tax['taxCode'] ?? 'A';
            $taxLookup[$code] = [
                'taxID' => $tax['taxID'] ?? 1,
                'taxPercent' => (string) ($tax['taxPercent'] ?? '15.00'),
            ];
        }

        Log::debug('Receipt Tax Mode (BCMath)', [
            'tax_inclusive' => $taxInclusive,
            'fdms_tax_lookup' => $taxLookup,
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Calculate Line Totals & Taxes using BCMath
        |--------------------------------------------------------------------------
        */
        $receiptLines = $receiptData['receiptLines'] ?? [];
        $taxTotals = [];
        $calculatedReceiptTotal = '0.00';

        foreach ($receiptLines as $index => &$line) {
            $price = $this->bcFormat($line['receiptLinePrice'] ?? '0');
            $quantity = $this->bcFormat($line['receiptLineQuantity'] ?? '1');
            $taxCode = $line['taxCode'] ?? 'A';
            
            // Get taxID and taxPercent from FDMS config
            $fdmsTax = $taxLookup[$taxCode] ?? ['taxID' => 1, 'taxPercent' => '15.00'];
            $taxID = $line['taxID'] ?? $fdmsTax['taxID'];
            $taxPercent = $this->bcFormat($line['taxPercent'] ?? $fdmsTax['taxPercent']);
            
            // Update line with correct values
            $line['taxID'] = $taxID;
            $line['taxPercent'] = (float) $taxPercent;

            // Calculate line total: price * quantity (use scale 6 for internal precision)
            $lineTotal = bcmul($price, $quantity, 6);

            if ($taxInclusive) {
                // Tax inclusive: taxAmount = lineTotal * taxPercent / (100 + taxPercent)
                $divisor = bcadd('100', $taxPercent, 6);
                $taxAmount = bcdiv(bcmul($lineTotal, $taxPercent, 6), $divisor, 6);
                $salesAmountWithTax = $lineTotal;
            } else {
                // Tax exclusive: taxAmount = lineTotal * taxPercent / 100
                $taxAmount = bcdiv(bcmul($lineTotal, $taxPercent, 6), '100', 6);
                $salesAmountWithTax = bcadd($lineTotal, $taxAmount, 6);
            }

            // Round final values to 2 decimals AFTER all calculations
            $lineTotal = $this->bcRound($lineTotal, 2);
            $taxAmount = $this->bcRound($taxAmount, 2);
            $salesAmountWithTax = $this->bcRound($salesAmountWithTax, 2);

            // Update line with formatted values (always 2 decimals)
            $line['receiptLinePrice'] = (float) $price;
            $line['receiptLineQuantity'] = (float) $quantity;
            $line['receiptLineTotal'] = (float) $lineTotal;

            // Accumulate tax totals by taxCode (use scale 6 for accumulation)
            $taxKey = "{$taxCode}_{$taxPercent}_{$taxID}";
            if (!isset($taxTotals[$taxKey])) {
                $taxTotals[$taxKey] = [
                    'taxCode' => $taxCode,
                    'taxPercent' => (float) $taxPercent,
                    'taxID' => $taxID,
                    'taxAmount' => '0.000000',
                    'salesAmountWithTax' => '0.000000',
                ];
            }
            $taxTotals[$taxKey]['taxAmount'] = bcadd($taxTotals[$taxKey]['taxAmount'], $taxAmount, 6);
            $taxTotals[$taxKey]['salesAmountWithTax'] = bcadd($taxTotals[$taxKey]['salesAmountWithTax'], $salesAmountWithTax, 6);

            // Accumulate receipt total (use scale 6 for internal precision)
            $calculatedReceiptTotal = bcadd($calculatedReceiptTotal, $salesAmountWithTax, 6);

            Log::debug('Receipt Line Calculation (BCMath)', [
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

        // Round receipt total AFTER all accumulation (was accumulated at scale 6)
        $calculatedReceiptTotal = $this->bcRound($calculatedReceiptTotal, 2);

        // Convert tax totals to float with 2 decimal precision (round after accumulation)
        $formattedTaxes = [];
        foreach ($taxTotals as $tax) {
            $formattedTaxes[] = [
                'taxCode' => $tax['taxCode'],
                'taxPercent' => (float) $tax['taxPercent'],
                'taxID' => $tax['taxID'],
                'taxAmount' => (float) $this->bcRound($tax['taxAmount'], 2),
                'salesAmountWithTax' => (float) $this->bcRound($tax['salesAmountWithTax'], 2),
            ];
        }
        $receiptData['receiptTaxes'] = $formattedTaxes;

        // Set receipt total (rounded to 2 decimals)
        $receiptData['receiptTotal'] = (float) $calculatedReceiptTotal;

        Log::debug('Receipt Tax Totals (BCMath)', [
            'taxes' => $receiptData['receiptTaxes'],
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
            $payment['paymentAmount'] = (float) $paymentAmount;
            $totalPayments = bcadd($totalPayments, $paymentAmount, 2);
        }
        unset($payment);
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
        foreach ($receiptData['receiptTaxes'] as $tax) {
            $totalSalesWithTax = bcadd($totalSalesWithTax, $this->bcFormat($tax['salesAmountWithTax']), 2);
        }

        Log::debug('Receipt Tax Total Validation (BCMath)', [
            'receipt_total' => $calculatedReceiptTotal,
            'sum_sales_with_tax' => $totalSalesWithTax,
        ]);

        // Validate: receiptTotal == sum(salesAmountWithTax)
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
            'tax_groups' => count($receiptData['receiptTaxes']),
            'fiscal_day_no' => $fiscalDayNo,
        ]);

        return $receiptData;
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

            // Update line with calculated values
            $line['receiptLineType'] = $line['receiptLineType'] ?? 'Sale';
            $line['receiptLineNo'] = (int) ($line['receiptLineNo'] ?? $index + 1);
            $line['receiptLineHSCode'] = $line['receiptLineHSCode'] ?? '00000000';
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
        $receiptTotal = round($sumOfLineTotals, 2, PHP_ROUND_HALF_UP);

        // Format taxes with rounding
        $formattedTaxes = [];
        $sumOfTaxSales = 0.0;
        foreach ($taxTotals as $tax) {
            $roundedTaxAmount = round($tax['taxAmount'], 2, PHP_ROUND_HALF_UP);
            $roundedSalesWithTax = round($tax['salesAmountWithTax'], 2, PHP_ROUND_HALF_UP);
            $formattedTaxes[] = [
                'taxCode' => $tax['taxCode'],
                'taxPercent' => $tax['taxPercent'],
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
            $paymentAmount = round((float) ($payment['paymentAmount'] ?? $receiptTotal), 2, PHP_ROUND_HALF_UP);
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
                'paymentAmount' => $receiptTotal,
            ];
            $sumOfPayments = $receiptTotal;
        }

        // Build canonical receipt - NO fiscalDayNo, NO receiptDeviceSignature
        $canonical = [
            'receiptType' => $receiptData['receiptType'] ?? 'FiscalInvoice',
            'receiptCurrency' => $receiptData['receiptCurrency'] ?? 'USD',
            'receiptCounter' => (int) ($receiptData['receiptCounter'] ?? 1),
            'receiptGlobalNo' => (int) ($receiptData['receiptGlobalNo'] ?? 1),
            'invoiceNo' => $receiptData['invoiceNo'] ?? '',
            'receiptDate' => $receiptData['receiptDate'] ?? date('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => $taxInclusive,
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $formattedTaxes,
            'receiptPayments' => $formattedPayments,
            'receiptTotal' => $receiptTotal,
            'receiptPrintForm' => $receiptData['receiptPrintForm'] ?? 'Receipt48',
        ];

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
    private function validateReceiptTotals(array $receipt): void
    {
        $receiptTotal = (float) ($receipt['receiptTotal'] ?? 0);
        
        // Sum of receipt line totals (salesAmountWithTax for tax-inclusive)
        $sumLineTotals = 0.0;
        foreach ($receipt['receiptLines'] ?? [] as $line) {
            $sumLineTotals += (float) ($line['receiptLineTotal'] ?? 0);
        }
        $sumLineTotals = round($sumLineTotals, 2, PHP_ROUND_HALF_UP);

        // Sum of salesAmountWithTax from taxes
        $sumTaxSales = 0.0;
        foreach ($receipt['receiptTaxes'] ?? [] as $tax) {
            $sumTaxSales += (float) ($tax['salesAmountWithTax'] ?? 0);
        }
        $sumTaxSales = round($sumTaxSales, 2, PHP_ROUND_HALF_UP);

        // Sum of payments
        $sumPayments = 0.0;
        foreach ($receipt['receiptPayments'] ?? [] as $payment) {
            $sumPayments += (float) ($payment['paymentAmount'] ?? 0);
        }
        $sumPayments = round($sumPayments, 2, PHP_ROUND_HALF_UP);

        Log::info('VALIDATE_RECEIPT_TOTALS', [
            'receiptTotal' => $receiptTotal,
            'sumLineTotals' => $sumLineTotals,
            'sumTaxSales' => $sumTaxSales,
            'sumPayments' => $sumPayments,
        ]);

        // Validate totals match
        $tolerance = 0.01; // Allow 1 cent tolerance for rounding
        
        if (abs($receiptTotal - $sumLineTotals) > $tolerance) {
            throw new \Exception(
                "RCPT020 Prevention: receiptTotal ({$receiptTotal}) != SUM(receiptLineTotal) ({$sumLineTotals})"
            );
        }

        if (abs($receiptTotal - $sumTaxSales) > $tolerance) {
            throw new \Exception(
                "RCPT020 Prevention: receiptTotal ({$receiptTotal}) != SUM(salesAmountWithTax) ({$sumTaxSales})"
            );
        }

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

        // Step 1: Generate SHA256 hash for the 'hash' field
        $hashBinary = hash('sha256', $json, true);
        $hashBase64 = base64_encode($hashBinary);

        Log::info('SIGNING_HASH_GENERATED', [
            'hash_base64' => $hashBase64,
            'hash_hex' => bin2hex($hashBinary),
        ]);

        // Step 2: Load private key
        $privateKeyPath = storage_path('app/zimra/device_private.key');
        
        if (!file_exists($privateKeyPath)) {
            throw new \Exception('Device private key not found. Please register device first.');
        }

        $privateKeyPem = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new \Exception('Failed to load private key for signing: ' . openssl_error_string());
        }

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

        // Step 4: Verify signature locally before sending
        $certPath = storage_path('app/zimra/device_certificate.pem');
        if (file_exists($certPath)) {
            $certPem = file_get_contents($certPath);
            $cert = openssl_x509_read($certPem);
            if ($cert) {
                $publicKey = openssl_pkey_get_public($cert);
                // Verify against raw JSON (same as what we signed)
                $verifyResult = openssl_verify($json, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
                
                Log::info('SIGNING_LOCAL_VERIFY', [
                    'verified' => $verifyResult === 1,
                    'verify_result_code' => $verifyResult,
                ]);
                
                if ($verifyResult !== 1) {
                    Log::error('SIGNING_LOCAL_VERIFY_FAILED', [
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
        // Default to tax-exclusive unless explicitly set
        $taxInclusive = $receiptData['receiptLinesTaxInclusive'] ?? false;
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
                $key = $tax['taxCode'] . '_' . ($tax['taxPercent'] ?? 0);

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
}
