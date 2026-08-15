<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WhmConnectionException;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class WhmConfigController extends Controller
{
    /**
     * Show the WHM configuration form.
     */
    public function edit(): View
    {
        $settings = [
            'whm_hostname' => $this->getDecryptedSetting('whm_hostname'),
            'whm_api_token' => $this->getDecryptedSetting('whm_api_token'),
            'whm_default_package' => Setting::get('whm_default_package'),
            'whm_nameserver_0' => Setting::get('whm_nameserver_0', 'ns0.thundercloud.uk'),
            'whm_nameserver_1' => Setting::get('whm_nameserver_1', 'ns1.thundercloud.uk'),
        ];

        return view('admin.settings.whm', compact('settings'));
    }

    /**
     * Validate WHM configuration, test connectivity, and store settings.
     */
    public function update(Request $request, WhmService $whmService): RedirectResponse
    {
        $request->validate([
            'whm_hostname' => 'required|string|max:255',
            'whm_api_token' => 'required|string|max:1000',
            'whm_default_package' => 'nullable|string|max:255',
            'whm_nameserver_0' => 'nullable|string|max:255',
            'whm_nameserver_1' => 'nullable|string|max:255',
        ]);

        $hostname = $request->input('whm_hostname');
        $apiToken = $request->input('whm_api_token');

        // Test connectivity before saving
        try {
            $whmService->testConnection($hostname, $apiToken);
        } catch (WhmConnectionException $e) {
            return back()
                ->withInput()
                ->withErrors(['whm_connection' => $e->getMessage()]);
        }

        // Encrypt and store hostname and API token
        Setting::set('whm_hostname', Crypt::encryptString($hostname));
        Setting::set('whm_api_token', Crypt::encryptString($apiToken));

        // Store plain-text settings
        Setting::set('whm_default_package', $request->input('whm_default_package'));
        Setting::set('whm_nameserver_0', $request->input('whm_nameserver_0', 'ns0.thundercloud.uk'));
        Setting::set('whm_nameserver_1', $request->input('whm_nameserver_1', 'ns1.thundercloud.uk'));

        return back()->with('success', 'WHM configuration saved successfully.');
    }

    /**
     * Retrieve and decrypt a WHM setting from the database.
     */
    private function getDecryptedSetting(string $key): ?string
    {
        $value = Setting::get($key);

        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
