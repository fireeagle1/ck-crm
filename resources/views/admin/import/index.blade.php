<x-admin-layout>
    <x-slot:title>Import Data</x-slot:title>

    <h1 class="text-2xl font-semibold mb-2">Import from Legacy CRM</h1>
    <p class="text-sm text-gray-500 mb-6">Connect to your existing MySQL database and import customer data. This uses updateOrCreate so it's safe to run multiple times.</p>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.import.run') }}">
            @csrf

            <div class="space-y-6">
                {{-- Connection details --}}
                <fieldset class="border border-gray-200 rounded-md p-4">
                    <legend class="text-sm font-semibold text-gray-700 px-2">Source Database Connection</legend>
                    <div class="grid grid-cols-2 gap-4 mt-2">
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
                        <div class="col-span-2">
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

                <div class="bg-amber-50 border border-amber-200 rounded-md p-3 text-sm text-amber-800">
                    <strong>Note:</strong> Import customers first if other tables have foreign keys. The importer uses updateOrCreate, so existing records will be updated rather than duplicated. User passwords from the old system will be preserved (bcrypt hashes are compatible).
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                    Run Import
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
