<?php

use App\Models\Purchase;
use App\Models\PurchaseItems;
use App\Repositories\PurchaseItemRepository;
use App\Repositories\PurchasesRepository;
use App\Services\ImageUploadService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Collection;

describe("test all cases of purchases", function () {

    it('create a purchase with no image ', function () {
        // Arrenage 
        $imageService = Mockery::mock(ImageUploadService::class);
        $purchaseRepo = Mockery::mock(PurchasesRepository::class);
        $purchaseItemsRepo = Mockery::mock(PurchaseItemRepository::class);
        $productService = Mockery::mock(ProductService::class);

        $fakePurchase = new Purchase(['id' => 1, 'total' => 30.0]);
        $purchaseRepo->shouldReceive('create')->once()
            ->withArgs(function ($data) {
                return $data['total'] === 30.0
                    && $data['supplier_id'] === 1;
            })->andReturn($fakePurchase);
        $imageService->shouldNotReceive('upload');
        $purchaseItemsRepo->shouldReceive('create')->twice();
        $productService->shouldReceive('clear')->once();

        $service = new PurchaseService(
            $purchaseItemsRepo,
            $purchaseRepo,
            $imageService,
            $productService
        );

        // act 
        $result = $service->createInvoice([
            "supplier_id" => 1,
            'date' => '2026-01-01',
            'items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => 10, 'expire_date' => null],
                ['product_id' => 2, 'quantity' => 1, 'price' => 10, 'expire_date' => null],
            ],
        ]);

        // Assert 
        $this->assertSame($result, $fakePurchase);
    });

    it('create purchase with image', function () {
        // Arrange
        $imageService = Mockery::mock(ImageUploadService::class);
        $purchaseRepo = Mockery::mock(PurchasesRepository::class);
        $purchaseItemsRepo = Mockery::mock(PurchaseItemRepository::class);
        $productService = Mockery::mock(ProductService::class);

        $fakeImageFile = Mockery::mock(\Illuminate\Http\UploadedFile::class);

        $fakePurchase = new Purchase(["id" => 1, "total" => 100.0, "supplier_id" => 1]);
        $purchaseRepo->shouldReceive("create")->once()

            ->andReturn($fakePurchase)
        ;
        $purchaseItemsRepo->shouldReceive("create")->once()
            ->withArgs(function ($data) {
                return $data['product_id'] === 1
                    && $data['price'] === 15
                    && $data['quantity'] === 5
                    && $data['expire_date'] === null;
            });

        $productService->shouldReceive("clear")->once();
        $imageService->shouldReceive("upload")->once()
            ->with($fakeImageFile, 'uploads/purchases')
            ->andReturn('uploads/categories/image.jpg');;

        $service = new PurchaseService(
            $purchaseItemsRepo,
            $purchaseRepo,
            $imageService,
            $productService
        );
        // Acc 
        $result = $service->createInvoice([
            "supplier_id" => 1,
            "date" => '2026-01-01',
            "items" => [
                ["product_id" => 1, "price" => 15, "quantity" => 5, "expire_date" => null]
            ]
        ], $fakeImageFile);
        // assert 
        $this->assertSame($fakePurchase, $result);
    });

    it("update a purchase items ", function () {
        // arrenge 
        $imageService = Mockery::mock(ImageUploadService::class);
        $purchaseRepo = Mockery::mock(PurchasesRepository::class);
        $purchaseItemsRepo = Mockery::mock(PurchaseItemRepository::class);
        $productService = Mockery::mock(ProductService::class);

        $purchase = new Purchase([
            "id" => 1,
            "supplier_id" => 1,
            "total" => 50.00,

        ]);

        $oldItem = new PurchaseItems([
            "id" => 1,
            "product_id" => 1,
            'quantity' => 5,
            'remaining_stock' => 5,
            'price' => 10,
            'expire_date' => null
        ]);

        $purchase->setRelation('items', new Collection([$oldItem]));

        $purchaseItemsRepo->shouldReceive('update')
            ->once()
            ->withArgs(function ($item, $data) use ($oldItem) {
                return $item === $oldItem;
            })->andReturn($oldItem);

        $imageService->shouldNotReceive('delete');
        $imageService->shouldNotReceive("upload");
        $productService->shouldReceive("clear")->once();

        $fakeUpdatedPurchase = new Purchase(
            [
                'id' => 1,
                'total' => 80.0,
            ]
        );
        $purchaseRepo->shouldReceive('update')
            ->once()
            ->andReturn($fakeUpdatedPurchase);

        $service = new PurchaseService(
            $purchaseItemsRepo,
            $purchaseRepo,
            $imageService,
            $productService
        );
        // act 
        $result = $service->updateInvoiceItems($purchase, [
            "supplier_id" => 2,
            "date" => '2026-01-01',
            "items" => [
                ["product_id" => 1, "quantity" => 8, "price" => 10, 'expire_date' => null],
            ]

        ]);

        // assert 
        $this->assertSame($fakeUpdatedPurchase, $result);
    });
    it("calculate Total InvoicePrice", function () {
        $service = app(PurchaseService::class);
        $total = $service->calculateTotalInvoicePrice([["quantity" => 1, "price" => 10], ["quantity" => 2, "price" => 2]]);
        expect($total)->toBe(14.0);
    });
});
