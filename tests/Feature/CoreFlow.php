<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Inventory;
use App\Models\Loan;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreFlow extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function createAdminUser(): User
    {
        \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        return $user;
    }

    protected function createBaseData(): array
    {
        $category = Category::create(['name' => 'Electronics', 'description' => 'Devices']);
        $supplier = Supplier::create(['name' => 'Acme']);
        $department = Department::create(['name' => 'IT']);

        return compact('category', 'supplier', 'department');
    }

    protected function createProduct(Category $category, Supplier $supplier): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'brand' => 'AcmeBrand',
            'model' => 'A100',
            'spec' => 'Spec',
        ]);
    }

    protected function createPurchase(Supplier $supplier, Product $product): Purchase
    {
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'voucher_ref' => 'V-CORE-1',
            'status' => 'pending',
            'total_cost' => 100,
            'total_qty' => 10,
        ]);

        PurchaseProduct::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'serial_number' => 'SN-CORE-1',
            'price' => 100,
        ]);

        return $purchase;
    }

    protected function createInventoryRecord(Product $product, Purchase $purchase, string $serialNumber, int $quantity = 10): Inventory
    {
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

    public function test_admin_can_access_home()
    {
        $user = $this->createAdminUser();

        $this->actingAs($user)->get('/')->assertStatus(200);
    }

    public function test_admin_has_admin_role()
    {
        $user = $this->createAdminUser();

        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_category_can_be_created()
    {
        $category = Category::create(['name' => 'Electronics', 'description' => 'Devices']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Electronics']);
    }

    public function test_supplier_can_be_created()
    {
        $supplier = Supplier::create(['name' => 'Acme']);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Acme']);
    }

    public function test_product_can_be_created()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'brand' => 'AcmeBrand']);
    }

    public function test_product_belongs_to_category()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals('Electronics', $product->category->name);
    }

    public function test_product_belongs_to_supplier()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $product = $this->createProduct($category, $supplier);

        $this->assertEquals($supplier->id, $product->supplier_id);
        $this->assertEquals('Acme', $product->supplier->name);
    }

    public function test_category_can_have_products()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $this->createProduct($category, $supplier);
        $this->createProduct($category, $supplier);

        $this->assertCount(2, $category->products);
    }

    public function test_multiple_products_can_share_category()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();

        $first = $this->createProduct($category, $supplier);
        $second = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'brand' => 'AcmeBrand2',
            'model' => 'A200',
            'spec' => 'Spec 2',
        ]);

        $this->assertEquals($category->id, $first->category_id);
        $this->assertEquals($category->id, $second->category_id);
    }

    public function test_inventory_can_be_created_from_purchase()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-CORE-1');

        $this->assertDatabaseHas('inventories', ['serial_number' => 'SN-CORE-1', 'quantity' => 10]);
        $this->assertEquals('available', $inventory->status);
    }

    public function test_purchase_can_be_created()
    {
        ['supplier' => $supplier] = $this->createBaseData();
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Office', 'description' => 'Office tools'])->id,
            'supplier_id' => $supplier->id,
            'brand' => 'DeskBrand',
            'model' => 'Desk100',
            'spec' => 'Wood',
        ]);

        $purchase = $this->createPurchase($supplier, $product);

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'pending']);
    }

    public function test_purchase_product_can_be_created()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'voucher_ref' => 'V-CORE-PP',
            'status' => 'pending',
            'total_cost' => 100,
            'total_qty' => 10,
        ]);

        $purchaseProduct = PurchaseProduct::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'serial_number' => 'SN-PP-1',
            'price' => 100,
        ]);

        $this->assertDatabaseHas('purchase_products', ['id' => $purchaseProduct->id, 'serial_number' => 'SN-PP-1']);
    }

    public function test_loan_marks_inventory_as_loaned()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-CORE-1');

        $this->createLoanRecord($inventory, $department, 'active');

        $this->assertEquals('loaned', Inventory::find($inventory->id)->status);
    }

    public function test_inventory_belongs_to_purchase()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-CORE-2');

        $this->assertEquals($purchase->id, $inventory->purchase_id);
    }

    public function test_inventory_quantity_can_be_summed()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $this->createInventoryRecord($product, $purchase, 'SN-SUM-1', 4);
        $this->createInventoryRecord($product, $purchase, 'SN-SUM-2', 6);

        $this->assertEquals(10, Inventory::where('product_id', $product->id)->sum('quantity'));
    }

    public function test_loan_belongs_to_inventory()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-LOAN-1');

        $loan = $this->createLoanRecord($inventory, $department);

        $this->assertEquals($inventory->id, $loan->inventory_id);
    }

    public function test_loan_belongs_to_department()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-DEPT-1');

        $loan = $this->createLoanRecord($inventory, $department);

        $this->assertEquals($department->id, $loan->department_id);
    }

    public function test_loan_activation_sets_inventory_loaned()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-ACTIVE-1');

        $this->createLoanRecord($inventory, $department, 'active');

        $this->assertEquals('loaned', $inventory->fresh()->status);
    }

    public function test_returned_loan_marks_inventory_available()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $inventory = $this->createInventoryRecord($product, $purchase, 'SN-RETURN-1');

        $loan = $this->createLoanRecord($inventory, $department, 'active');

        $loan->update(['status' => 'returned', 'return_date' => now()]);

        $this->assertEquals('available', Inventory::find($inventory->id)->status);
    }

    public function test_multiple_inventory_items_can_exist_for_same_product()
    {
        ['category' => $category, 'supplier' => $supplier] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);

        $this->createInventoryRecord($product, $purchase, 'SN-MULTI-1');
        $this->createInventoryRecord($product, $purchase, 'SN-MULTI-2');

        $this->assertCount(2, Inventory::where('product_id', $product->id)->get());
    }

    public function test_multiple_loans_can_be_created_for_different_inventory_items()
    {
        ['category' => $category, 'supplier' => $supplier, 'department' => $department] = $this->createBaseData();
        $product = $this->createProduct($category, $supplier);
        $purchase = $this->createPurchase($supplier, $product);
        $firstInventory = $this->createInventoryRecord($product, $purchase, 'SN-L1');
        $secondInventory = $this->createInventoryRecord($product, $purchase, 'SN-L2');

        $this->createLoanRecord($firstInventory, $department, 'active');
        $this->createLoanRecord($secondInventory, $department, 'active');

        $this->assertEquals(2, Loan::count());
    }
}
