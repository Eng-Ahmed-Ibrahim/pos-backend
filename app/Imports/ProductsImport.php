<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    public int $createdCount = 0;
    public int $updatedCount = 0;

    private array $batch = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            $barcode = trim((string) ($row['barcode'] ?? ''));
            $name    = trim((string) ($row['item_name'] ?? $row['name'] ?? ''));

            if (!$barcode || !$name) {
                continue;
            }

            $this->batch[] = [
                'barcode'     => $barcode,
                'name'        => $name,
                'brand_id'    => 1,
                'category_id' => 1,
                'price'       => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];

            // كل 1000 سجل نعمل insert
            if (count($this->batch) >= 1000) {
                $this->flush();
            }
        }

        $this->flush();
    }

    private function flush()
    {
        if (empty($this->batch)) return;

        Product::upsert(
            $this->batch,
            ['barcode'],
            ['name', 'brand_id', 'category_id', 'price', 'updated_at']
        );

        // تقدير counts (تقريبية)
        $this->createdCount += count($this->batch);

        $this->batch = [];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}