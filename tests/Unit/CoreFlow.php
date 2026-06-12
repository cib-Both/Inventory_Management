<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Loan;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreFlow extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private const INVENTORY_AVAILABLE = 'available';
    private const INVENTORY_LOANED = 'loaned';

    private const LOAN_ACTIVE = 'active';
    private const LOAN_RETURNED = 'returned';

    protected function createBaseData(): array
    {
        return [
            'category' => Category::create([
                'name' => 'Electronics',
                'description' => 'Devices',
            ]),

            'supplier' => Supplier::create([
                'name' => 'Acme',
            ]),

            'department' => Department::create([
                'name' => 'IT',
            ]),
        ];
    }

    protected function createProduct(
        Category $category,
        Supplier $supplier,
        string $brand = 'AcmeBrand'
    ): Product {
        return Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'brand' => $brand,
            'model' => 'A100',
            'spec' => 'Spec',
        ]);
    }

    protected function createPurchase(
        Supplier $supplier,
        Product $product,
        int $quantity = 10,
        float $price = 100
    ): Purchase {
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'voucher_ref' => $this->nextCode('V-UNIT'),
            'status' => 'pending',
            'total_qty' => $quantity,
            'total_cost' => $quantity * $price,
        ]);

        PurchaseProduct::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'serial_number' => $this->nextCode('SN-PP'),
            'price' => $price,
        ]);

        return $purchase;
    }

    protected function createInventoryRecord(
        Product $product,
        Purchase $purchase,
        int $quantity = 10
    ): Inventory {
        $serialNumber = $this->nextCode('SN-INV');

        return Inventory::create([
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'serial_number' => $serialNumber,
            'quantity' => $quantity,
            'status' => self::INVENTORY_AVAILABLE,
            'unit_price' => 100,
            'code' => $serialNumber,
        ]);
    }

    protected function createLoanRecord(
        Inventory $inventory,
        Department $department,
        string $status = self::LOAN_ACTIVE
    ): Loan {
        return Loan::create([
            'name' => 'Bob',
            'inventory_id' => $inventory->id,
            'department_id' => $department->id,
            'position' => 'Staff',
            'loan_date' => now(),
            'status' => $status,
        ]);
    }

    protected function nextCode(string $prefix): string
    {
        return sprintf(
            '%s-%04d',
            $prefix,
            ++$this->sequence
        );
    }

    public function test_supplier_can_be_created(): void
    {
        $supplier = Supplier::create([
            'name' => 'Acme',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Acme',
        ]);
    }

    public function test_product_can_be_created_for_supplier(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_product_belongs_to_supplier(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertTrue(
            $product->supplier->is($supplier)
        );
    }

    public function test_purchase_can_be_created_for_supplier(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_purchase_product_links_purchase_and_product(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $purchaseProduct = PurchaseProduct::where(
            'purchase_id',
            $purchase->id
        )->first();

        $this->assertNotNull($purchaseProduct);

        $this->assertTrue(
            $purchaseProduct->purchase->is($purchase)
        );

        $this->assertTrue(
            $purchaseProduct->product->is($product)
        );
    }

    public function test_inventory_can_be_created_for_product_and_purchase(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'status' => self::INVENTORY_AVAILABLE,
        ]);
    }

    public function test_inventory_belongs_to_purchase(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $this->assertTrue(
            $inventory->purchase->is($purchase)
        );
    }

    public function test_inventory_quantity_can_be_summed(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $this->createInventoryRecord($product, $purchase, 4);
        $this->createInventoryRecord($product, $purchase, 6);

        $this->assertEquals(
            10,
            Inventory::where('product_id', $product->id)->sum('quantity')
        );
    }

    public function test_loan_belongs_to_inventory(): void
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $loan = $this->createLoanRecord(
            $inventory,
            $department
        );

        $this->assertTrue(
            $loan->inventory->is($inventory)
        );
    }

    public function test_active_loan_sets_inventory_status_to_loaned(): void
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $this->createLoanRecord(
            $inventory,
            $department,
            self::LOAN_ACTIVE
        );

        $this->assertEquals(
            self::INVENTORY_LOANED,
            $inventory->fresh()->status
        );
    }

    public function test_returned_loan_sets_inventory_status_to_available(): void
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $loan = $this->createLoanRecord(
            $inventory,
            $department,
            self::LOAN_ACTIVE
        );

        $loan->update([
            'status' => self::LOAN_RETURNED,
            'return_date' => now(),
        ]);

        $this->assertEquals(
            self::INVENTORY_AVAILABLE,
            $inventory->fresh()->status
        );
    }

    public function test_multiple_loans_can_be_created_for_different_inventory_items(): void
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $firstInventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $secondInventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $this->createLoanRecord(
            $firstInventory,
            $department
        );

        $this->createLoanRecord(
            $secondInventory,
            $department
        );

        $this->assertEquals(
            2,
            Loan::count()
        );
    }

    public function test_product_belongs_to_category(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertTrue(
            $product->category->is($category)
        );
    }

    public function test_inventory_belongs_to_product(): void
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $this->assertTrue(
            $inventory->product->is($product)
        );
    }

    public function test_loan_belongs_to_department(): void
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord(
            $product,
            $purchase
        );

        $loan = $this->createLoanRecord(
            $inventory,
            $department
        );

        $this->assertTrue(
            $loan->department->is($department)
        );
    }
}