# Production CSR & Device Model Registration Guide

## Overview

This guide provides step-by-step instructions for generating a production-ready Certificate Signing Request (CSR) and registering your device model with ZIMRA for production use.

---

## 1. CSR Requirements (FDMS Spec v7.2, Page 10)

### 1.1 Mandatory CSR Fields

Your CSR **MUST** contain the following fields in the exact format specified:

| Field | Value | Description |
|-------|-------|-------------|
| **CN** (Common Name) | `ZIMRA-<DeviceSerial>-<zero_padded_deviceID>` | Device identifier |
| **C** (Country) | `ZW` | Zimbabwe country code |
| **O** (Organization) | `Zimbabwe Revenue Authority` | Exact organization name |
| **S** (State/Province) | `Zimbabwe` | State/Province name |

### 1.2 CSR Format Examples

**Example 1: Device Serial "SRV001", Device ID 32558**
```
CN = ZIMRA-SRV001-32558
C = ZW
O = Zimbabwe Revenue Authority
S = Zimbabwe
```

**Example 2: Device Serial "POS123", Device ID 5**
```
CN = ZIMRA-POS123-00005
C = ZW
O = Zimbabwe Revenue Authority
S = Zimbabwe
```

**Example 3: Device Serial "RETAIL-A1", Device ID 1234**
```
CN = ZIMRA-RETAIL-A1-01234
C = ZW
O = Zimbabwe Revenue Authority
S = Zimbabwe
```

### 1.3 Zero-Padding Rules for Device ID

- Device IDs **MUST** be zero-padded to 5 digits
- Examples:
  - `1` → `00001`
  - `42` → `00042`
  - `999` → `00999`
  - `12345` → `12345`

⚠️ **CRITICAL:** If CSR format is wrong → **production will be rejected**

---

## 2. Generating Production CSR

### 2.1 Using OpenSSL (Recommended)

#### Step 1: Prepare Configuration File

Create a file named `production_csr.conf`:

```ini
[req]
default_bits = 256
prompt = no
default_md = sha256
req_extensions = v3_req
distinguished_name = dn

[dn]
CN = ZIMRA-<YOUR_SERIAL>-<YOUR_DEVICE_ID>
C = ZW
O = Zimbabwe Revenue Authority
S = Zimbabwe

[v3_req]
keyUsage = critical, digitalSignature
extendedKeyUsage = clientAuth
```

**Replace:**
- `<YOUR_SERIAL>` with your actual device serial number
- `<YOUR_DEVICE_ID>` with your zero-padded device ID (5 digits)

#### Step 2: Generate Private Key and CSR

```bash
# Generate ECDSA private key (P-256 curve)
openssl ecparam -name prime256v1 -genkey -noout -out production_private.key

# Generate CSR using the configuration file
openssl req -new -key production_private.key -out production_csr.pem -config production_csr.conf

# Verify CSR contents
openssl req -in production_csr.pem -noout -text
```

#### Step 3: Verify CSR Format

```bash
# Check subject line
openssl req -in production_csr.pem -noout -subject

# Expected output:
# subject=CN=ZIMRA-SRV001-32558, C=ZW, O=Zimbabwe Revenue Authority, S=Zimbabwe
```

### 2.2 Using PHP (Laravel Application)

Add this command to your Laravel application:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateProductionCSR extends Command
{
    protected $signature = 'zimra:generate-production-csr 
                            {serial : Device serial number}
                            {device_id : Device ID (will be zero-padded)}';
    
    protected $description = 'Generate production CSR for ZIMRA FDMS';

    public function handle()
    {
        $serial = $this->argument('serial');
        $deviceId = str_pad($this->argument('device_id'), 5, '0', STR_PAD_LEFT);
        
        // Build Distinguished Name
        $dn = [
            "countryName" => "ZW",
            "stateOrProvinceName" => "Zimbabwe",
            "organizationName" => "Zimbabwe Revenue Authority",
            "commonName" => "ZIMRA-{$serial}-{$deviceId}"
        ];
        
        // Generate private key
        $config = [
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name" => "prime256v1",
        ];
        
        $privateKey = openssl_pkey_new($config);
        
        if (!$privateKey) {
            $this->error('Failed to generate private key');
            return 1;
        }
        
        // Generate CSR
        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha256',
        ]);
        
        if (!$csr) {
            $this->error('Failed to generate CSR');
            return 1;
        }
        
        // Export CSR
        openssl_csr_export($csr, $csrOut);
        
        // Export private key
        openssl_pkey_export($privateKey, $privateKeyOut);
        
        // Save to files
        $csrPath = storage_path("app/zimra/production_csr_{$deviceId}.pem");
        $keyPath = storage_path("app/zimra/production_private_{$deviceId}.key");
        
        file_put_contents($csrPath, $csrOut);
        file_put_contents($keyPath, $privateKeyOut);
        
        $this->info("Production CSR generated successfully!");
        $this->info("CSR saved to: {$csrPath}");
        $this->info("Private key saved to: {$keyPath}");
        $this->newLine();
        
        // Display CSR details
        $csrDetails = openssl_csr_get_subject($csr);
        $this->info("CSR Subject:");
        $this->line("  CN: " . $csrDetails['CN']);
        $this->line("  C: " . $csrDetails['C']);
        $this->line("  O: " . $csrDetails['O']);
        $this->line("  S: " . $csrDetails['S']);
        $this->newLine();
        
        $this->warn("⚠️  IMPORTANT: Keep the private key secure!");
        $this->warn("⚠️  Submit the CSR file to ZIMRA for certificate issuance");
        
        return 0;
    }
}
```

**Usage:**
```bash
php artisan zimra:generate-production-csr SRV001 32558
```

### 2.3 Verification Checklist

Before submitting your CSR to ZIMRA, verify:

- [ ] CN format: `ZIMRA-<Serial>-<DeviceID>`
- [ ] Device ID is zero-padded to 5 digits
- [ ] Country code is exactly `ZW`
- [ ] Organization is exactly `Zimbabwe Revenue Authority`
- [ ] State is exactly `Zimbabwe`
- [ ] Key algorithm is ECDSA (P-256 curve)
- [ ] Signature algorithm is SHA-256
- [ ] CSR is in PEM format
- [ ] Private key is securely stored

---

## 3. Device Model Registration

### 3.1 Production Device Model Requirements

Before registering a device in production, ensure your device model is registered with ZIMRA.

**Device Model Information Required:**

| Field | Example | Description |
|-------|---------|-------------|
| Model Name | `Server` | Device model name |
| Model Version | `v1.0` | Software version |
| Manufacturer | `Your Company Ltd` | Company name |
| Device Type | `Virtual Fiscal Device` | Type of device |
| Capabilities | `Receipt, Invoice, Credit Note` | Supported operations |

### 3.2 Check if Model is Already Registered

Contact ZIMRA to verify if your device model is already registered:

**ZIMRA Contact Information:**
- **Email:** fdms@zimra.co.zw
- **Phone:** +263 4 758891-5
- **Website:** https://www.zimra.co.zw

**Information to Provide:**
```
Subject: Device Model Registration Inquiry

Dear ZIMRA FDMS Team,

I would like to inquire if the following device model is registered:

Model Name: Server
Model Version: v1.0
Manufacturer: [Your Company Name]
Device Type: Virtual Fiscal Device

If not registered, please advise on the registration process.

Thank you.
```

### 3.3 Device Model Registration Process

If your model is **not registered**, follow these steps:

#### Step 1: Prepare Documentation

Prepare the following documents:

1. **Device Model Specification Document**
   - Hardware/Software specifications
   - Supported receipt types
   - Security features
   - Compliance with FDMS requirements

2. **Company Registration Documents**
   - Certificate of Incorporation
   - Tax Clearance Certificate
   - VAT Registration Certificate (if applicable)

3. **Technical Documentation**
   - API integration details
   - Security implementation
   - Data storage and backup procedures

#### Step 2: Submit Application

Submit your application to ZIMRA via:

**Email:** fdms@zimra.co.zw

**Subject:** New Device Model Registration Application

**Attachments:**
- Device model specification
- Company documents
- Technical documentation
- Sample CSR (for verification)

#### Step 3: Wait for Approval

ZIMRA will review your application and may request:
- Additional documentation
- Technical demonstrations
- Security audits
- Compliance testing

**Typical Timeline:** 2-4 weeks

---

## 4. Production Device Registration

### 4.1 Prerequisites

Before registering a device in production:

- ✅ Device model is registered with ZIMRA
- ✅ Production CSR is generated with correct format
- ✅ Company is registered and tax compliant
- ✅ Testing completed in ZIMRA test environment
- ✅ All FDMS compliance requirements met

### 4.2 Registration API Endpoint

**Production Endpoint:**
```
POST https://fdmsapi.zimra.co.zw/Device/v1/registerDevice
```

**Test Endpoint (for verification):**
```
POST https://fdmsapitest.zimra.co.zw/Device/v1/registerDevice
```

### 4.3 Registration Request

```json
{
  "deviceModelName": "Server",
  "deviceModelVersion": "v1.0",
  "deviceSerialNumber": "SRV001",
  "certificateRequest": "-----BEGIN CERTIFICATE REQUEST-----\nMIIBXTCCAQMCAQAwgYsxCzAJBgNVBAYTAlpXMRIwEAYDVQQIEwlaaW1iYWJ3ZTEX\n...\n-----END CERTIFICATE REQUEST-----"
}
```

### 4.4 Registration Response

**Success Response:**
```json
{
  "deviceID": 32558,
  "certificateID": "CERT-32558-2026",
  "status": "Pending",
  "message": "Device registration initiated. Certificate will be issued upon approval."
}
```

### 4.5 Certificate Issuance

After registration approval, retrieve your certificate:

**Endpoint:**
```
POST https://fdmsapi.zimra.co.zw/Device/v1/{deviceID}/issueCertificate
```

**Request:**
```json
{
  "certificateID": "CERT-32558-2026"
}
```

**Response:**
```json
{
  "certificate": "-----BEGIN CERTIFICATE-----\nMIICXTCCAcSgAwIBAgIJAK...\n-----END CERTIFICATE-----",
  "certificateThumbprint": "A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0",
  "validFrom": "2026-02-23T00:00:00Z",
  "validTo": "2027-02-23T00:00:00Z",
  "status": "Active"
}
```

---

## 5. Production Checklist

### 5.1 Pre-Production Verification

- [ ] CSR generated with correct format (CN, C, O, S)
- [ ] Device ID is zero-padded to 5 digits
- [ ] Device model is registered with ZIMRA
- [ ] Company documents are up to date
- [ ] Test environment integration successful
- [ ] All FDMS compliance tests passed
- [ ] Private key is securely stored
- [ ] Backup and recovery procedures in place

### 5.2 Production Deployment

- [ ] CSR submitted to ZIMRA
- [ ] Device registration completed
- [ ] Certificate received and validated
- [ ] Certificate and private key stored in database
- [ ] Production configuration updated
- [ ] Production endpoint configured
- [ ] mTLS authentication tested
- [ ] First receipt submitted successfully
- [ ] QR code verification working
- [ ] Monitoring and logging enabled

### 5.3 Post-Production

- [ ] Certificate expiry monitoring set up
- [ ] Regular backups configured
- [ ] Support contact established with ZIMRA
- [ ] Documentation updated
- [ ] Staff trained on production system
- [ ] Incident response plan in place

---

## 6. Common Issues and Solutions

### Issue 1: CSR Rejected - Invalid Format

**Error:** "Certificate request format is invalid"

**Solution:**
- Verify CN format: `ZIMRA-<Serial>-<DeviceID>`
- Ensure Device ID is zero-padded to 5 digits
- Check Country code is `ZW` (not `Zimbabwe`)
- Verify Organization is exactly `Zimbabwe Revenue Authority`
- Confirm State is `Zimbabwe`

### Issue 2: Device ID Not Zero-Padded

**Error:** "Device ID format incorrect"

**Solution:**
```php
// Correct
$deviceId = str_pad('123', 5, '0', STR_PAD_LEFT); // "00123"

// Incorrect
$deviceId = '123'; // Wrong - not padded
```

### Issue 3: Device Model Not Registered

**Error:** "Device model not found in registry"

**Solution:**
- Contact ZIMRA to register your device model first
- Provide model specifications and documentation
- Wait for approval before proceeding

### Issue 4: Certificate Issuance Delayed

**Error:** "Certificate not yet available"

**Solution:**
- ZIMRA manual approval may take 1-3 business days
- Contact ZIMRA support for status update
- Ensure all documentation is complete

---

## 7. Sample CSR Generation Script

Save this as `generate_production_csr.sh`:

```bash
#!/bin/bash

# Production CSR Generator for ZIMRA FDMS
# Usage: ./generate_production_csr.sh <serial> <device_id>

SERIAL=$1
DEVICE_ID=$(printf "%05d" $2)

if [ -z "$SERIAL" ] || [ -z "$DEVICE_ID" ]; then
    echo "Usage: $0 <serial> <device_id>"
    echo "Example: $0 SRV001 32558"
    exit 1
fi

CN="ZIMRA-${SERIAL}-${DEVICE_ID}"

echo "Generating production CSR..."
echo "Serial: $SERIAL"
echo "Device ID: $DEVICE_ID"
echo "Common Name: $CN"
echo ""

# Create config file
cat > csr_config.conf << EOF
[req]
default_bits = 256
prompt = no
default_md = sha256
req_extensions = v3_req
distinguished_name = dn

[dn]
CN = $CN
C = ZW
O = Zimbabwe Revenue Authority
S = Zimbabwe

[v3_req]
keyUsage = critical, digitalSignature
extendedKeyUsage = clientAuth
EOF

# Generate private key
openssl ecparam -name prime256v1 -genkey -noout -out "production_private_${DEVICE_ID}.key"

# Generate CSR
openssl req -new -key "production_private_${DEVICE_ID}.key" -out "production_csr_${DEVICE_ID}.pem" -config csr_config.conf

# Verify
echo "CSR Subject:"
openssl req -in "production_csr_${DEVICE_ID}.pem" -noout -subject

echo ""
echo "✅ CSR generated successfully!"
echo "CSR file: production_csr_${DEVICE_ID}.pem"
echo "Private key: production_private_${DEVICE_ID}.key"
echo ""
echo "⚠️  IMPORTANT: Keep the private key secure!"
echo "⚠️  Submit production_csr_${DEVICE_ID}.pem to ZIMRA"

# Cleanup
rm csr_config.conf
```

**Make executable and run:**
```bash
chmod +x generate_production_csr.sh
./generate_production_csr.sh SRV001 32558
```

---

## 8. Contact Information

### ZIMRA FDMS Support

**Email:** fdms@zimra.co.zw  
**Phone:** +263 4 758891-5  
**Website:** https://www.zimra.co.zw  
**Office Hours:** Monday - Friday, 8:00 AM - 4:30 PM CAT

### Technical Support

For technical issues with the integration:
- Check logs in `storage/logs/laravel.log`
- Review FDMS API documentation
- Contact your system administrator

---

## 9. References

- **FDMS API Specification:** Fiscal Device Gateway API v7.2
- **CSR Requirements:** Page 10 of FDMS Spec
- **Device Registration:** Section 4.1 of FDMS Spec
- **Certificate Management:** Section 10.4 of FDMS Spec

---

**Document Version:** 1.0  
**Last Updated:** 2026-02-23  
**Status:** Production Ready
