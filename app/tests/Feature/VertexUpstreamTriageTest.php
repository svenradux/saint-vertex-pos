<?php

namespace Tests\Feature;

use App\Exceptions\NotAllowedException;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnitQuantity;
use App\Models\UnitGroup;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\OrdersService;
use App\Services\ProductService;
use App\Services\TaxService;
use App\Services\TestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithOrderTest;
use Tests\Traits\WithProductTest;

class VertexUpstreamTriageTest extends TestCase
{
    use WithOrderTest, WithProductTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame( 'vertex_qa', DB::connection()->getDatabaseName() );
        DB::beginTransaction();
        $admin = User::where( 'username', 'vertexadmin' )->firstOrFail();
        Auth::login( $admin );
        Sanctum::actingAs( $admin, ['*'] );
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_upstream_precision_and_idr_rounding_are_distinct(): void
    {
        $tax = app( TaxService::class );
        $cases = [
            ['net-8', $tax->getPriceWithoutTax( 'inclusive', 12, 8 ), 7.14286],
            ['net-9', $tax->getPriceWithoutTax( 'inclusive', 12, 9 ), 8.03571],
            ['tax-64.8', $tax->getVatValue( 'inclusive', 12, 64.8 ), 6.94286],
        ];
        foreach ( $cases as [$label,$computed,$expectedStored] ) {
            $stored = (float) DB::selectOne( 'SELECT CAST(? AS DECIMAL(18,5)) AS value', [$computed] )->value;
            $this->assertEquals( $expectedStored, $stored );
            $this->assertNotEquals( $computed, $stored );
            $this->assertLessThanOrEqual( 0.000005, abs( $computed - $stored ) );
            $this->assertSame( ( new CurrencyService( $computed, ['decimal_precision' => 0] ) )->getRaw(), ( new CurrencyService( $stored, ['decimal_precision' => 0] ) )->getRaw() );
            fwrite( STDOUT, json_encode( ['case' => $label, 'computed' => $computed, 'stored' => $stored, 'difference' => abs( $computed - $stored )] ) . PHP_EOL );
        }
        $this->assertSame( 7.0, ( new CurrencyService( '7.49999', ['decimal_precision' => 0] ) )->getRaw() );
        $this->assertSame( 8.0, ( new CurrencyService( '7.5', ['decimal_precision' => 0] ) )->getRaw() );
        $this->assertSame( 8.0, ( new CurrencyService( '7.50001', ['decimal_precision' => 0] ) )->getRaw() );
        $schema = DB::select( "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nexopos_products_unit_quantities' AND COLUMN_NAME IN ('sale_price_net','quantity')" );
        fwrite( STDOUT, 'SCHEMA ' . json_encode( $schema ) . PHP_EOL );
    }

    public function test_empty_upstream_tax_fixture_is_rejected_for_valid_reason(): void
    {
        $eligible = Product::notGrouped()->whereRelation( 'unit_quantities', 'quantity', '>', 1000 )->count();
        $details = ( new TestService )->prepareOrder( null, ['discount_type' => 'flat', 'discount' => 2], [], ['products' => fn() => collect()] );
        $this->assertSame( [], $details['products'] );
        $this->assertEquals( 0, $details['subtotal'] );
        $details['products'] = collect( $details['products'] );
        fwrite( STDOUT, json_encode( ['currentEligibleOver1000' => $eligible, 'controlledEmptyFixtureSubtotal' => $details['subtotal'], 'discount' => $details['discount']] ) . PHP_EOL );
        $this->expectException( NotAllowedException::class );
        app( OrdersService::class )->__checkDiscountValidity( $details );
    }

    public function test_fractional_stock_adjustment_does_not_use_currency_rounding(): void
    {
        ns()->option->set( 'ns_currency_precision', 0 );
        $quantity = ProductUnitQuantity::whereRelation( 'product', 'stock_management', 'enabled' )->whereRelation( 'product', 'type', '<>', Product::TYPE_GROUPED )->firstOrFail();
        $service = app( ProductService::class );
        $service->setQuantity( $quantity->product_id, $quantity->unit_id, 10 );
        foreach ( [['added', 0.125, 10.125], ['removed', 0.125, 10], ['added', 0.1, 10.1], ['removed', 0.1, 10]] as [$action,$delta,$expected] ) {
            $service->stockAdjustment( $action, ['product_id' => $quantity->product_id, 'unit_id' => $quantity->unit_id, 'quantity' => $delta, 'unit_price' => 0, 'author' => auth()->id()] );
            $this->assertEqualsWithDelta( $expected, $quantity->fresh()->quantity, 0.00001 );
        }
    }

    public function test_grouped_stock_and_checkout_with_explicit_fixture(): void
    {
        foreach ( ['ns_pos_vat' => 'disabled', 'ns_pos_prefered_price' => 'gross_prices'] as $key => $value ) {
            ns()->option->set( $key, $value );
        }
        $unitGroup = UnitGroup::whereHas( 'units' )->with( 'units' )->firstOrFail();
        $unit = $unitGroup->units->first();
        $barcode = 'VTX002' . Str::upper( Str::random( 8 ) );
        $service = app( ProductService::class );
        $base = ['name' => 'VTX002 fixture', 'category_id' => ProductCategory::firstOrFail()->id, 'type' => Product::TYPE_MATERIALIZED, 'product_type' => 'product', 'barcode_type' => 'code128', 'sku' => $barcode, 'barcode' => $barcode, 'status' => 'available', 'stock_management' => 'enabled', 'searchable' => true, 'tax_group_id' => null, 'tax_type' => 'exclusive', 'expires' => 0, 'on_expiration' => 'prevent_sales', 'images' => [], 'groups' => [], 'units' => ['unit_group' => $unitGroup->id, 'selling_group' => [['unit_id' => $unit->id, 'sale_price_edit' => 10000, 'wholesale_price_edit' => 10000]]]];
        $component = $service->create( $base )['data']['product'];
        $service->setQuantity( $component->id, $unit->id, 100 );
        $componentUnit = $component->unit_quantities->first();
        $base['type'] = Product::TYPE_GROUPED;
        $base['units']['selling_group'][0]['sale_price_edit'] = 12;
        $base['units']['selling_group'][0]['wholesale_price_edit'] = 12;
        $base['sku'] .= '-G';
        $base['barcode'] .= 'G';
        $base['groups'] = ['product_subitems' => [['unit_quantity_id' => $componentUnit->id, 'product_id' => $component->id, 'unit_id' => $unit->id, 'quantity' => 1, 'sale_price' => 10000]]];
        $grouped = $service->create( $base )['data']['product'];
        $this->assertEquals( 1, $grouped->sub_items()->count() );
        $service->setQuantity( $grouped->id, $unit->id, 100 );
        // Exercise the original upstream stock assertion with its missing fixture supplied.
        $rejection = null;
        try {
            $service->stockAdjustment( 'added', ['product_id' => $grouped->id, 'unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 0] );
        } catch ( \Exception $exception ) {
            $rejection = $exception;
        }
        $this->assertNotNull( $rejection );
        $this->assertEquals( 100, $componentUnit->fresh()->quantity );
        $this->attemptHoldOrderAndCheckoutWithGroupedProducts();
        $this->attemptCreateOrderWithGroupedProducts();
    }
}
