<?php

namespace Tests\Feature;

use App\Mail\HostingProvisioned;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Nameservers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Validates: Requirements 8.5
 */
class NameserversTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_when_settings_missing_or_empty(): void
    {
        $this->assertSame(['ns0.thundercloud.uk', 'ns1.thundercloud.uk'], Nameservers::get());

        Setting::set('whm_nameserver_0', '');
        Setting::set('whm_nameserver_1', '   ');
        $this->assertSame(['ns0.thundercloud.uk', 'ns1.thundercloud.uk'], Nameservers::get());
    }

    public function test_settings_override_defaults(): void
    {
        Setting::set('whm_nameserver_0', 'a.example.net');
        Setting::set('whm_nameserver_1', 'b.example.net');

        $this->assertSame(['a.example.net', 'b.example.net'], Nameservers::get());
    }

    public function test_admin_form_and_provisioned_email_show_same_values(): void
    {
        Setting::set('whm_nameserver_0', 'a.example.net');
        Setting::set('whm_nameserver_1', 'b.example.net');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->is_admin = true;
        $admin->save();

        $this->actingAs($admin)
            ->get(route('admin.settings.whm'))
            ->assertOk()
            ->assertSee('value="a.example.net"', false)
            ->assertSee('value="b.example.net"', false);

        Mail::fake();
        $customer = Customer::factory()->create();
        User::factory()->create(['company_id' => $customer->company_id]);
        $service = Service::factory()->webHosting()->create(['company_id' => $customer->company_id]);

        app(NotificationService::class)->notifyCustomerHostingProvisioned($service);

        Mail::assertQueued(HostingProvisioned::class, fn ($m) => $m->nameservers === Nameservers::get()
            && $m->nameservers === ['a.example.net', 'b.example.net']);
    }

    public function test_admin_form_shows_defaults_when_unset(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->is_admin = true;
        $admin->save();

        $this->actingAs($admin)
            ->get(route('admin.settings.whm'))
            ->assertSee('value="ns0.thundercloud.uk"', false)
            ->assertSee('value="ns1.thundercloud.uk"', false);
    }
}
