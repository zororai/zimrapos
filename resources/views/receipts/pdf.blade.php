<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receipt->receipt_type === 'CreditNote' ? 'Credit Note' : ($receipt->receipt_type === 'DebitNote' ? 'Debit Note' : 'Fiscal Tax Invoice') }} - {{ $receipt->invoice_no }}</title>
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
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
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
        
        .invoice-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
        }
        
        .parties-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 20px;
        }
        
        .party-box {
            flex: 1;
            border: 1px solid #000;
            padding: 10px;
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
        @endphp
        
        <div class="header">
            <div class="company-logo">
                <!-- Logo placeholder - add your company logo here -->
                <div style="width: 60px; height: 60px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; font-size: 8pt;">LOGO</div>
            </div>
            <div class="verification-section" style="flex: 1; text-align: right;">
                <div class="verification-code">
                    <strong>Verification code</strong><br>
                    @if($receipt->receipt_qr_code)
                        {{ substr($receipt->receipt_qr_code, 0, 50) }}<br>
                        <a href="{{ $receipt->receipt_qr_code }}" style="font-size: 8pt;">{{ $receipt->receipt_qr_code }}</a>
                    @else
                        Verification URL not available
                    @endif
                </div>
                @if($receipt->receipt_qr_code)
                <div class="qr-code" id="qrcode"></div>
                @endif
            </div>
        </div>
        
        <div class="invoice-title">
            @if($receipt->receipt_type === 'CreditNote')
                FISCAL TAX CREDIT NOTE
            @elseif($receipt->receipt_type === 'DebitNote')
                FISCAL TAX DEBIT NOTE
            @else
                FISCAL TAX INVOICE
            @endif
        </div>
        
        <div class="parties-section">
            <div class="party-box">
                <div class="party-title">SELLER</div>
                <div class="party-info">
                    <strong>{{ $companyName }}</strong><br>
                    TIN: {{ $companyTin }}<br>
                    @if($config && $config->company_address)
                        {{ $config->company_address }}<br>
                    @endif
                    Device ID: {{ $receipt->device_id }}
                </div>
            </div>
            
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
        </div>
        
        <div class="invoice-details">
            <div class="detail-row">Invoice No: <strong>{{ $receipt->invoice_no }}</strong></div>
            <div class="detail-row">Date: <strong>{{ $receipt->receipt_date->format('d/m/Y H:i') }}</strong></div>
            <div class="detail-row">Fiscal device ID: <strong>{{ $receipt->device_id }}</strong></div>
            <div class="detail-row">Fiscal day No: <strong>{{ $receipt->fiscal_day_no }}</strong></div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Code</th>
                    <th style="width: 40%;">Description</th>
                    <th class="text-center" style="width: 8%;">Qty</th>
                    <th class="text-right" style="width: 12%;">Price</th>
                    <th class="text-right" style="width: 10%;">VAT</th>
                    <th class="text-right" style="width: 22%;">Total amount<br>(incl. tax)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->receipt_lines as $index => $line)
                <tr>
                    <td>{{ $line['receiptLineHSCode'] ?? ($index + 1) }}</td>
                    <td>{{ $line['receiptLineName'] ?? 'Item' }}</td>
                    <td class="text-center">{{ $line['receiptLineQuantity'] ?? 1 }}</td>
                    <td class="text-right">{{ number_format(abs($line['receiptLinePrice'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format(abs(($line['receiptLineTotal'] ?? 0) - ($line['receiptLineTotal'] ?? 0) / (1 + ($receipt->tax_percent / 100))), 2) }}</td>
                    <td class="text-right">{{ number_format(abs($line['receiptLineTotal'] ?? 0), 2) }}</td>
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
                    <td style="width: 25%;" class="text-right"><strong>{{ number_format($subtotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <th colspan="3">Tax Summary</th>
                </tr>
                @foreach($taxBreakdown as $label => $amount)
                <tr class="tax-row">
                    <td style="padding-left: 20px;">{{ $label }}</td>
                    <td></td>
                    <td class="text-right">{{ number_format($amount, 2) }}</td>
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
