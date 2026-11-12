<?php

namespace Tests\Feature\Admin;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Service type/status selectors are rendered from the enums.
 *
 * Validates: Requirements 19.6
 */
class ServiceEnumSelectorsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    /** @return list<string> option values inside the named select */
    private function optionValues(string $html, string $selectId): array
    {
        $this->assertSame(1, preg_match('/<select[^>]*id="'.$selectId.'".*?<\/select>/s', $html, $m));
        preg_match_all('/<option value="([^"]*)"/', $m[0], $opts);

        return array_values(array_filter($opts[1], fn ($v) => $v !== ''));
    }

    public function test_create_view_options_match_enums(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.services.create'))->assertOk()->getContent();

        $this->assertSame(ServiceType::values(), $this->optionValues($html, 'service_type'));
    }

    public function test_edit_view_options_match_enums(): void
    {
        $service = Service::factory()->webHosting()->create();

        $html = $this->actingAs($this->admin)->get(route('admin.services.edit', $service))->assertOk()->getContent();

        $this->assertSame(ServiceType::values(), $this->optionValues($html, 'service_type'));
        $this->assertSame(ServiceStatus::values(), $this->optionValues($html, 'status'));
    }

    public function test_service_views_have_no_hard_coded_type_or_status_lists(): void
    {
        $labels = array_merge(ServiceType::values(), ServiceStatus::values());

        foreach (glob(resource_path('views/admin/services/*.blade.php')) as $file) {
            $source = file_get_contents($file);

            foreach ($labels as $label) {
                // <option value="Label"> or a PHP array literal containing the label
                $this->assertDoesNotMatchRegularExpression(
                    '/<option\s+value="'.preg_quote($label, '/').'"/',
                    $source,
                    basename($file)." hard-codes option '$label'"
                );
                $this->assertDoesNotMatchRegularExpression(
                    "/\\[[^\\]]*'".preg_quote($label, '/')."'[^\\]]*,[^\\]]*\\]/",
                    $source,
                    basename($file)." hard-codes a list containing '$label'"
                );
            }
        }
    }
}
