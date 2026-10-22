<?php

/** Copyright © 2026 Saint Vertex. Original branding regression checks. */

namespace Modules\SaintVertex\Tests\Feature;

use App\Classes\Hook;
use App\Forms\UserProfileForm;
use App\Jobs\CheckForUpdatesJob;
use App\Mail\ActivateYourAccountMail;
use App\Mail\PasswordRecoveredMail;
use App\Mail\ResetPasswordMail;
use App\Mail\UserRegisteredMail;
use App\Mail\WelcomeMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\AdService;
use App\Services\GuideService;
use App\Services\MarketplaceService;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Http;
use Modules\SaintVertex\Services\BrandingText;
use Tests\TestCase;

class VertexBrandingCompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame( 'vertex_qa', config( 'database.connections.mysql.database' ) );
    }

    public function test_branding_changes_display_copy_without_rewriting_keys_urls_or_placeholders(): void
    {
        $this->assertSame( 'Vertex POS %s', BrandingText::product( 'My NexoPOS %s' ) );
        $this->assertSame( '<a href="https://github.com/Blair2004/NexoPOS">Vertex POS</a>', BrandingText::product( '<a href="https://github.com/Blair2004/NexoPOS">NexoPOS</a>' ) );
        foreach ( array_keys( config( 'nexopos.languages' ) ) as $locale ) {
            $dictionary = BrandingText::translations( $locale );
            $original = json_decode( file_get_contents( lang_path( $locale . '.json' ) ), true );
            $this->assertSame( array_keys( $original ), array_keys( $dictionary ) );
            $this->assertSame( $dictionary, json_decode( file_get_contents( base_path( 'modules/SaintVertex/Public/lang/' . $locale . '.json' ) ), true ) );
            $this->assertStringNotContainsString( 'NexoPOS', strip_tags( implode( '\n', $dictionary ) ) );
        }
    }

    public function test_backend_guides_messages_and_footer_are_branded(): void
    {
        $this->assertStringNotContainsString( 'NexoPOS', __( 'Take a quick tour of the procurement module and discover the main areas of NexoPOS.' ) );
        $this->assertSame( '© 2026 Saint Vertex', Hook::filter( 'ns-footer-signature', 'original' ) );
        $this->assertStringContainsString( '© 2026 Saint Vertex', view( 'SaintVertex::footer' )->render() );
        $this->assertStringNotContainsString( 'NexoPOS', json_encode( app( GuideService::class )->all() ) );
    }

    public function test_about_identifies_product_and_original_contributions_without_replacing_core_details(): void
    {
        $this->actingAs( User::where( 'username', 'vertexadmin' )->firstOrFail() );
        $this->get( '/dashboard/settings/about' )
            ->assertOk()
            ->assertSee( 'Copyright © 2026 Saint Vertex' )
            ->assertSee( 'Hak cipta kontribusi asli Saint Vertex' )
            ->assertSee( 'Vertex POS' )
            ->assertSee( '6.2.4' );
    }

    public function test_offline_checks_make_no_requests_or_new_notifications(): void
    {
        Http::fake();
        $before = Notification::count();
        $this->assertFalse( app( MarketplaceService::class )->testConnection() );
        $this->assertSame( [], app( AdService::class )->getAds() );
        ( new CheckForUpdatesJob )->handle();
        Http::assertNothingSent();
        $this->assertSame( $before, Notification::count() );
    }

    public function test_cloud_routes_are_blocked_but_local_language_is_available(): void
    {
        Http::fake();
        $this->getJson( '/api/marketplace/modules' )->assertUnauthorized();
        $guest = $this->get( '/dashboard/oauth/mynexopos/authorize' )->assertRedirect();
        $this->assertSame( '/sign-in', parse_url( $guest->headers->get( 'Location' ), PHP_URL_PATH ) );
        $this->actingAs( User::where( 'username', 'vertexadmin' )->firstOrFail() );
        $this->getJson( '/api/marketplace/modules' )->assertStatus( 409 )->assertJsonPath( 'status', 'error' );
        $this->get( '/dashboard/modules/marketplace' )->assertRedirect( '/dashboard/modules' );
        Http::assertNothingSent();
    }

    public function test_theme_values_and_personal_store_default_option_are_correct(): void
    {
        $this->actingAs( User::where( 'username', 'vertexadmin' )->firstOrFail() );
        $tabs = ( new UserProfileForm )->getForm()['tabs'];
        $theme = collect( $tabs['attribute']['fields'] )->firstWhere( 'name', 'theme' );
        $this->assertSame( ['', 'dark', 'light', 'phosphor'], array_column( $theme['options'], 'value' ) );
        $this->assertSame( ['dark' => 'Dark', 'light' => 'Light', 'phosphor' => 'Phosphor'], config( 'nexopos.themes' ) );
    }

    public function test_all_native_email_templates_render_without_upstream_product_branding(): void
    {
        $user = User::where( 'username', 'vertexadmin' )->firstOrFail();
        // Unsaved token supplies template URL parameters only; no account or mail is created.
        $user->activation_token = 'vertex-template-render-only';
        foreach ( [ActivateYourAccountMail::class, PasswordRecoveredMail::class, ResetPasswordMail::class, UserRegisteredMail::class, WelcomeMail::class] as $mailClass ) {
            $mail = $mailClass === UserRegisteredMail::class ? new $mailClass( $user, $user ) : new $mailClass( $user );
            $html = $mail->render();
            $this->assertStringNotContainsString( 'NexoPOS', strip_tags( $html ) );
            $this->assertStringNotContainsString( 'NexoPOS', $mail->subject ?? '' );
            $this->assertStringContainsString( 'Saint Vertex', $html );
            $this->assertStringContainsString( '© 2026 Saint Vertex', html_entity_decode( $html ) );
            $text = app( Markdown::class )->renderText( $mail->markdown, $mail->buildViewData() )->toHtml();
            $this->assertStringContainsString( '© 2026 Saint Vertex', $text );
            $this->assertStringNotContainsString( 'NexoPOS', $text );
        }
    }
}
