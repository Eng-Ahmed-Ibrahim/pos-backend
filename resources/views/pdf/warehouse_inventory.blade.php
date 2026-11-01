
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: dejavusans;
            direction: rtl;
            text-align: right;
            font-size: 11px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            font-size: 20px;
            margin-bottom: 5px;
            color: #333;
        }

        .info {
            margin-bottom: 15px;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #eee;
            font-weight: bold;
            padding: 10px 5px;
            border: 1px solid #ccc;
            text-align: center;
        }

        td {
            padding: 8px 5px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .total-row {
            background-color: #e8f0e8;
            font-weight: bold;
        }

        .total-box {
            margin-top: 20px;
            padding: 12px;
            border: 1px solid #999;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>تقرير جرد المخزون</h2>
        <div>شهر {{ $monthName }}</div>
    </div>

    <div class="info">
        <strong>تاريخ إصدار التقرير:</strong>
        {{ $generatedAt }}

        <br>

        <strong>عدد المنتجات:</strong>
        {{ $inventory->count() }}
    </div>

    <table>

        <thead>
            <tr>
                <th>#</th>
                <th>الباركود</th>
                <th>المنتج</th>
                <th>مرحّل من الشهر السابق</th>
                <th>مشتريات جديدة</th>
                <th>المخزون الحالي</th>
                <th>قيمة المخزون</th>
            </tr>
        </thead>

        <tbody>

            @forelse($inventory as $index => $item)

                <tr>
                    <td>{{ $index + 1 }}</td>

                    <td>{{ $item['product_barcode'] }}</td>

                    <td>{{ $item['product_name'] }}</td>

                    <td>{{ number_format($item['carried_forward'], 2) }}</td>

                    <td>{{ number_format($item['new_purchases'], 2) }}</td>

                    <td>{{ number_format($item['current_stock'], 2) }}</td>

                    <td>
                        {{ number_format($item['current_stock_value'], 2) }}
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="7">
                        لا توجد بيانات مخزون
                    </td>
                </tr>

            @endforelse

        </tbody>

        @if($inventory->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="6">إجمالي قيمة المخزون</td>
                    <td>
                        {{ number_format($totalInventoryValue, 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif

    </table>

    <div class="total-box">
        إجمالي قيمة المخزون الحالي:

        {{ number_format($totalInventoryValue, 2) }}
    </div>

    <div class="footer">
        تم إنشاء التقرير بواسطة نظام إدارة المخازن
    </div>

</body>
</html>        
