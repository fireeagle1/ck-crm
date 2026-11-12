<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * _Requirements: 7.1, 7.2, 7.3, 7.6_
 */
class WhmCredentialCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_a_noop_and_reports_env_config_removed(): void
    {
        Setting::set('whm_hostname', Crypt::encryptString('existing.host'));

        $this->artisan('whm:import-env-credentials')
            ->expectsOutputToContain('Nothing to import')
            ->assertExitCode(0);

        $this->assertNull(config('services.whm'));
        $this->assertNull(Setting::find('whm_api_token'));
        $this->assertSame('existing.host', Crypt::decryptString(Setting::find('whm_hostname')->value));
    }

    public function test_only_whm_service_calls_whm_api_and_nothing_reads_services_whm(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            $rel = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (str_contains($src, 'json-api/') && $rel !== 'Services'.DIRECTORY_SEPARATOR.'WhmService.php') {
                $offenders[] = "$rel: json-api/";
            }
            if (preg_match('/services\.whm(?!_)/', $src)) {
                $offenders[] = "$rel: services.whm";
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_check_reports_success(): void
    {
        Setting::set('whm_hostname', Crypt::encryptString('whm.example.test'));
        Setting::set('whm_api_token', Crypt::encryptString('SECRET-TOKEN-123'));
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => []]], 200)]);

        $this->artisan('whm:check')
            ->expectsOutputToContain('WHM connection OK')
            ->doesntExpectOutputToContain('SECRET-TOKEN-123')
            ->assertExitCode(0);

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://whm.example.test:2087/'));
    }

    public function test_check_fails_with_non_zero_exit_on_api_error(): void
    {
        Setting::set('whm_hostname', Crypt::encryptString('whm.example.test'));
        Setting::set('whm_api_token', Crypt::encryptString('SECRET-TOKEN-123'));
        Http::fake(['*' => Http::response('denied', 403)]);

        $this->artisan('whm:check')
            ->expectsOutputToContain('WHM check failed')
            ->doesntExpectOutputToContain('SECRET-TOKEN-123')
            ->assertExitCode(1);
    }

    public function test_check_fails_when_credentials_missing(): void
    {
        Http::fake();

        $this->artisan('whm:check')->assertExitCode(1);
        Http::assertNothingSent();
    }
}
