<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note - {{ $receipt->invoice_no }}</title>
    <style>
        @page {
            margin-top: 120px;
            margin-left: 20px;
            margin-right: 20px;
            margin-bottom: 40px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
        }

        .page-header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 100px;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0052a3;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items th {
            border: 1px solid #000;
            padding: 6px;
            background: #e6f2ff;
            color: #0052a3;
        }

        .items td {
            border: 1px solid #000;
            padding: 6px;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .summary td {
            border: 1px solid #000;
            padding: 6px;
        }

        .footer {
            margin-top: 30px;
            font-size: 9pt;
        }

        .credit-note-badge {
            background: #0052a3;
            color: white;
            padding: 3px 10px;
            display: inline-block;
            font-weight: bold;
            font-size: 9pt;
        }

        .original-invoice-info {
            background: #f0f8ff;
            border: 1px solid #0052a3;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    @php
        $config = \App\Models\ZimraConfig::where('device_id', $receipt->device_id)->first();
        $companyName = $config->company_name ?? 'Company Name';
        $companyTin = $config->company_tin ?? 'TIN Number';
        $companyAddress = $config->company_address ?? '';
        $companyPhone = $config->company_phone ?? '';
        $companyEmail = $config->company_email ?? '';
        
        $vatNumber = null;
        if (isset($receipt->vat_number)) {
            $vatNumber = $receipt->vat_number;
        } elseif ($config && isset($config->vat_number)) {
            $vatNumber = $config->vat_number;
        }
        
        $isVatRegistered = $vatNumber && $vatNumber !== 'NOT_REGISTERED';
        
        // Get original invoice info
        $originalReceipt = null;
        if ($receipt->original_receipt_id) {
            $originalReceipt = \App\Models\Receipt::find($receipt->original_receipt_id);
        }
        
        // Calculate tax breakdown
        $subtotal = 0;
        $taxBreakdown = [];
        
        foreach ($receipt->receipt_taxes as $tax) {
            $taxLabel = ($tax['taxPercent'] ?? 0) . '% Tax';
            if ($tax['taxPercent'] == 0) {
                $taxLabel = 'Zero rated (0%)';
            } elseif ($tax['taxPercent'] == 15) {
                $taxLabel = 'Standard rated (15%)';
            }
            $taxBreakdown[$taxLabel] = abs($tax['taxAmount'] ?? 0);
        }
        
        // Calculate subtotal
        foreach ($receipt->receipt_lines as $line) {
            $lineTotal = abs($line['receiptLineTotal'] ?? 0);
            $taxPercent = $line['taxPercent'] ?? 0;
            if ($taxPercent > 0) {
                $taxAmount = $lineTotal - ($lineTotal / (1 + ($taxPercent / 100)));
                $subtotal += ($lineTotal - $taxAmount);
            } else {
                $subtotal += $lineTotal;
            }
        }
        
        $grandTotal = abs($receipt->receipt_total);
    @endphp

    <!-- Repeating Header -->
    <div class="page-header">
        <table class="header-table">
            <tr>
                <td width="25%" style="vertical-align:top;">
                    <strong>{{ $companyName }}</strong><br>
                    TIN: {{ $companyTin }}<br>
                    @if($isVatRegistered)
                        VAT: {{ $vatNumber }}
                    @else
                        VAT: Not Registered
                    @endif
                </td>
                <td width="50%" class="text-center" style="vertical-align:middle;">
                    <span class="credit-note-badge">CREDIT NOTE</span><br>
                    <h2 style="margin:5px 0; color:#0052a3;">FISCAL TAX CREDIT NOTE</h2>
                    Credit Note: {{ $receipt->invoice_no }}
                </td>
                <td width="25%" class="text-right" style="vertical-align:top;">
                    @if(isset($qrCodeBase64) && $qrCodeBase64)
                        <img src="{{ $qrCodeBase64 }}" width="80" alt="QR Code"><br>
                    @endif
                    <small>Verification</small><br>
                    @if(isset($verificationCode) && $verificationCode)
                        <strong>{{ $verificationCode }}</strong>
                    @elseif($receipt->verification_code)
                        <strong>{{ $receipt->verification_code }}</strong>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Credited Invoice Information -->
    @if($originalReceipt)
    <div class="original-invoice-info">
        <strong>Credited Invoice</strong>
    </div>
    <table width="100%" style="margin-bottom:10px; font-size:9pt;">
        <tr>
            <td>Device Serial No: {{ $config->serial_number ?? 'N/A' }}</td>
            <td>Invoice No (Receipt Global No): {{ $originalReceipt->receipt_global_no }}</td>
        </tr>
        <tr>
            <td>Receipt Date: {{ $originalReceipt->receipt_date->format('d/m/Y H:i:s') }}</td>
            <td>Customer Reference No: {{ $originalReceipt->invoice_no }}</td>
        </tr>
        @if($receipt->receipt_notes)
        <tr>
            <td colspan="2"><strong>Reason:</strong> {{ $receipt->receipt_notes }}</td>
        </tr>
        @endif
    </table>
    @endif

    <!-- Current Credit Note Metadata -->
    <table width="100%" style="margin-bottom:10px; font-size:9pt; background:#f0f8ff;">
        <tr>
            <td>Invoice No (Receipt Counter): {{ $receipt->receipt_counter }}</td>
            <td>Invoice No (Receipt Global No): {{ $receipt->receipt_global_no }}</td>
        </tr>
        <tr>
            <td>Fiscal Day No: {{ $receipt->fiscal_day_no }}</td>
            <td>Customer Reference No: {{ $receipt->invoice_no }}</td>
        </tr>
        <tr>
            <td>Device Serial No: {{ $config->serial_number ?? 'N/A' }}</td>
            <td>Device ID: {{ $receipt->device_id }}</td>
        </tr>
        <tr>
            <td colspan="2">Receipt Date and Time: {{ $receipt->receipt_date->format('d/m/Y H:i:s') }}</td>
        </tr>
    </table>

    <!-- Seller / Buyer -->
    <table width="100%" style="margin-bottom:10px;">
        <tr>
            <td width="50%" style="vertical-align:top;">
                <strong>SELLER</strong><br>
                {{ $companyName }}<br>
                TIN: {{ $companyTin }}<br>
                @if($isVatRegistered)
                    VAT: {{ $vatNumber }}<br>
                @endif
                @if($companyAddress)
                    {{ $companyAddress }}<br>
                @endif
                @if($companyPhone)
                    Tel: {{ $companyPhone }}<br>
                @endif
                @if($companyEmail)
                    Email: {{ $companyEmail }}<br>
                @endif
            </td>
            <td width="50%" style="vertical-align:top;">
                <strong>BUYER</strong><br>
                @if($receipt->buyer_data && !empty($receipt->buyer_data) && isset($receipt->buyer_data['buyerRegisterName']) && $receipt->buyer_data['buyerRegisterName'])
                    {{ $receipt->buyer_data['buyerRegisterName'] }}<br>
                    @if(isset($receipt->buyer_data['buyerTIN']) && $receipt->buyer_data['buyerTIN'])
                        TIN: {{ $receipt->buyer_data['buyerTIN'] }}<br>
                    @endif
                    @if(isset($receipt->buyer_data['vatNumber']) && $receipt->buyer_data['vatNumber'])
                        VAT: {{ $receipt->buyer_data['vatNumber'] }}<br>
                    @endif
                    @if(isset($receipt->buyer_data['buyerAddress']))
                        @php
                            $address = [];
                            if(isset($receipt->buyer_data['buyerAddress']['street'])) $address[] = $receipt->buyer_data['buyerAddress']['street'];
                            if(isset($receipt->buyer_data['buyerAddress']['city'])) $address[] = $receipt->buyer_data['buyerAddress']['city'];
                            $fullAddress = implode(', ', array_filter($address));
                        @endphp
                        @if($fullAddress)
                            {{ $fullAddress }}<br>
                        @endif
                    @endif
                    @if(isset($receipt->buyer_data['buyerContacts']['phoneNo']) && $receipt->buyer_data['buyerContacts']['phoneNo'])
                        Tel: {{ $receipt->buyer_data['buyerContacts']['phoneNo'] }}<br>
                    @endif
                    @if(isset($receipt->buyer_data['buyerContacts']['email']) && $receipt->buyer_data['buyerContacts']['email'])
                        Email: {{ $receipt->buyer_data['buyerContacts']['email'] }}<br>
                    @endif
                @else
                    Cash Customer
                @endif
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items">
        <thead>
            <tr>
                <th>Code</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Discount</th>
                <th>Tax %</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->receipt_lines as $index => $line)
            @php
                $lineType = $line['receiptLineType'] ?? 'Sale';
                $isDiscount = $lineType === 'Discount';
                $linePrice = abs($line['receiptLinePrice'] ?? 0);
                $lineTotal = abs($line['receiptLineTotal'] ?? 0);
                $lineQty = $line['receiptLineQuantity'] ?? 1;
                $taxPercent = $line['taxPercent'] ?? 0;
                $discountAmount = $isDiscount ? $lineTotal : 0;
                
                // Generate simple code
                $productName = $line['receiptLineName'] ?? 'Item';
                $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $productName), 0, 5));
                $generatedCode = ($prefix ?: 'ITEM') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            @endphp
            <tr>
                <td style="font-size:8pt;">{{ $generatedCode }}</td>
                <td>{{ $line['receiptLineName'] ?? 'Item' }}</td>
                <td class="text-center">{{ $lineQty }}</td>
                <td class="text-right">{{ number_format($linePrice, 2) }}</td>
                <td class="text-right">{{ number_format($discountAmount, 2) }}</td>
                <td class="text-right">{{ number_format($taxPercent, 2) }}</td>
                <td class="text-right">{{ $receipt->receipt_currency }} {{ number_format($lineTotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Tax Summary -->
    <table class="summary">
        <tr>
            <td>Subtotal (excl. Tax)</td>
            <td class="text-right"><strong>{{ $receipt->receipt_currency }} {{ number_format($subtotal, 2) }}</strong></td>
        </tr>
        @foreach($taxBreakdown as $label => $amount)
        <tr>
            <td>{{ $label }}</td>
            <td class="text-right">{{ $receipt->receipt_currency }} {{ number_format($amount, 2) }}</td>
        </tr>
        @endforeach
        <tr style="background:#e6f2ff;">
            <td><strong>Credit Note Total</strong></td>
            <td class="text-right"><strong>{{ $receipt->receipt_currency }} {{ number_format($grandTotal, 2) }}</strong></td>
        </tr>
    </table>

    <!-- Fiscal Footer -->
    <div class="footer">
        <strong>Payment Information:</strong> Please make all payments to our CBZ Bank Account 0100000000<br>
        <strong>Terms:</strong> This invoice will be considered invalid when the payment due date has lapsed.<br><br>
        
        Device Serial: {{ $config->serial_number ?? 'N/A' }}<br>
        Fiscal Day No: {{ $receipt->fiscal_day_no }}<br>
        @if($receipt->receipt_qr_code)
            Verify this receipt at: <a href="{{ $receipt->receipt_qr_code }}">{{ $receipt->receipt_qr_code }}</a>
        @endif
    </div>
</body>
</html>
