<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير مشتريات الموردين</title>
    <style>
        body {
            font-family: 'cairo', 'XB Riyaz', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 13px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #666;
            padding: 8px 10px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            background-color: #e6f7ff;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>تقرير كشف حساب الموردين </h2>
        @if($from || $to)
            <p>الفترة: من {{ $from ?? 'بداية السجل' }} إلى {{ $to ?? 'اليوم' }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 42%;">اسم المورد</th>
                <th style="width: 25%;">قيمة الفواتير الإجمالية</th>
                <th style="width: 25%;">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($suppliers as $index => $supplier)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: right;">{{ $supplier['supplier_name'] }}</td>
                    <td>{{ number_format($supplier['total_amount'], 2) }} جنيه</td>
                    <td>{{ $supplier['notes'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">لا توجد بيانات متاحة لهذه الفترة</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" style="text-align: left;">الإجمالي العام:</td>
                <td>{{ number_format($grandTotal, 2) }} جنيه</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>