<div class="{{ $layout === 'responsive' ? 'flex flex-col md:flex-row' : 'flex flex-row' }} items-start md:items-center gap-1 md:gap-2">
    @foreach($stages as $stage)
        @php $status = $stageStatus($stage); @endphp
        <div class="flex items-center gap-1 md:gap-2">
            <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium
                {{ $status === 'completed' ? 'bg-green-500 text-white' : '' }}
                {{ $status === 'active' ? 'bg-blue-500 text-white ring-2 ring-blue-200' : '' }}
                {{ $status === 'future' ? 'bg-gray-200 text-gray-500' : '' }}">
                @if($status === 'completed')
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                @else
                    {{ $loop->iteration }}
                @endif
            </div>
            <span class="text-xs {{ $status === 'active' ? 'font-semibold text-blue-700' : 'text-gray-600' }}">
                {{ $labels[$stage] ?? $stage }}
            </span>
            @unless($loop->last)
                <div class="hidden md:block w-6 h-0.5 {{ $status === 'completed' ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                <div class="md:hidden w-0.5 h-4 mx-auto {{ $status === 'completed' ? 'bg-green-500' : 'bg-gray-200' }}"></div>
            @endunless
        </div>
    @endforeach
</div>
