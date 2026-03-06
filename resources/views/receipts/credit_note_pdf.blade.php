<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note - {{ $receipt->invoice_no }}</title>
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
            border-bottom: 3px solid #0052a3;
        }
        
        .credit-note-badge {
            background: linear-gradient(135deg, #0052a3, #0066cc);
            color: white;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 11pt;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 4px rgba(0,82,163,0.3);
        }
        
        .company-logo {
            flex: 0 0 80px;
        }
        
        .company-logo img {
            max-width: 80px;
            max-height: 80px;
        }
        
        .verification-section {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .verification-code {
            font-size: 9pt;
            color: #0066cc;
            margin: 5px 0;
        }
        
        .qr-code {
            display: inline-block;
            margin: 10px 0;
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
        
        .receipt-footer .verification-url {
            font-size: 6pt;
            word-break: break-all;
            color: #0066cc;
            margin: 3px 0;
        }
        
        .invoice-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
            color: #0052a3;
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
        }
        
        .party-info {
            font-size: 9pt;
            line-height: 1.5;
        }
        
        .invoice-details {
            margin-bottom: 15px;
        }
        
        .detail-row {
            padding: 3px 0;
            font-size: 9pt;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        
        .items-table th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
        }
        
        .items-table td {
            border: 1px solid #000;
            padding: 6px 4px;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .totals-section {
            margin-top: 15px;
        }
        
        .tax-summary-table {
            width: 50%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9.5pt;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tax-summary-table th {
            background: linear-gradient(to bottom, #e8e8e8, #d0d0d0);
            border: 1px solid #999;
            padding: 10px 12px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tax-summary-table td {
            border: 1px solid #ccc;
            padding: 10px 12px;
        }
        
        .tax-summary-table .text-right {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .tax-summary-table .subtotal-row td {
            background: #f5f5f5;
            font-weight: 600;
            border-bottom: 2px solid #999;
        }
        
        .tax-summary-table .tax-row td {
            background: #fafafa;
        }
        
        .tax-summary-table .tax-row:hover td {
            background: #f0f0f0;
        }
        
        .tax-summary-table .grand-total-row td {
            background: linear-gradient(to bottom, #f8f8f8, #e8e8e8);
            font-weight: bold;
            font-size: 11pt;
            border-top: 3px double #000;
            padding: 12px;
        }
        
        .footer-note {
            margin-top: 20px;
            font-size: 8pt;
            font-style: italic;
            text-align: center;
        }
        
        .print-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 12px;
            background: #0066cc;
            color: white;
            border: none;
            font-size: 11pt;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        
        .print-btn:hover {
            background: #0052a3;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        @php
            $config = \App\Models\ZimraConfig::where('device_id', $receipt->device_id)->first();
            $companyName = $config->company_name ?? 'Company Name';
            $companyTin = $config->company_tin ?? 'TIN Number';
            
            // Get original receipt reference for credit note (FDMS spec items [24]-[28])
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
                    $originalConfig = \App\Models\ZimraConfig::where('device_id', $originalReceipt->device_id)->first();
                    $originalDeviceSerialNo = $originalConfig->serial_number ?? $originalReceipt->device_id;
                }
            }
        @endphp
        
        <div class="header">
            <div class="company-logo">
                <div style="width: 60px; height: 60px; border: 2px solid #0052a3; display: flex; align-items: center; justify-content: center; font-size: 8pt; color: #0052a3; font-weight: bold;">LOGO</div>
            </div>
            <div class="credit-note-badge">
                CREDIT NOTE
            </div>
        </div>
        
        <div class="invoice-title">
            FISCAL TAX CREDIT NOTE
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
                                    @if(isset($receipt->buyer_data['buyerTradeName']) && $receipt->buyer_data['buyerTradeName'] != $receipt->buyer_data['buyerRegisterName'])
                                        Trading as: {{ $receipt->buyer_data['buyerTradeName'] }}<br>
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerTIN']))
                                        TIN: {{ $receipt->buyer_data['buyerTIN'] }}<br>
                                    @endif
                                    @if(isset($receipt->buyer_data['vatNumber']))
                                        VAT: {{ $receipt->buyer_data['vatNumber'] }}<br>
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerAddress']))
                                        @php
                                            $address = [];
                                            if(isset($receipt->buyer_data['buyerAddress']['houseNo'])) $address[] = $receipt->buyer_data['buyerAddress']['houseNo'];
                                            if(isset($receipt->buyer_data['buyerAddress']['street'])) $address[] = $receipt->buyer_data['buyerAddress']['street'];
                                            if(isset($receipt->buyer_data['buyerAddress']['district'])) $address[] = $receipt->buyer_data['buyerAddress']['district'];
                                            if(isset($receipt->buyer_data['buyerAddress']['city'])) $address[] = $receipt->buyer_data['buyerAddress']['city'];
                                            $fullAddress = implode(', ', array_filter($address));
                                        @endphp
                                        @if($fullAddress)
                                            {{ $fullAddress }}<br>
                                        @endif
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerContacts']['phoneNo']))
                                        Tel: {{ $receipt->buyer_data['buyerContacts']['phoneNo'] }}<br>
                                    @endif
                                    @if(isset($receipt->buyer_data['buyerContacts']['email']))
                                        Email: {{ $receipt->buyer_data['buyerContacts']['email'] }}
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
        
        <!-- FDMS Spec Items [17]-[23]: Current Receipt Information -->
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
            @if(isset($receipt->date_issued))
            <div style="display: table-row;">
                <div style="display: table-cell; width: 100%; padding: 5px; font-size: 9pt;" colspan="2">Date Issued: <strong>{{ \Carbon\Carbon::parse($receipt->date_issued)->format('d/m/Y') }}</strong></div>
            </div>
            @endif
            @if(isset($receipt->payment_due))
            <div style="display: table-row;">
                <div style="display: table-cell; width: 100%; padding: 5px; font-size: 9pt;" colspan="2">Payment Due: <strong>{{ \Carbon\Carbon::parse($receipt->payment_due)->format('d/m/Y') }}</strong></div>
            </div>
            @endif
        </div>
        
        <!-- FDMS Spec Items [24]-[28]: Credited Invoice Information Block -->
        @if($originalReceipt)
        <div style="margin: 15px 0; padding: 10px; border: 2px solid #0052a3; background: #eff6ff;">
            <div style="font-weight: bold; font-size: 10pt; margin-bottom: 8px; color: #0052a3;">
                [24] Credited Invoice
            </div>
            <div style="font-size: 9pt; line-height: 1.6;">
                <div>[25] Device Serial No: <strong>{{ $originalDeviceSerialNo }}</strong></div>
                <div>[26] Invoice No (Receipt Global No): <strong>{{ $originalReceiptGlobalNo }}</strong></div>
                <div>[27] Receipt Date: <strong>{{ $originalReceiptDate->format('d/m/Y H:i:s') }}</strong></div>
                <div>[28] Customer Reference No: <strong>{{ $originalReceiptNo }}</strong></div>
                @if($receipt->receipt_notes)
                <div style="margin-top: 8px;"><strong>Reason:</strong> {{ $receipt->receipt_notes }}</div>
                @endif
            </div>
        </div>
        @endif
        
        @if($receipt->receipt_notes)
        <div style="background: #eff6ff; border-left: 4px solid #0052a3; padding: 12px; margin: 15px 0; font-size: 9pt;">
            ⚠️ <strong>Reason for Credit Note:</strong> {{ $receipt->receipt_notes }}
        </div>
        @endif
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">Code</th>
                    <th style="width: 28%;">Description</th>
                    <th class="text-center" style="width: 6%;">Qty</th>
                    <th class="text-right" style="width: 9%;">Price<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 9%;">Discount<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 12%;">Amount<br>(excl. Tax)<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 9%;">Tax<br>({{ $receipt->receipt_currency }})</th>
                    <th class="text-right" style="width: 12%;">Amount<br>(incl. Tax)<br>({{ $receipt->receipt_currency }})</th>
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
                    $taxPercent = $line['taxPercent'] ?? $receipt->tax_percent ?? 0;
                    
                    // Calculate discount amount (0 for sale lines, show amount for discount lines)
                    $discountAmount = $isDiscount ? $lineTotal : 0;
                    
                    // Calculate amounts
                    if ($taxPercent > 0) {
                        // Tax inclusive calculation
                        $amountInclTax = $lineTotal;
                        $taxAmount = $lineTotal - ($lineTotal / (1 + ($taxPercent / 100)));
                        $amountExclTax = $lineTotal - $taxAmount;
                    } else {
                        // No tax
                        $amountInclTax = $lineTotal;
                        $taxAmount = 0;
                        $amountExclTax = $lineTotal;
                    }
                @endphp
                <tr @if($isDiscount) style="background-color: #fff8dc;" @endif>
                    <td>{{ $line['receiptLineHSCode'] ?? ($index + 1) }}</td>
                    <td>
                        @if($isDiscount)
                            <em style="color: #d97706;">{{ $line['receiptLineName'] ?? 'Discount' }}</em>
                        @else
                            {{ $line['receiptLineName'] ?? 'Item' }}
                        @endif
                    </td>
                    <td class="text-center">{{ $lineQty }}</td>
                    <td class="text-right">{{ number_format($linePrice, 2) }}</td>
                    <td class="text-right" style="color: {{ $discountAmount > 0 ? '#d97706' : '#666' }};">
                        @if($discountAmount > 0)
                            ({{ number_format($discountAmount, 2) }})
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($amountExclTax, 2) }}</td>
                    <td class="text-right">{{ number_format($taxAmount, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($amountInclTax, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        @php
            // Calculate subtotal (excluding tax)
            $subtotal = 0;
            $taxBreakdown = [];
            
            // Group taxes by type
            if (isset($receipt->receipt_taxes) && is_array($receipt->receipt_taxes)) {
                foreach ($receipt->receipt_taxes as $tax) {
                    $taxPercent = $tax['taxPercent'] ?? 0;
                    $taxAmount = abs($tax['taxAmount'] ?? 0);
                    $salesAmount = abs($tax['salesAmountWithTax'] ?? 0);
                    $taxCode = $tax['taxCode'] ?? null;
                    
                    // Determine tax label
                    if ($taxPercent == 0) {
                        $label = 'Zero rated (0%)';
                    } elseif ($taxPercent == 15 || $taxPercent == 15.5) {
                        $label = 'Standard rated (' . $taxPercent . '%)';
                    } elseif ($taxPercent == 5) {
                        $label = 'Non-VAT Withholding Tax (5%)';
                    } else {
                        $label = 'Exempt';
                    }
                    
                    if (!isset($taxBreakdown[$label])) {
                        $taxBreakdown[$label] = 0;
                    }
                    $taxBreakdown[$label] += $taxAmount;
                    
                    // Add to subtotal (sales amount minus tax)
                    $subtotal += ($salesAmount - $taxAmount);
                }
            }
            
            // If no taxes, calculate from total
            if (empty($taxBreakdown)) {
                $subtotal = abs($receipt->receipt_total) - abs($receipt->tax_amount);
                if ($receipt->tax_percent > 0) {
                    $taxBreakdown['Standard rated (' . $receipt->tax_percent . '%)'] = abs($receipt->tax_amount);
                } else {
                    $taxBreakdown['Zero rated (0%)'] = 0;
                }
            }
            
            $grandTotal = abs($receipt->receipt_total);
        @endphp
        
        <div class="totals-section">
            <table class="tax-summary-table">
                <tr class="subtotal-row">
                    <td style="width: 60%;"><strong>Sub Total</strong><br><span style="font-size: 8pt; color: #666;">(excl. Tax)</span></td>
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
                    <td><strong>Grand Total</strong></td>
                    <td class="text-right"><strong>{{ $receipt->receipt_currency }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
            </table>
        </div>
        
        <div class="footer-note">
            Invoice is issued after purchasing goods according to agreement No.555
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
        @if($receipt->receipt_qr_code)
            <div class="verification-url">
                <a href="{{ $receipt->receipt_qr_code }}" style="color: #0066cc;">{{ $receipt->receipt_qr_code }}</a>
            </div>
        @endif
        <div class="verify-text">Scan to verify receipt</div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var qrString = '{{ $receipt->receipt_qr_code ?? "" }}';
            var qrElement = document.getElementById('qrcode');
            
            if (qrString && qrElement && typeof qrcode !== 'undefined') {
                var qr = qrcode(0, 'M');
                qr.addData(qrString);
                qr.make();
                qrElement.innerHTML = qr.createImgTag(3);
            }
        });
    </script>
</body>
</html>
