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

        // Build payload from database if not provided
        if (!$payload) {
            // Filter counters: remove zero values and fix structure per ZIMRA FDMS v7.2
            $rawCounters = $fiscalDay->fiscal_counters ?? [];
            $filteredCounters = [];
            foreach ($rawCounters as $counter) {
                // Skip zero value counters
                if (empty($counter['fiscalCounterValue']) || $counter['fiscalCounterValue'] == 0) {
                    continue;
                }
                // For SaleByTax: remove fiscalCounterMoneyType (not allowed per spec)
                if (isset($counter['fiscalCounterType']) && strtolower($counter['fiscalCounterType']) === 'salebytax') {
                    unset($counter['fiscalCounterMoneyType']);
                }
                $filteredCounters[] = $counter;
            }

            $payload = [
                'fiscalDayNo' => $fiscalDay->fiscal_day_no,
                'fiscalDayCounters' => $filteredCounters,
                'receiptCounter' => $fiscalDay->receipt_counter ?? 0,
            ];
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
        | 1️⃣ Build & Validate Receipt with Tax Calculations
        |--------------------------------------------------------------------------
        */
        $receiptData = $this->buildAndValidateReceipt($receiptData, $fiscalDay);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Generate Device Signature
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
        openssl_sign($receiptJson, $signatureBinary, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureBase64 = base64_encode($signatureBinary);

        // Add signature to receipt
        $receiptData['receiptDeviceSignature'] = [
            'hash' => $hashBase64,
            'signature' => $signatureBase64,
        ];

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Send To ZIMRA
        |--------------------------------------------------------------------------
        */

        Log::info('ZIMRA SubmitReceipt Request', [
            'device_id' => $deviceId,
            'fiscal_day_no' => $fiscalDay->fiscal_day_no,
            'receipt_counter' => $receiptData['receiptCounter'] ?? null,
            'receipt_global_no' => $receiptData['receiptGlobalNo'] ?? null,
            'receipt_total' => $receiptData['receiptTotal'] ?? null,
            'tax_inclusive' => $receiptData['receiptLinesTaxInclusive'] ?? false,
        ]);

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

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Log Full Raw FDMS Response
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
        | 5️⃣ Check for Validation Errors
        |--------------------------------------------------------------------------
        */
        $validationErrors = $responseData['validationErrors'] ?? [];
        $receiptValidationCode = $responseData['receiptValidationCode'] ?? null;
        $fdmsReceiptId = $responseData['receiptID'] ?? null;
        $isValid = empty($validationErrors) && !in_array($receiptValidationCode, ['Grey', 'Red']);

        // Log each validation error individually
        if (!empty($validationErrors)) {
            Log::error('ZIMRA Receipt Validation Errors Detected', [
                'receipt_id' => $fdmsReceiptId,
                'validation_code' => $receiptValidationCode,
                'error_count' => count($validationErrors)
            ]);

            foreach ($validationErrors as $index => $error) {
                Log::error("ZIMRA Validation Error #{$index}", [
                    'errorCode' => $error['errorCode'] ?? 'UNKNOWN',
                    'errorMessage' => $error['errorMessage'] ?? $error['message'] ?? 'No message',
                    'field' => $error['field'] ?? null,
                    'receipt_id' => $fdmsReceiptId,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Save Receipt with Validation Status to Database
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
            'validation_errors' => $validationErrors,
            'is_valid' => $isValid,
            'fdms_receipt_id' => $fdmsReceiptId,
        ]);

        Log::info('Receipt Saved to Database', [
            'receipt_id' => $receipt->id,
            'fdms_receipt_id' => $fdmsReceiptId,
            'is_valid' => $isValid,
            'validation_code' => $receiptValidationCode,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7️⃣ Throw Exception if Validation Errors Exist (Block CloseDay)
        |--------------------------------------------------------------------------
        */
        if (!empty($validationErrors)) {
            $errorSummary = collect($validationErrors)->map(function ($e) {
                return ($e['errorCode'] ?? 'UNKNOWN') . ': ' . ($e['errorMessage'] ?? $e['message'] ?? 'No message');
            })->implode('; ');

            Log::error('ZIMRA Receipt Invalid - CloseDay Blocked', [
                'receipt_id' => $fdmsReceiptId,
                'validation_code' => $receiptValidationCode,
                'error_summary' => $errorSummary,
                'db_receipt_id' => $receipt->id,
            ]);

            throw new \Exception(
                "ZIMRA Receipt Validation Failed [{$receiptValidationCode}]: {$errorSummary}. " .
                "Receipt saved to database (ID: {$receipt->id}). CloseDay will fail with this receipt."
            );
        }

        // Also throw if Grey or Red even without explicit errors
        if ($receiptValidationCode === 'Grey' || $receiptValidationCode === 'Red') {
            Log::error('ZIMRA Receipt Marked Invalid (Grey/Red)', [
                'receipt_id' => $fdmsReceiptId,
                'validation_code' => $receiptValidationCode,
                'db_receipt_id' => $receipt->id,
            ]);

            throw new \Exception(
                "ZIMRA Receipt marked as {$receiptValidationCode}. " .
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
    | Build & Validate Receipt with Tax Calculations
    |--------------------------------------------------------------------------
    | Handles tax-inclusive vs tax-exclusive calculations per ZIMRA spec.
    | Default: receiptLinesTaxInclusive = false
    |--------------------------------------------------------------------------
    */
    private function buildAndValidateReceipt(array $receiptData, FiscalDay $fiscalDay): array
    {
        // Default to tax-exclusive unless explicitly set
        $taxInclusive = $receiptData['receiptLinesTaxInclusive'] ?? false;
        $receiptData['receiptLinesTaxInclusive'] = $taxInclusive;

        Log::debug('Receipt Tax Mode', ['tax_inclusive' => $taxInclusive]);

        /*
        |--------------------------------------------------------------------------
        | Calculate Line Totals & Taxes
        |--------------------------------------------------------------------------
        */
        $receiptLines = $receiptData['receiptLines'] ?? [];
        $taxTotals = []; // Group by taxCode
        $calculatedReceiptTotal = 0;

        foreach ($receiptLines as $index => &$line) {
            $price = (float) ($line['receiptLinePrice'] ?? 0);
            $quantity = (float) ($line['receiptLineQuantity'] ?? 1);
            $taxPercent = (float) ($line['taxPercent'] ?? 0);
            $taxCode = $line['taxCode'] ?? 'A';
            $taxID = $line['taxID'] ?? 1;

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
