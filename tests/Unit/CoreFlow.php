<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Loan;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreFlow extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function createBaseData(): array
    {
        $category = Category::create(['name' => 'Electronics', 'description' => 'Devices']);
        $supplier = Supplier::create(['name' => 'Acme']);
        $department = Department::create(['name' => 'IT']);

        return compact('category', 'supplier', 'department');
    }

    protected function createProduct(Category $category, Supplier $supplier, string $brand = 'AcmeBrand'): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'brand' => $brand,
            'model' => 'A100',
            'spec' => 'Spec',
        ]);
    }

    protected function createPurchase(Supplier $supplier, Product $product): Purchase
    {
        $serialNumber = $this->nextCode('SN-PP');

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'voucher_ref' => $this->nextCode('V-UNIT'),
            'status' => 'pending',
            'total_cost' => 100,
            'total_qty' => 10,
        ]);

        PurchaseProduct::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'serial_number' => $serialNumber,
            'price' => 100,
        ]);

        return $purchase;
    }

    protected function createInventoryRecord(Product $product, Purchase $purchase, int $quantity = 10): Inventory
    {
        $serialNumber = $this->nextCode('SN-INV');

        return Inventory::create([
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'serial_number' => $serialNumber,
            'quantity' => $quantity,
            'status' => 'available',
            'unit_price' => 100,
            'code' => $serialNumber,
        ]);
    }

    protected function createLoanRecord(Inventory $inventory, Department $department, string $status = 'active'): Loan
    {
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
        $this->sequence++;

        return $prefix.'-'.$this->sequence;
    }

    public function test_supplier_can_be_created()
    {
        $supplier = Supplier::create(['name' => 'Acme']);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Acme']);
    }

    public function test_product_can_be_created_for_supplier()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'supplier_id' => $supplier->id]);
    }

    public function test_product_belongs_to_supplier()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertTrue($product->supplier->is($supplier));
    }

    public function test_purchase_can_be_created_for_supplier()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);

        $purchase = $this->createPurchase($supplier, $product);

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'supplier_id' => $supplier->id]);
    }

    public function test_purchase_product_links_purchase_and_product()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $purchaseProduct = PurchaseProduct::where('purchase_id', $purchase->id)->first();

        $this->assertTrue($purchaseProduct->purchase->is($purchase));
        $this->assertTrue($purchaseProduct->product->is($product));
    }

    public function test_inventory_can_be_created_for_product_and_purchase()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord($product, $purchase);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'status' => 'available',
        ]);
    }

    public function test_inventory_belongs_to_purchase()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);

        $this->assertTrue($inventory->purchase->is($purchase));
    }

    public function test_inventory_quantity_can_be_summed()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $this->createInventoryRecord($product, $purchase, 4);
        $this->createInventoryRecord($product, $purchase, 6);

        $this->assertEquals(10, Inventory::where('product_id', $product->id)->sum('quantity'));
    }

    public function test_loan_belongs_to_inventory()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);

        $loan = $this->createLoanRecord($inventory, $department);

        $this->assertTrue($loan->inventory->is($inventory));
    }

    public function test_active_loan_sets_inventory_status_to_loaned()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);

        $this->createLoanRecord($inventory, $department, 'active');

        $this->assertEquals('loaned', $inventory->fresh()->status);
    }

    public function test_returned_loan_sets_inventory_status_to_available()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);
        $loan = $this->createLoanRecord($inventory, $department, 'active');

        $loan->update(['status' => 'returned', 'return_date' => now()]);

        $this->assertEquals('available', $inventory->fresh()->status);
    }

    public function test_multiple_loans_can_be_created_for_different_inventory_items()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $firstInventory = $this->createInventoryRecord($product, $purchase);
        $secondInventory = $this->createInventoryRecord($product, $purchase);

        $this->createLoanRecord($firstInventory, $department);
        $this->createLoanRecord($secondInventory, $department);

        $this->assertEquals(2, Loan::count());
    }

    public function test_product_belongs_to_category()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);

        $this->assertTrue($product->category->is($category));
    }

    public function test_inventory_belongs_to_product()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);

        $this->assertTrue($inventory->product->is($product));
    }

    public function test_loan_belongs_to_department()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase);
        $loan = $this->createLoanRecord($inventory, $department);

        $this->assertTrue($loan->department->is($department));
    }
}
