<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnitQuantity;
use App\Models\UnitGroup;
use App\Models\User;
use App\Services\ProductService;
use App\Services\TaxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VertexLocalRegressionTest extends TestCase
{
    public function test_full_net_price_refund_returns_tax_and_renders_receipt(): void
    {
        $this->checkRefund( 'net_prices', 1, 1 );
    }

    public function test_partial_net_price_refund_prorates_the_saved_tax(): void
    {
        $this->checkRefund( 'net_prices', 2, 1 );
    }

    public function test_gross_price_refund_does_not_add_tax_twice(): void
    {
        $this->checkRefund( 'gross_prices', 1, 1 );
    }

    private function checkRefund( string $priceMode, int $quantity, int $refundQuantity ): void
    {
        $this->assertSame( 'vertex_qa', config( 'database.connections.mysql.database' ) );
        $admin = User::where( 'username', 'vertexadmin' )->firstOrFail();
        Auth::login( $admin );
        Sanctum::actingAs( $admin, ['*'] );
        foreach ( ['ns_pos_vat' => 'products_vat', 'ns_pos_prefered_price' => $priceMode, 'ns_currency_precision' => 0] as $key => $value ) {
            ns()->option->set( $key, $value );
        }
        $taxService = app( TaxService::class );
        $group = $taxService->createTaxGroup( ['name' => 'QA Test 10%', 'description' => 'Automated test fixture'] )['data']['group'];
        $tax = $taxService->createTax( ['name' => 'QA 10%', 'rate' => 10, 'tax_group_id' => $group->id, 'description' => 'Test only'] )['data']['tax'];
        $unitGroup = UnitGroup::whereHas( 'units' )->with( 'units' )->firstOrFail();
        $unit = $unitGroup->units->first();
        $barcode = 'SVQA' . Str::upper( Str::random( 10 ) );
        $productService = app( ProductService::class );
        $product = $productService->create( [
            'name' => 'Vertex QA Tax Refund', 'category_id' => ProductCategory::firstOrFail()->id,
            'type' => Product::TYPE_MATERIALIZED, 'product_type' => 'product', 'barcode' => $barcode,
            'barcode_type' => 'code128', 'sku' => $barcode, 'status' => 'available', 'stock_management' => 'enabled',
            'searchable' => true, 'tax_group_id' => $group->id, 'tax_type' => 'exclusive', 'expires' => 0,
            'on_expiration' => 'prevent_sales', 'images' => [], 'groups' => [],
            'units' => ['unit_group' => $unitGroup->id, 'selling_group' => [['unit_id' => $unit->id, 'sale_price_edit' => 10000, 'wholesale_price_edit' => 10000, 'barcode' => $barcode]]],
        ] )['data']['product'];
        $productService->setQuantity( $product->id, $unit->id, 10 );
        $unitQuantity = $product->unit_quantities->first();
        $barcodeResponse = $this->getJson( '/api/products/search/using-barcode/' . $barcode )->assertOk();
        $this->assertEquals( 10, $barcodeResponse->json( 'product.tax_group.taxes.0.rate' ) );
        $unitPrice = $priceMode === 'net_prices' ? 10000 : 11000;
        $sale = $this->postJson( '/api/orders', [
            'customer_id' => Customer::firstOrFail()->id, 'type' => ['identifier' => 'takeaway'],
            'discount_type' => 'flat', 'discount' => 0, 'discount_percentage' => 0, 'shipping' => 0,
            'subtotal' => 11000 * $quantity, 'total' => 11000 * $quantity,
            'tax_type' => 'exclusive', 'tax_group_id' => $group->id,
            'taxes' => [['tax_id' => $tax->id, 'name' => $tax->name, 'rate' => 10]],
            'products' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice,
                'unit_id' => $unit->id, 'unit_quantity_id' => $unitQuantity->id, 'tax_group_id' => $group->id,
                'tax_type' => 'exclusive', 'mode' => 'normal', 'discount_type' => 'percentage', 'discount_percentage' => 0]],
            'payments' => [['identifier' => 'cash-payment', 'value' => 11000 * $quantity]],
        ] )->assertOk();
        $order = Order::with( 'products' )->findOrFail( $sale->json( 'data.order.id' ) );
        $this->assertEquals( 11000 * $quantity, $order->total );
        $this->assertEquals( 10 - $quantity, ProductUnitQuantity::find( $unitQuantity->id )->quantity );
        $refund = $this->postJson( '/api/orders/' . $order->id . '/refund', [
            'payment' => ['identifier' => 'cash-payment'], 'refund_shipping' => false, 'total' => 11000 * $refundQuantity,
            'products' => [['id' => $order->products->first()->id, 'condition' => 'unspoiled', 'description' => 'QA regression',
                'quantity' => $refundQuantity, 'unit_price' => $unitPrice]],
        ] )->assertOk();
        $this->assertEquals( 11000 * $refundQuantity, $refund->json( 'data.orderRefund.total' ) );
        $this->assertEquals( 10 - $quantity + $refundQuantity, ProductUnitQuantity::find( $unitQuantity->id )->quantity );
        if ( $priceMode === 'net_prices' ) {
            $this->assertEquals( 1000 * $refundQuantity, $refund->json( 'data.orderRefund.tax_value' ) );
        }
        $this->get( '/dashboard/orders/refund-receipt/' . $refund->json( 'data.orderRefund.id' ) )->assertOk()->assertSee( 'Saint Vertex' );
    }
}
