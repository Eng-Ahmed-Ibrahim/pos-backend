<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Product;
use App\Models\PurchaseItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * استيراد المنتجات من الصفر (بعد مسح جدول products بالكامل).
 * كل صف بيتحول لمنتج جديد، وبراند المنتج بياخد قيمته من "اسم المجموعة".
 * لو الصف عنده كمية وسعر شراء صحيحين، بيتضاف purchase_items على فاتورة
 * الرصيد الافتتاحي (purchase_id = 1).
 */
class ProductsImport implements ToCollection, WithChunkReading, WithEvents
{
    private const OPENING_BALANCE_PURCHASE_ID = 1;

    public int $productsCreated = 0;
    public int $purchaseItemsCreated = 0;
    public int $skippedNoBarcode = 0;

    /** @var array<string, int> اسم المجموعة => brand_id، كاش عشان منكررش الكويري */
    private array $brandCache = [];

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        // دمج الصفوف اللي ليها نفس الباركود جوه نفس الـ chunk
        $rowsByBarcode = [];

        foreach ($rows as $row) {
            // 0 اسم المجموعة | 1 اسم المنتج | 2 باركود | 3 سعر بيع | 4 سعر شراء | 5 العدد | 6 الاجمالي
            $groupName   = trim((string) ($row[0] ?? ''));
            $productName = trim((string) ($row[1] ?? ''));
            $barcode     = trim((string) ($row[2] ?? ''));
            $sellPrice   = $this->parseDecimal($row[3] ?? null);
            $buyPrice    = $this->parseDecimal($row[4] ?? null);
            $quantity    = $this->parseDecimal($row[5] ?? null);

            if ($barcode === '') {
                $this->skippedNoBarcode++;
                continue;
            }

            if (isset($rowsByBarcode[$barcode])) {
                // نفس الباركود اتكرر: نجمع الكمية ونفضّل آخر سعر شراء
                if ($quantity !== null) {
                    $rowsByBarcode[$barcode]['quantity'] = ($rowsByBarcode[$barcode]['quantity'] ?? 0) + $quantity;
                }
                if ($buyPrice !== null) {
                    $rowsByBarcode[$barcode]['buy_price'] = $buyPrice;
                }
                continue;
            }

            $rowsByBarcode[$barcode] = [
                'group_name'   => $groupName,
                'product_name' => $productName !== '' ? $productName : $barcode,
                'sell_price'   => $sellPrice,
                'buy_price'    => $buyPrice,
                'quantity'     => $quantity,
            ];
        }

        if (empty($rowsByBarcode)) {
            return;
        }

        DB::transaction(function () use ($rowsByBarcode) {
            foreach ($rowsByBarcode as $barcode => $data) {
                $brandId = $this->resolveBrandId($data['group_name']);

                $product = Product::updateOrCreate(
                    ['barcode' => $barcode],
                    [
                        'name'     => $data['product_name'],
                        'price'    => $data['sell_price'] ?? 0,
                        'brand_id' => $brandId,
                    ]
                );

                $this->productsCreated++;

                $hasValidStock = $data['buy_price'] !== null
                    && $data['quantity'] !== null
                    && $data['quantity'] > 0;

                if ($hasValidStock) {
                    PurchaseItems::updateOrCreate(
                        [
                            'purchase_id' => self::OPENING_BALANCE_PURCHASE_ID,
                            'product_id'  => $product->id,
                        ],
                        [
                            'price'           => $data['buy_price'],
                            'quantity'        => $data['quantity'],
                            'remaining_stock' => $data['quantity'],
                            'expire_date'     => '2030-01-01',
                        ]
                    );

                    $this->purchaseItemsCreated++;
                }
            }
        });
    }

    private function resolveBrandId(string $groupName): ?int
    {
        if ($groupName === '') {
            return null;
        }

        if (!isset($this->brandCache[$groupName])) {
            $this->brandCache[$groupName] = Brand::firstOrCreate(['name' => $groupName])->id;
        }

        return $this->brandCache[$groupName];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                Log::info('Fresh product import finished', [
                    'products_created' => $this->productsCreated,
                    'purchase_items_created' => $this->purchaseItemsCreated,
                    'skipped_no_barcode' => $this->skippedNoBarcode,
                ]);
            },
        ];
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || strtoupper($value) === 'NULL') {
                return null;
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}