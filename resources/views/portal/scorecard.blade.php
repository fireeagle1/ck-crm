<x-portal-layout>
    <x-slot:title>Monthly Scorecard</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">Monthly Support Scorecard</h1>
    <p class="text-gray-500 mb-6">{{ now()->format('F Y') }} performance summary for your Technical Support Package.</p>

    {{-- Main stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        {{-- Total Requests --}}
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Total Requests</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-bold">{{ $thisTotal }}</p>
                @if ($lastTotal > 0)
                    @php $diff = $thisTotal - $lastTotal; @endphp
                    <span class="text-sm font-medium {{ $diff > 0 ? 'text-amber-600' : ($diff < 0 ? 'text-green-600' : 'text-gray-400') }}">
                        {{ $diff > 0 ? '↑' : ($diff < 0 ? '↓' : '→') }} {{ abs($diff) }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-400 mt-1">Last month: {{ $lastTotal }}</p>
        </div>

        {{-- Resolved --}}
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Resolved</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-bold text-green-700">{{ $thisClosed }}</p>
                @php $closedDiff = $thisClosed - $lastClosed; @endphp
                <span class="text-sm font-medium {{ $closedDiff > 0 ? 'text-green-600' : ($closedDiff < 0 ? 'text-amber-600' : 'text-gray-400') }}">
                    {{ $closedDiff > 0 ? '↑' : ($closedDiff < 0 ? '↓' : '→') }} {{ abs($closedDiff) }}
                </span>
            </div>
            <p class="text-xs text-gray-400 mt-1">Still open: {{ $thisOpen }}</p>
        </div>

        {{-- Avg Response Time --}}
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg Response</p>
            <div class="flex items-end gap-2 mt-1">
                <p class="text-3xl font-bold">{{ $thisResponseTimes !== null ? $thisResponseTimes . 'h' : '—' }}</p>
                @if ($thisResponseTimes !== null && $lastResponseTimes !== null)
                    @php $rtDiff = round($thisResponseTimes - $lastResponseTimes, 1); @endphp
                    <span class="text-sm font-medium {{ $rtDiff < 0 ? 'text-green-600' : ($rtDiff > 0 ? 'text-amber-600' : 'text-gray-400') }}">
                        {{ $rtDiff < 0 ? '↓' : ($rtDiff > 0 ? '↑' : '→') }} {{ abs($rtDiff) }}h
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-400 mt-1">Last month: {{ $lastResponseTimes !== null ? $lastResponseTimes . 'h' : '—' }}</p>
        </div>

        {{-- Incidents vs Requests --}}
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Breakdown</p>
            <div class="mt-2 space-y-1">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">🚨 Incidents</span>
                    <span class="font-semibold">{{ $thisIncidents }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">📋 Requests</span>
                    <span class="font-semibold">{{ $thisRequests }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Category breakdown --}}
    @if ($categories->isNotEmpty())
        <div class="bg-white rounded-lg border p-6 mb-8">
            <h2 class="font-bold mb-4">Requests by Category</h2>
            <div class="space-y-2">
                @foreach ($categories as $category => $count)
                    @php $percentage = $thisTotal > 0 ? round(($count / $thisTotal) * 100) : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ $category }}</span>
                            <span class="text-gray-500">{{ $count }} ({{ $percentage }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Comparison table --}}
    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Month-on-Month Comparison</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold text-gray-600">Metric</th>
                    <th class="px-5 py-3 text-right font-semibold text-gray-600">{{ now()->subMonth()->format('M') }}</th>
                    <th class="px-5 py-3 text-right font-semibold text-gray-600">{{ now()->format('M') }}</th>
                    <th class="px-5 py-3 text-right font-semibold text-gray-600">Trend</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr>
                    <td class="px-5 py-3">Total Tickets</td>
                    <td class="px-5 py-3 text-right">{{ $lastTotal }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ $thisTotal }}</td>
                    <td class="px-5 py-3 text-right">
                        @php $d = $thisTotal - $lastTotal; @endphp
                        <span class="{{ $d > 0 ? 'text-amber-600' : ($d < 0 ? 'text-green-600' : 'text-gray-400') }}">
                            {{ $d > 0 ? '↑' . $d : ($d < 0 ? '↓' . abs($d) : '—') }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3">Resolved</td>
                    <td class="px-5 py-3 text-right">{{ $lastClosed }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ $thisClosed }}</td>
                    <td class="px-5 py-3 text-right">
                        @php $d = $thisClosed - $lastClosed; @endphp
                        <span class="{{ $d > 0 ? 'text-green-600' : ($d < 0 ? 'text-amber-600' : 'text-gray-400') }}">
                            {{ $d > 0 ? '↑' . $d : ($d < 0 ? '↓' . abs($d) : '—') }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3">Incidents</td>
                    <td class="px-5 py-3 text-right">{{ $lastIncidents }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ $thisIncidents }}</td>
                    <td class="px-5 py-3 text-right">
                        @php $d = $thisIncidents - $lastIncidents; @endphp
                        <span class="{{ $d > 0 ? 'text-amber-600' : ($d < 0 ? 'text-green-600' : 'text-gray-400') }}">
                            {{ $d > 0 ? '↑' . $d : ($d < 0 ? '↓' . abs($d) : '—') }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3">Service Requests</td>
                    <td class="px-5 py-3 text-right">{{ $lastRequests }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ $thisRequests }}</td>
                    <td class="px-5 py-3 text-right">
                        @php $d = $thisRequests - $lastRequests; @endphp
                        <span class="{{ $d > 0 ? 'text-amber-600' : ($d < 0 ? 'text-green-600' : 'text-gray-400') }}">
                            {{ $d > 0 ? '↑' . $d : ($d < 0 ? '↓' . abs($d) : '—') }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3">Avg Response Time</td>
                    <td class="px-5 py-3 text-right">{{ $lastResponseTimes ? $lastResponseTimes . 'h' : '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ $thisResponseTimes ? $thisResponseTimes . 'h' : '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        @if ($thisResponseTimes && $lastResponseTimes)
                            @php $d = round($thisResponseTimes - $lastResponseTimes, 1); @endphp
                            <span class="{{ $d < 0 ? 'text-green-600' : ($d > 0 ? 'text-amber-600' : 'text-gray-400') }}">
                                {{ $d < 0 ? '↓' . abs($d) . 'h' : ($d > 0 ? '↑' . $d . 'h' : '—') }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</x-portal-layout>
