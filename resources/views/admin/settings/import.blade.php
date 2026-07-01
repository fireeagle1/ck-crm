<x-admin-layout>
    <x-slot:title>Import Data</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.import', 'label' => 'Import Data', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ];
    @endphp

    <div class="flex gap-6">
        {{-- Sidebar --}}
        <aside class="w-56 shrink-0 hidden md:block">
            <nav class="bg-white rounded-lg shadow-sm border overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-900">Settings</h2>
                </div>
                <ul class="py-1">
                    @foreach ($settingsNav as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm transition
                                      {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-700 font-medium border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs($item['route']) ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $item['icon'] }}"/>
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-semibold mb-2">Import from Legacy CRM</h1>
            <p class="text-sm text-gray-500 mb-6">Connect to your existing MySQL database and import customer data directly.</p>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <form method="POST" action="{{ route('admin.import.run') }}">
                    @csrf

                    <div class="space-y-6">
                        {{-- Connection details --}}
                        <fieldset class="border border-gray-200 rounded-md p-4">
                            <legend class="text-sm font-semibold text-gray-700 px-2">Source Database Connection</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                                <div>
                                    <label for="source_host" class="block text-sm font-medium text-gray-700">Host</label>
                                    <input type="text" name="source_host" id="source_host" required
                                           value="{{ old('source_host', '127.0.0.1') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="source_port" class="block text-sm font-medium text-gray-700">Port</label>
                                    <input type="number" name="source_port" id="source_port" required
                                           value="{{ old('source_port', '3306') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="source_database" class="block text-sm font-medium text-gray-700">Database Name</label>
                                    <input type="text" name="source_database" id="source_database" required
                                           value="{{ old('source_database') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Your old CRM database name">
                                </div>
                                <div>
                                    <label for="source_username" class="block text-sm font-medium text-gray-700">Username</label>
                                    <input type="text" name="source_username" id="source_username" required
                                           value="{{ old('source_username', 'root') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="source_password" class="block text-sm font-medium text-gray-700">Password</label>
                                    <input type="password" name="source_password" id="source_password"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </fieldset>

                        {{-- What to import --}}
                        <fieldset class="border border-gray-200 rounded-md p-4">
                            <legend class="text-sm font-semibold text-gray-700 px-2">Data to Import</legend>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-2">
                                @foreach (['customers' => 'Customers', 'users' => 'Users', 'services' => 'Services', 'invoices' => 'Invoices', 'tickets' => 'Tickets', 'domains' => 'Domains', 'assets' => 'Assets (CMDB)'] as $key => $label)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="import_{{ $key }}" value="0">
                                        <input type="checkbox" name="import_{{ $key }}" value="1" checked
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        {{-- Fresh import option --}}
                        <fieldset class="border border-red-200 rounded-md p-4 bg-red-50">
                            <legend class="text-sm font-semibold text-red-700 px-2">Fresh Import</legend>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="fresh_import" value="0">
                                <input type="checkbox" name="fresh_import" value="1"
                                       class="rounded border-red-300 text-red-600 shadow-sm focus:ring-red-500">
                                <span class="text-red-800 font-medium">Truncate selected tables before importing</span>
                            </label>
                            <p class="text-xs text-red-600 mt-2">This will DELETE all existing data in the selected tables and replace it with the source data. Your admin account will be preserved.</p>
                        </fieldset>

                        <div class="bg-amber-50 border border-amber-200 rounded-md p-3 text-sm text-amber-800">
                            <strong>Note:</strong> Customers are always imported first. Duplicate Stripe IDs in the source data are handled automatically. Rows referencing non-existent customers are skipped.
                        </div>

                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                            Run Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
