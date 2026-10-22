<?php

namespace Tests\Feature;

use App\Enums\NotificationsEnum;
use App\Jobs\CheckTaskSchedulingConfigurationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\CoreService;
use App\Services\ModulesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class VertexOperationalHealthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame( 'vertex_qa', config( 'database.connections.mysql.database' ) );
        Notification::whereIn( 'identifier', [NotificationsEnum::NSWORKERDISABLED, NotificationsEnum::NSCRONDISABLED, 'symlink-saintvertex', 'symlink-healthfixture', 'public-modules-permissions'] )->delete();
    }

    private function notification( string $identifier ): void
    {
        DB::table( ( new Notification )->getTable() )->insert( [
            'identifier' => $identifier,
            'title' => 'Health fixture',
            'description' => 'Health fixture',
            'source' => 'system',
            'user_id' => User::where( 'username', 'vertexadmin' )->firstOrFail()->id,
            'actions' => '[]',
        ] );
    }

    private function windowsOnly(): void
    {
        if ( PHP_OS_FAMILY !== 'Windows' ) {
            $this->markTestSkipped( 'NTFS compatibility check requires Windows.' );
        }
    }

    public function test_sync_queue_uses_verified_scheduler_heartbeat_and_preserves_other_notifications(): void
    {
        config( ['queue.default' => 'sync'] );
        $this->notification( NotificationsEnum::NSWORKERDISABLED );
        $this->notification( 'vertex-health-preserve' );
        ns()->option->set( 'ns_cron_last_activity', ns()->date->toDateTimeString() );
        ns()->checkTaskSchedulingConfiguration();
        $this->assertFalse( Notification::where( 'identifier', NotificationsEnum::NSWORKERDISABLED )->exists() );
        $this->assertFalse( Notification::where( 'identifier', NotificationsEnum::NSCRONDISABLED )->exists() );
        $this->assertTrue( Notification::where( 'identifier', 'vertex-health-preserve' )->exists() );
    }

    public function test_sync_queue_still_reports_a_stale_scheduler_and_does_not_clear_unverified_warning(): void
    {
        config( ['queue.default' => 'sync'] );
        $this->notification( NotificationsEnum::NSWORKERDISABLED );
        ns()->option->set( 'ns_cron_last_activity', now()->subHours( 2 )->toDateTimeString() );
        ns()->checkTaskSchedulingConfiguration();
        $this->assertTrue( Notification::where( 'identifier', NotificationsEnum::NSWORKERDISABLED )->exists() );
        $this->assertTrue( Notification::where( 'identifier', NotificationsEnum::NSCRONDISABLED )->exists() );
    }

    public function test_async_queue_retains_original_worker_health_check(): void
    {
        config( ['queue.default' => 'database'] );
        Bus::fake( [CheckTaskSchedulingConfigurationJob::class] );
        ns()->option->set( 'ns_jobs_last_activity', now()->subHours( 2 )->toDateTimeString() );
        ns()->checkTaskSchedulingConfiguration();
        $this->assertTrue( Notification::where( 'identifier', NotificationsEnum::NSWORKERDISABLED )->exists() );
        Bus::assertDispatched( CheckTaskSchedulingConfigurationJob::class );
    }

    public function test_missing_scheduler_heartbeat_is_reported_without_fabricating_activity(): void
    {
        config( ['queue.default' => 'sync'] );
        ns()->option->delete( 'ns_cron_last_activity' );
        ns()->checkTaskSchedulingConfiguration();
        $this->assertTrue( Notification::where( 'identifier', NotificationsEnum::NSCRONDISABLED )->exists() );
        $this->assertFalse( ns()->option->get( 'ns_cron_last_activity', false ) );
    }

    public function test_actual_ntfs_junction_resolves_to_module_assets_and_clears_only_its_warning(): void
    {
        $this->windowsOnly();
        $this->notification( 'symlink-saintvertex' );
        $this->notification( 'vertex-health-preserve' );
        $this->assertSame( realpath( base_path( 'modules/SaintVertex/Public' ) ), realpath( public_path( 'modules/saintvertex' ) ) );
        $this->assertSame( file_get_contents( base_path( 'modules/SaintVertex/Public/brand.css' ) ), file_get_contents( public_path( 'modules/saintvertex/brand.css' ) ) );
        ns()->checkModulesSymbolicLinks();
        $this->assertFalse( Notification::where( 'identifier', 'symlink-saintvertex' )->exists() );
        $this->assertTrue( Notification::where( 'identifier', 'vertex-health-preserve' )->exists() );
    }

    public function test_missing_copied_or_misdirected_module_asset_paths_still_raise_warnings(): void
    {
        $this->windowsOnly();
        $originalPublicPath = public_path();
        $fixtureRoot = storage_path( 'framework/testing/vertex-health-' . bin2hex( random_bytes( 6 ) ) );
        mkdir( $fixtureRoot );
        mkdir( $fixtureRoot . '/modules' );
        $linkPath = $fixtureRoot . '/modules/healthfixture';
        app()->usePublicPath( $fixtureRoot );
        $modules = Mockery::mock( ModulesService::class );
        $modules->shouldReceive( 'get' )->andReturn( [[
            'namespace' => 'HealthFixture', 'name' => 'Health Fixture',
            'path' => base_path( 'modules/SaintVertex/' ),
        ]] );
        $this->instance( ModulesService::class, $modules );

        try {
            ns()->checkModulesSymbolicLinks();
            $this->assertTrue( Notification::where( 'identifier', 'symlink-healthfixture' )->exists(), 'Missing link must fail' );
            Notification::where( 'identifier', 'symlink-healthfixture' )->delete();
            mkdir( $linkPath );
            ns()->checkModulesSymbolicLinks();
            $this->assertTrue( Notification::where( 'identifier', 'symlink-healthfixture' )->exists(), 'Ordinary directory must fail' );
            rmdir( $linkPath );
            Notification::where( 'identifier', 'symlink-healthfixture' )->delete();
            File::link( storage_path( 'framework/testing' ), $linkPath );
            ns()->checkModulesSymbolicLinks();
            $this->assertTrue( Notification::where( 'identifier', 'symlink-healthfixture' )->exists(), 'Wrong junction target must fail' );
        } finally {
            app()->usePublicPath( $originalPublicPath );
            if ( is_dir( $linkPath ) ) {
                rmdir( $linkPath );
            }
            rmdir( $fixtureRoot . '/modules' );
            rmdir( $fixtureRoot );
        }
    }

    public function test_ntfs_probe_verifies_access_and_leaves_no_probe_files(): void
    {
        $this->windowsOnly();
        $this->notification( 'public-modules-permissions' );
        $before = glob( public_path( 'modules/.vertex-health-*' ) );
        ns()->checkPublicModulesDirectoryPermissions();
        $this->assertFalse( Notification::where( 'identifier', 'public-modules-permissions' )->exists() );
        $this->assertSame( $before, glob( public_path( 'modules/.vertex-health-*' ) ) );
    }

    public function test_ntfs_access_failure_remains_actionable_and_clears_only_after_recovery(): void
    {
        $this->windowsOnly();
        $core = Mockery::mock( CoreService::class )->makePartial()->shouldAllowMockingProtectedMethods();
        $core->shouldReceive( 'canAccessPublicModulesDirectory' )->once()->andReturn( false );
        $core->checkPublicModulesDirectoryPermissions();
        $warning = Notification::where( 'identifier', 'public-modules-permissions' )->firstOrFail();
        $this->assertStringContainsString( 'NTFS', $warning->description );
        $this->assertSame( [], $warning->actions );
        ns()->checkPublicModulesDirectoryPermissions();
        $this->assertFalse( Notification::where( 'identifier', 'public-modules-permissions' )->exists() );
    }
}
