<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debit Note - {{ $receipt->invoice_no }}</title>
    <style>
        @page {
            margin: 15mm;
        }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .receipt-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 10mm;
            padding-bottom: 140px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #dc2626;
        }
        
        .company-logo {
            flex: 0 0 80px;
        }
        
        .company-logo img {
            max-width: 80px;
            max-height: 80px;
        }
        
        .debit-note-badge {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
        }
        
        .invoice-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
            color: #dc2626;
            border: 2px solid #dc2626;
            padding: 12px;
            background: #fef2f2;
        }
        
        .reference-section {
            background: #fff7ed;
            border: 2px solid #f59e0b;
            border-radius: 6px;
            padding: 12px;
            margin: 15px 0;
        }
        
        .reference-title {
            font-weight: bold;
            font-size: 10pt;
            color: #92400e;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .reference-details {
            font-size: 9pt;
            line-height: 1.6;
        }
        
        .reference-details strong {
            color: #000;
        }
        
        .parties-section {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .parties-section table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .parties-section td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
        }
        
        .party-box {
            padding: 0;
        }
        
        .party-title {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 8px;
            text-transform: uppercase;
            color: #dc2626;
        }
        
        .party-info {
            font-size: 9pt;
            line-height: 1.5;
        }
        
        .invoice-details {
            margin-bottom: 15px;
            background: #f9fafb;
            padding: 12px;
            border-radius: 4px;
        }
        
        .detail-row {
            padding: 3px 0;
            font-size: 9pt;
        }
        
        .adjustment-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin: 15px 0;
            font-size: 9pt;
            font-style: italic;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        
        .items-table th {
            background: #dc2626;
            color: white;
            border: 1px solid #991b1b;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
        }
        
        .items-table td {
            border: 1px solid #d1d5db;
            padding: 8px 6px;
            background: white;
        }
        
        .items-table tbody tr:nth-child(even) td {
            background: #fef2f2;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .items-table .adjustment-qty {
            color: #dc2626;
            font-weight: bold;
        }
        
        .tax-summary-table {
            width: 50%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9.5pt;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.1);
        }
        
        .tax-summary-table th {
            background: linear-gradient(to bottom, #fee2e2, #fecaca);
            border: 1px solid #dc2626;
            padding: 10px 12px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #991b1b;
        }
        
        .tax-summary-table td {
            border: 1px solid #fecaca;
            padding: 10px 12px;
        }
        
        .tax-summary-table .text-right {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .tax-summary-table .subtotal-row td {
            background: #fef2f2;
            font-weight: 600;
            border-bottom: 2px solid #dc2626;
        }
        
        .tax-summary-table .tax-row td {
            background: #fffbeb;
        }
        
        .tax-summary-table .grand-total-row td {
            background: linear-gradient(to bottom, #fee2e2, #fecaca);
            font-weight: bold;
            font-size: 11pt;
            border-top: 3px double #dc2626;
            padding: 12px;
            color: #991b1b;
        }
        
        .receipt-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 10px 15mm;
            background: #fff;
        }
        
        .receipt-footer .verification-title {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 3px;
            color: #dc2626;
        }
        
        .receipt-footer .verification-code {
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .receipt-footer .qr-code {
            display: inline-block;
            margin: 5px 0;
        }
        
        .receipt-footer .verify-text {
            font-size: 7pt;
            color: #666;
            margin-top: 3px;
        }
        
        .footer-note {
            margin-top: 20px;
            font-size: 8pt;
            text-align: center;
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        @php
            $config = \App\Models\ZimraConfig::where('device_id', $receipt->device_id)->first();
            $companyName = $config->company_name ?? 'Company Name';
            $companyTin = $config->company_tin ?? 'TIN Number';
            
            // Get original receipt reference (FDMS spec items [24]-[28])
            $originalReceipt = null;
            $originalReceiptNo = null;
            $originalReceiptDate = null;
            $originalReceiptGlobalNo = null;
            $originalDeviceSerialNo = null;
            if (isset($receipt->original_receipt_id)) {
                $originalReceipt = \App\Models\Receipt::find($receipt->original_receipt_id);
                if ($originalReceipt) {
                    $originalReceiptNo = $originalReceipt->invoice_no;
                    $originalReceiptDate = $originalReceipt->receipt_date;
                    $originalReceiptGlobalNo = $originalReceipt->receipt_global_no;
                    // Get device serial number from config
                    $originalConfig = \App\Models\ZimraConfig::where('device_id', $originalReceipt->device_id)->first();
                    $originalDeviceSerialNo = $originalConfig->serial_number ?? $originalReceipt->device_id;
                }
            }
        @endphp
        
        <div class="header">
            <div class="company-logo">
                <div style="width: 60px; height: 60px; border: 2px solid #dc2626; display: flex; align-items: center; justify-content: center; font-size: 8pt; color: #dc2626; font-weight: bold;">LOGO</div>
            </div>
            <div class="debit-note-badge">
                DEBIT NOTE
            </div>
        </div>
        
        <div class="invoice-title">
            FISCAL TAX DEBIT NOTE
        </div>
        
        <div class="parties-section">
            <table>
                <tr>
                    <td>
                        <div class="party-box">
                            <div class="party-title">SELLER</div>
                            <div class="party-info">
                                <strong>{{ $companyName }}</strong><br>
                                TIN: {{ $companyTin }}<br>
                                @if($config && $config->company_address)
                                    {{ $config->company_address }}<br>
                                @endif
                                @if($config && $config->company_phone)
                                    Tel: {{ $config->company_phone }}<br>
                                @endif
                                @if($config && $config->company_email)
                                    Email: {{ $config->company_email }}<br>
                                @endif
                                Device ID: {{ $receipt->device_id }}
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="party-box">
                            <div class="party-title">BUYER</div>
                            <div class="party-info">
                                @if($receipt->buyer_data)
                                    @if(isset($receipt->buyer_data['buyerRegisterName']))
                                        <strong>{{ $receipt->buyer_data['buyerRegisterName'] }}</strong><br>
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerTIN']))
                                        TIN: {{ $receipt->buyer_data['buyerTIN'] }}<br>
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerContacts']['phoneNo']))
                                        Tel: {{ $receipt->buyer_data['buyerContacts']['phoneNo'] }}<br>
                                    @endif
                                @else
                                    <em>Cash Customer</em>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- FDMS Spec Items [17]-[23]: Current Debit Note Information -->
        <div class="invoice-details" style="display: table; width: 100%; margin: 15px 0;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[17] Invoice No (Receipt Counter): <strong>{{ $receipt->receipt_counter ?? 'N/A' }}</strong></div>
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[18] Invoice No (Receipt Global No): <strong>{{ $receipt->receipt_global_no }}</strong></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[19] Fiscal Day No: <strong>{{ $receipt->fiscal_day_no }}</strong></div>
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[20] Customer Reference No: <strong>{{ $receipt->invoice_no }}</strong></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[21] Device Serial No: <strong>{{ $config->serial_number ?? $receipt->device_id }}</strong></div>
                <div style="display: table-cell; width: 50%; padding: 5px; font-size: 9pt;">[22] Device ID: <strong>{{ $receipt->device_id }}</strong></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 100%; padding: 5px; font-size: 9pt;" colspan="2">[23] Receipt Date and Time: <strong>{{ $receipt->receipt_date->format('d/m/Y H:i:s') }}</strong></div>
            </div>
        </div>
        
        <!-- FDMS Spec Items [24]-[28]: Debited Invoice Information Block -->
        @if($originalReceipt)
        <div class="reference-section">
            <div class="reference-title">[24] Debited Invoice</div>
            <div class="reference-details">
                <div class="detail-row">[25] Device Serial No: <strong>{{ $originalDeviceSerialNo }}</strong></div>
                <div class="detail-row">[26] Invoice No (Receipt Global No): <strong>{{ $originalReceiptGlobalNo }}</strong></div>
                <div class="detail-row">[27] Receipt Date: <strong>{{ $originalReceiptDate->format('d/m/Y H:i:s') }}</strong></div>
                <div class="detail-row">[28] Customer Reference No: <strong>{{ $originalReceiptNo }}</strong></div>
                <div class="detail-row" style="margin-top: 10px;"><strong>Reason:</strong> {{ $receipt->receipt_notes ?? 'Quantity adjustment' }}</div>
            </div>
        </div>
        @endif
        
        @if($receipt->receipt_notes)
        <div class="adjustment-note">
            ⚠️ <strong>Reason for Debit Note:</strong> {{ $receipt->receipt_notes }}
        </div>
        @endif
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 35%;">Description</th>
                    <th class="text-center" style="width: 12%;">Adjustment Qty</th>
                    <th class="text-right" style="width: 12%;">Unit Price<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 12%;">Amount<br>(excl. Tax)<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 12%;">Tax<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 12%;">Total<br>({{ $receipt->receipt_currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->receipt_lines as $index => $line)
                @php
                    $linePrice = abs($line['receiptLinePrice'] ?? 0);
                    $lineTotal = abs($line['receiptLineTotal'] ?? 0);
                    $lineQty = $line['receiptLineQuantity'] ?? 1;
                    $taxPercent = $line['taxPercent'] ?? 0;
                    
                    // Calculate tax amount
                    if ($taxPercent > 0) {
                        $amountInclTax = $lineTotal;
                        $taxAmount = $lineTotal - ($lineTotal / (1 + ($taxPercent / 100)));
                        $amountExclTax = $lineTotal - $taxAmount;
                    } else {
                        $amountInclTax = $lineTotal;
                        $taxAmount = 0;
                        $amountExclTax = $lineTotal;
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $line['receiptLineName'] ?? 'Item' }}</strong>
                        @if(isset($line['receiptLineHSCode']) && $line['receiptLineHSCode'] != '0000')
                            <br><small style="color: #666;">HS Code: {{ $line['receiptLineHSCode'] }}</small>
                        @endif
                    </td>
                    <td class="text-center adjustment-qty">+{{ number_format($lineQty, 2) }}</td>
                    <td class="text-right">{{ number_format($linePrice, 2) }}</td>
                    <td class="text-right">{{ number_format($amountExclTax, 2) }}</td>
                    <td class="text-right">{{ number_format($taxAmount, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($amountInclTax, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        @php
            // Calculate totals
            $subtotal = 0;
            $taxBreakdown = [];
            
            if (isset($receipt->receipt_taxes) && is_array($receipt->receipt_taxes)) {
                foreach ($receipt->receipt_taxes as $tax) {
                    $taxPercent = $tax['taxPercent'] ?? 0;
                    $taxAmount = abs($tax['taxAmount'] ?? 0);
                    $salesAmount = abs($tax['salesAmountWithTax'] ?? 0);
                    
                    $label = $taxPercent == 0 ? 'Zero rated (0%)' : 'Tax (' . $taxPercent . '%)';
                    
                    if (!isset($taxBreakdown[$label])) {
                        $taxBreakdown[$label] = 0;
                    }
                    $taxBreakdown[$label] += $taxAmount;
                    $subtotal += ($salesAmount - $taxAmount);
                }
            }
            
            $grandTotal = abs($receipt->receipt_total);
        @endphp
        
        <div class="totals-section">
            <table class="tax-summary-table">
                <tr class="subtotal-row">
                    <td style="width: 60%;"><strong>Adjustment Sub Total</strong><br><span style="font-size: 8pt; color: #666;">(excl. Tax)</span></td>
                    <td style="width: 15%; text-align: center;"></td>
                    <td style="width: 25%;" class="text-right"><strong>{{ $receipt->receipt_currency }} {{ number_format($subtotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <th colspan="3">Tax Summary</th>
                </tr>
                @foreach($taxBreakdown as $label => $amount)
                <tr class="tax-row">
                    <td style="padding-left: 20px;">{{ $label }}</td>
                    <td></td>
                    <td class="text-right">{{ $receipt->receipt_currency }} {{ number_format($amount, 2) }}</td>
                </tr>
                @endforeach
                <tr class="grand-total-row">
                    <td><strong>Total Adjustment Amount</strong></td>
                    <td class="text-right"><strong>{{ $receipt->receipt_currency }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
            </table>
        </div>
        
        <div class="footer-note">
            This is an official ZIMRA fiscalized debit note. The amount shown represents the adjustment to the original invoice.
        </div>
    </div>
    
    <div class="receipt-footer">
        <div class="verification-title">Verification Code</div>
        @if(isset($verificationCode) && $verificationCode)
            <div class="verification-code">{{ $verificationCode }}</div>
        @elseif($receipt->verification_code)
            <div class="verification-code">{{ $receipt->verification_code }}</div>
        @endif
        @if(isset($qrCodeBase64) && $qrCodeBase64)
            <div class="qr-code">
                <img src="{{ $qrCodeBase64 }}" width="100" height="100" alt="QR Code">
            </div>
        @endif
        <div class="verify-text">Scan to verify debit note</div>
    </div>
</body>
</html>
