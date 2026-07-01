<x-portal-layout>
    <x-slot:title>Dashboard</x-slot:title>

    {{-- Greeting --}}
    <section class="mb-6">
        <h1 class="text-2xl font-semibold">
            {{ now()->hour < 12 ? 'Good Morning' : (now()->hour < 18 ? 'Good Afternoon' : 'Good Evening') }}, {{ auth()->user()->first_name ?? 'there' }}.
        </h1>
    </section>

    {{-- Support Plan banner --}}
    <section class="bg-white rounded-lg border-l-4 border-gray-900 p-5 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Support Plan</p>
                <h2 class="text-lg font-semibold mt-1">Technical Support</h2>
                <p class="text-sm text-gray-600 mt-1">
                    @if ($hasSupportPlan)
                        Your account includes the Technical Support Package. Track requests and manage support work.
                    @else
                        Website changes, hardware support, and day-to-day technical support. Starting from £45/month.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.tickets.index') }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                    Support Tickets
                </a>
                <a href="#" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                    View Support Plan
                </a>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Active Websites</p>
            <p class="text-3xl font-semibold mt-1">{{ $activeServices }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Open Tickets</p>
            <p class="text-3xl font-semibold mt-1">{{ $openTickets }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Domains Expiring (30d)</p>
            <p class="text-3xl font-semibold mt-1">{{ $expiringDomains }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Quick Actions</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('portal.tickets.create') }}" class="text-sm text-blue-600 hover:underline">New ticket</a>
            </div>
        </div>
    </div>
</x-portal-layout>
