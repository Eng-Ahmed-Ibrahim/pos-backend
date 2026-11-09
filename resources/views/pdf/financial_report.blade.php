<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>التقرير المالي</title>
    <style>
        body {
            font-family: 'Amiri', DejaVu Sans, sans-serif;
            direction: rtl;
            text-align: right;
            color: #333;
            font-size: 14px;
        }
        h2 {
            text-align: center;
            color: #111;
            margin-bottom: 5px;
        }
        .date-range {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: center;
            font-size: 12px;
        }
        th {
            background-color: #f2f2f2;
            color: #222;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h2>التقرير المالي للإحصائيات والمخزون</h2>
    <div class="date-range">الفترة من: {{ $start_at }} إلى: {{ $end_at }}</div>

    <table>
        <thead>
            <tr>
                <th>قيمة البضاعة أول الفترة</th>
                <th>قيمة المشتريات خلال الفترة</th>
                <th>قيمة المرتجعات</th>
                <th>قيمة الهالك</th>
                <th>تكلفة البضاعة المباعة</th>
                <th>قيمة البضاعة آخر الفترة</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($start_value, 2) }} ج.م</td>
                <td>{{ number_format($purchases_value, 2) }} ج.م</td>
                <td>{{ number_format($purchase_returns_value, 2) }} ج.م</td>
                <td>{{ number_format($waste_value, 2) }} ج.م</td>
                <td>{{ number_format($sold_cost_value, 2) }} ج.م</td>
                <td>{{ number_format($end_value, 2) }} ج.م</td>
            </tr>
        </tbody>
    </table>

</body>
</html>