<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $receipt->invoice_no }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <style>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #f5f5f5;
        }
        
        .receipt-container {
            max-width: 400px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 11px;
            color: #666;
        }
        
        .info-section {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th {
            text-align: left;
            padding: 8px 5px;
            border-bottom: 1px solid #333;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .items-table td {
            padding: 8px 5px;
            border-bottom: 1px dashed #ddd;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .totals {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #333;
        }
        
        .tax-info {
            background: #f9f9f9;
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
        }
        
        .tax-info h4 {
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 5px;
            color: #666;
        }
        
        .zimra-section {
            background: #e8f5e9;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #4caf50;
        }
        
        .zimra-section h4 {
            font-size: 12px;
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .zimra-section .info-row {
            font-size: 10px;
        }
        
        .signature-box {
            background: #fff3e0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            word-break: break-all;
            font-size: 9px;
            font-family: monospace;
        }
        
        .qr-placeholder {
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
            border: 1px dashed #ccc;
            margin: 15px 0;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        
        .print-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: #4caf50;
            color: white;
            border: none;
            font-size: 14px;
            cursor: pointer;
            margin-top: 20px;
            border-radius: 5px;
        }
        
        .print-btn:hover {
            background: #388e3c;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>FISCAL INVOICE</h1>
            <div class="subtitle">ZIMRA Compliant Receipt</div>
        </div>
        
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Invoice No:</span>
                <span class="info-value">{{ $receipt->invoice_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value">{{ $receipt->receipt_date->format('d M Y H:i') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Receipt Type:</span>
                <span class="info-value">{{ $receipt->receipt_type }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Device ID:</span>
                <span class="info-value">{{ $receipt->device_id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fiscal Day:</span>
                <span class="info-value">{{ $receipt->fiscal_day_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Receipt Counter:</span>
                <span class="info-value">{{ $receipt->receipt_counter }}</span>
            </div>
        </div>
        
        @if($receipt->buyer_data)
        <div class="info-section" style="background: #f0f8ff; padding: 10px; border-radius: 5px;">
            <h4 style="font-size: 12px; margin-bottom: 8px; color: #1976d2;">CUSTOMER DETAILS</h4>
            @if(isset($receipt->buyer_data['buyerRegisterName']))
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $receipt->buyer_data['buyerRegisterName'] }}</span>
            </div>
            @endif
            @if(isset($receipt->buyer_data['buyerTradeName']))
            <div class="info-row">
                <span class="info-label">Trading Name:</span>
                <span class="info-value">{{ $receipt->buyer_data['buyerTradeName'] }}</span>
            </div>
            @endif
            @if(isset($receipt->buyer_data['vatNumber']))
            <div class="info-row">
                <span class="info-label">VAT Number:</span>
                <span class="info-value">{{ $receipt->buyer_data['vatNumber'] }}</span>
            </div>
            @endif
            @if(isset($receipt->buyer_data['buyerTIN']))
            <div class="info-row">
                <span class="info-label">TIN:</span>
                <span class="info-value">{{ $receipt->buyer_data['buyerTIN'] }}</span>
            </div>
            @endif
            @if(isset($receipt->buyer_data['buyerContacts']))
                @if(isset($receipt->buyer_data['buyerContacts']['phoneNo']))
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $receipt->buyer_data['buyerContacts']['phoneNo'] }}</span>
                </div>
                @endif
                @if(isset($receipt->buyer_data['buyerContacts']['email']))
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $receipt->buyer_data['buyerContacts']['email'] }}</span>
                </div>
                @endif
            @endif
            @if(isset($receipt->buyer_data['buyerAddress']))
                @php
                    $address = [];
                    if(isset($receipt->buyer_data['buyerAddress']['houseNo'])) $address[] = $receipt->buyer_data['buyerAddress']['houseNo'];
                    if(isset($receipt->buyer_data['buyerAddress']['street'])) $address[] = $receipt->buyer_data['buyerAddress']['street'];
                    if(isset($receipt->buyer_data['buyerAddress']['district'])) $address[] = $receipt->buyer_data['buyerAddress']['district'];
                    if(isset($receipt->buyer_data['buyerAddress']['city'])) $address[] = $receipt->buyer_data['buyerAddress']['city'];
                    if(isset($receipt->buyer_data['buyerAddress']['province'])) $address[] = $receipt->buyer_data['buyerAddress']['province'];
                    $fullAddress = implode(', ', array_filter($address));
                @endphp
                @if($fullAddress)
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">{{ $fullAddress }}</span>
                </div>
                @endif
            @endif
        </div>
        @endif
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->receipt_lines as $line)
                <tr>
                    <td>{{ $line['receiptLineName'] ?? 'Item' }}</td>
                    <td class="text-right">{{ $line['receiptLineQuantity'] ?? 1 }}</td>
                    <td class="text-right">{{ $receipt->receipt_currency }} {{ number_format($line['receiptLinePrice'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $receipt->receipt_currency }} {{ number_format($line['receiptLineTotal'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="tax-info">
            <h4>Tax Information</h4>
            <div class="info-row">
                <span class="info-label">Tax Code:</span>
                <span class="info-value">{{ $receipt->tax_code }} ({{ $receipt->tax_percent }}%)</span>
            </div>
            <div class="info-row">
                <span class="info-label">Tax Amount:</span>
                <span class="info-value">{{ $receipt->receipt_currency }} {{ number_format($receipt->tax_amount, 2) }}</span>
            </div>
        </div>
        
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>{{ $receipt->receipt_currency }} {{ number_format($receipt->receipt_total - $receipt->tax_amount, 2) }}</span>
            </div>
            <div class="total-row">
                <span>VAT ({{ $receipt->tax_percent }}%):</span>
                <span>{{ $receipt->receipt_currency }} {{ number_format($receipt->tax_amount, 2) }}</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>{{ $receipt->receipt_currency }} {{ number_format($receipt->receipt_total, 2) }}</span>
            </div>
        </div>
        
        <div class="info-section" style="margin-top: 15px;">
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">{{ $receipt->payment_method }}</span>
            </div>
        </div>
        
        <div class="qr-code-section" style="text-align: center; margin: 20px 0;">
            <h4 style="font-size: 11px; color: #666; margin-bottom: 10px;">SCAN TO VERIFY</h4>
            <div id="qrcode" style="display: inline-block;"></div>
            <p style="font-size: 9px; color: #888; margin-top: 5px; word-break: break-all;">
                @if($receipt->receipt_qr_code)
                    {{ $receipt->receipt_qr_code }}
                @else
                    QR not available - ensure getConfig was called
                @endif
            </p>
        </div>
        
        <div class="footer">
            <p>This is a fiscalized receipt registered with ZIMRA</p>
            <p>Generated on {{ now()->format('d M Y H:i:s') }}</p>
        </div>
        
        <div class="no-print" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <button class="print-btn" onclick="window.print()">
                Print / Save as PDF
            </button>
            @if($receipt->receipt_qr_code)
            <a href="{{ $receipt->receipt_qr_code }}" target="_blank" class="print-btn" style="text-decoration: none; background: #2563eb;">
                Verify on ZIMRA Portal
            </a>
            @endif
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ONLY use stored QR string - NO dynamic fallback
            var qrString = '{{ $receipt->receipt_qr_code ?? "" }}';
            
            if (qrString && typeof qrcode !== 'undefined') {
                var qr = qrcode(0, 'M');
                qr.addData(qrString);
                qr.make();
                document.getElementById('qrcode').innerHTML = qr.createImgTag(4);
            } else {
                document.getElementById('qrcode').innerHTML = '<p style="color:#999;font-size:10px;">QR not available - receipt was submitted before getConfig was called</p>';
            }
        });
    </script>
</body>
</html>
