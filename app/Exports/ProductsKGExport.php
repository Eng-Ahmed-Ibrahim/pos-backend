<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;

class ProductsKGExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    public function query()
    {
        return Product::query()
            ->where('unit_id', 7)
            ->where('price', '>', 0)
            ->whereNotNull('barcode')
            ->whereHas('purchaseItems', function ($query) {
                $query->where('remaining_stock', '>', 0);
            });
    }

    public function map($product): array
    {
        $barcode = (string) ($product->barcode ?? '');

        // استخراج الكود (أول 5 أرقام أو الباركود كاملاً)
        $itemCode = strlen($barcode) >= 5 ? substr($barcode, 0, 5) : $barcode;

        $unitPrice = $product->price ?? 0;

        return [
            $itemCode,
            20,
            0,
            $itemCode,
            $product->name ?? '',
            '',
            '',
            1,
            0,
            7,
            0,
            'kg',
            $unitPrice,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            '.',
            0,
            '',
            '',
        ];
    }

    public function headings(): array
    {
        return [
            'PLUNo',
            'Department',
            'GroupNo',
            'Itemcode',
            'name1',
            'name2',
            'name3',
            'labelno',
            'Auxlabeno',
            'barcodetype',
            'barcodetype1',
            'weightunit',
            'unitprice',
            'shelfdays',
            'validdays',
            'packagerange',
            'packagetype',
            'packageweight',
            'Text1',
            'Text2',
            'Text3',
            'Text4',
            'Text5',
            'Text6',
            'Text7',
            'Text8',
            'discounttbl',
            'flag1',
            'flag2',
            'TareNo',
            'iceTbl',
            'ProducedDate',
            'PackedDate',
            'PackedTime',
            'flag3',
            'IngredientsNo',
            'NutriFactNo',
            'OriginNo',
            'Traceability',
            'discount',
            'TareWeight',
            'SaleMsg',
            'link1_discount',
            'link2_discount',
            'SaleMsgIdx',
            'limtMaxUnitpri',
            'img',
            'Packageprice',
            'GroupName',
            'DepartmentName'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
