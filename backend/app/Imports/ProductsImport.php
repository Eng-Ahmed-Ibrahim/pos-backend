<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\PurchaseItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * Imports an opening-balance ("رصيد أول مدة") stock sheet into purchase_items.
 *
 * The purchase (invoice) must already exist — this class never invents a
 * purchase_id. Create it first, e.g.:
 *
 *   $purchase = Purchase::create([
 *       'type' => 'opening_balance',
 *       'supplier_id' => null,
 *       'date' => now(),
 *       'note' => 'رصيد أول مدة - جرد 31-8-2026',
 *   ]);
 *
 *   (new ProductsImport($purchase->id))->import($file);
 */
class ProductsImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    private int $purchaseId;

    public int $createdCount = 0;
    public int $updatedCount = 0;
    public int $skippedNoBarcode = 0;
    public int $skippedInvalidData = 0;
    public int $skippedProductNotFound = 0;

    /** @var array<int, string> barcodes not matched to a product */
    public array $notFoundBarcodes = [];

    public function __construct(int $purchaseId)
    {
        $this->purchaseId = $purchaseId;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        // Keyed by barcode so duplicate rows for the same barcode in the
        // sheet are merged instead of one silently overwriting the other.
        $itemsByBarcode = [];

        foreach ($rows as $row) {
            $barcode = trim((string) ($row['barcode'] ?? ''));

            if ($barcode === '') {
                $this->skippedNoBarcode++;
                continue;
            }

            $price = $this->parseDecimal($row['purchase_price'] ?? null);
            $quantity = $this->parseDecimal($row['quantity'] ?? null);

            if ($price === null || $quantity === null || $quantity <= 0) {
                $this->skippedInvalidData++;
                continue;
            }

            if (isset($itemsByBarcode[$barcode])) {
                // Same barcode appears twice in the sheet: sum the quantity,
                // keep the latest price.
                $itemsByBarcode[$barcode]['quantity'] += $quantity;
                $itemsByBarcode[$barcode]['remaining_stock'] += $quantity;
                $itemsByBarcode[$barcode]['price'] = $price;
            } else {
                $itemsByBarcode[$barcode] = [
                    'barcode' => $barcode,
                    'price' => $price,
                    'quantity' => $quantity,
                    'remaining_stock' => $quantity,
                    'expire_date' => '2030-01-01',
                ];
            }
        }

        if (empty($itemsByBarcode)) {
            return;
        }

        $products = Product::query()
            ->whereIn('barcode', array_keys($itemsByBarcode))
            ->pluck('id', 'barcode');

        $purchaseItems = [];

        foreach ($itemsByBarcode as $barcode => $item) {
            $productId = $products[$barcode] ?? null;

            if (!$productId) {
                $this->skippedProductNotFound++;
                $this->notFoundBarcodes[] = $barcode;

                Log::warning('Opening balance import: product not found', [
                    'barcode' => $barcode,
                ]);

                continue;
            }

            $purchaseItems[] = [
                'purchase_id' => $this->purchaseId,
                'product_id' => $productId,
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'remaining_stock' => $item['remaining_stock'],
                'expire_date' => $item['expire_date'],
            ];
        }

        if (empty($purchaseItems)) {
            return;
        }

        DB::transaction(function () use ($purchaseItems) {
            // Existing rows for the same (purchase_id, product_id) are
            // updated instead of duplicated.
            $existing = PurchaseItems::query()
                ->where('purchase_id', $this->purchaseId)
                ->whereIn('product_id', array_column($purchaseItems, 'product_id'))
                ->pluck('product_id')
                ->flip();

            PurchaseItems::upsert(
                $purchaseItems,
                ['purchase_id', 'product_id'],
                ['price', 'quantity', 'remaining_stock', 'expire_date']
            );

            foreach ($purchaseItems as $item) {
                if (isset($existing[$item['product_id']])) {
                    $this->updatedCount++;
                } else {
                    $this->createdCount++;
                }
            }
        });
    }


    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                Log::info('Opening balance import finished', [
                    'purchase_id' => $this->purchaseId,
                    'created' => $this->createdCount,
                    'updated' => $this->updatedCount,
                    'skipped_no_barcode' => $this->skippedNoBarcode,
                    'skipped_invalid_data' => $this->skippedInvalidData,
                    'skipped_product_not_found' => $this->skippedProductNotFound,
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
