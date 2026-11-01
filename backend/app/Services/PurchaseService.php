<?php

namespace App\Services;

use App\Helpers\Helpers;
use App\Models\Purchase;
use App\Models\PurchaseItems;
use App\Repositories\PurchaseItemRepository;
use App\Repositories\PurchasesRepository;
use Illuminate\Support\Facades\Storage;

class PurchaseService
{

    function __construct(
        protected PurchaseItemRepository $purchaseItemRepo,
        protected PurchasesRepository $purchaseRepo,
        protected ImageUploadService $imageService,
        protected ProductService $productService
    ) {}
    public function createInvoice(array $validated, $imageFile = null)
    {
        if ($imageFile) {
            $validated['image'] = $this->imageService->upload($imageFile, 'uploads/purchases');
        }

        $total = $this->calculateTotalInvoicePrice($validated['items']);
        $purchase = $this->addInvoice($total, $validated);
        $this->addItems($validated['items'], $purchase, $validated['date']);
        $this->productService->clear();

        return $purchase;
    }

    public function updateInvoiceItems(Purchase $purchase, array $validated, $imageFile = null)
    {
        if ($imageFile) {
            $this->imageService->delete($purchase->image);
            $validated['image'] = $this->imageService->upload($imageFile, 'uploads/purchases');
        }

        $oldItemsMap = $purchase->items->keyBy('product_id');
        $newItemsMap = collect($validated['items'])->keyBy('product_id');

        $this->updateOldItems($oldItemsMap, $newItemsMap);
        $this->addNewItemsToInvoice($oldItemsMap, $newItemsMap, $purchase);

        $total = $this->calculateTotalInvoicePrice($validated['items']);
        $this->productService->clear();

        return $this->updatePurchase($purchase, $validated, $total);
    }



    public function calculateTotalInvoicePrice(array $items): float
    {
        return collect($items)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });
    }



    private function addInvoice(float $total, array $validated): Purchase
    {
        return $this->purchaseRepo->create([
            'supplier_id' => $validated['supplier_id'],
            'date'        => $validated['date'],
            'total'       => $total,
            'image'       => $validated['image'] ?? null,
            'created_at' => $validated['date']
        ]);
    }

    private function addItems(array $items, Purchase $purchase, $date): void
    {
        foreach ($items as $item) {
            $this->purchaseItemRepo->create([
                'purchase_id'     => $purchase->id,
                'product_id'      => $item['product_id'],
                'quantity'        => $item['quantity'],
                'remaining_stock' => $item['quantity'],
                'price'           => $item['price'],
                'total'           => $item['quantity'] * $item['price'],
                'expire_date'     => $item['expire_date'],
                'created_at'      => $date,
                'updated_at'      => $date,

            ]);
        }
    }

    private function updateOldItems($oldItemsMap, $newItemsMap): void
    {
        foreach ($oldItemsMap as $productId => $oldItem) {
            if ($newItemsMap->has($productId)) {
                $newItem = $newItemsMap[$productId];
                $qtyDiff = $newItem['quantity'] - $oldItem->quantity;
                $newRemaining = $oldItem->remaining_stock + $qtyDiff;

                if ($newRemaining < 0) {
                    throw new \Exception("لا يمكن تقليل الكمية للمنتج '{$oldItem->product->name}'، تم بيع جزء منها بالفعل");
                }
                $this->purchaseItemRepo->update($oldItem, [
                    'quantity'        => $newItem['quantity'],
                    'remaining_stock' => $newRemaining,
                    'price'           => $newItem['price'],
                    'total'           => $newItem['quantity'] * $newItem['price'],
                    'expire_date'     => $newItem['expire_date'],
                ]);
            } else {
                if ($oldItem->remaining_stock < $oldItem->quantity) {
                    throw new \Exception("لا يمكن حذف المنتج \"{$oldItem->product->name}\" لأنه تم بيع جزء منه بالفعل");
                }
                $this->purchaseItemRepo->delete($oldItem);
            }
        }
    }

    private function addNewItemsToInvoice($oldItemsMap, $newItemsMap, Purchase $purchase): void
    {
        foreach ($newItemsMap as $productId => $newItem) {
            if (!$oldItemsMap->has($productId)) {
                $this->purchaseItemRepo->create([
                    'purchase_id'     => $purchase->id,
                    'product_id'      => $productId,
                    'quantity'        => $newItem['quantity'],
                    'remaining_stock' => $newItem['quantity'],
                    'price'           => $newItem['price'],
                    'total'           => $newItem['quantity'] * $newItem['price'],
                    'expire_date'     => $newItem['expire_date'],
                ]);
            }
        }
    }

    private function updatePurchase(Purchase $purchase, array $validated, float $total)
    {

        return $this->purchaseRepo->update($purchase, [
            'supplier_id' => $validated['supplier_id'],
            'date'        => $validated['date'],
            'notes'       => $validated['notes'] ?? $purchase->notes,
            'total'       => $total,
            'image'       => $validated['image'] ?? $purchase->image,
        ]);;
    }
}
