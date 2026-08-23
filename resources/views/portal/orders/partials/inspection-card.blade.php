<div class="border rounded-lg p-4" x-data="{ lightboxOpen: false, lightboxSrc: '' }">
    <div class="flex justify-between items-center mb-3">
        <h5 class="font-medium text-sm">{{ $title }}</h5>
        <span class="text-xs text-gray-500">{{ $inspection->inspected_at->format('d M Y, H:i') }}</span>
    </div>

    {{-- Photo gallery --}}
    @if(!empty($inspection->photos))
        <div class="flex gap-2 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($inspection->photos as $photo)
                <button @click="lightboxSrc = '{{ route('portal.inspection-photo', $photo) }}'; lightboxOpen = true"
                        class="flex-shrink-0 w-20 h-20 sm:w-full sm:h-auto sm:aspect-square rounded-lg overflow-hidden focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <img src="{{ route('portal.inspection-photo', $photo) }}"
                         class="w-full h-full object-cover" alt="Inspection photo" loading="lazy" />
                </button>
            @endforeach
        </div>

        {{-- Lightbox overlay --}}
        <div x-show="lightboxOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="lightboxOpen = false"
             @keydown.escape.window="lightboxOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4"
             x-cloak>
            <button @click="lightboxOpen = false"
                    class="absolute top-4 right-4 min-w-[44px] min-h-[44px] flex items-center justify-center text-white hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-white rounded-full"
                    aria-label="Close lightbox">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <img :src="lightboxSrc" @click.stop class="max-w-full max-h-[85vh] rounded-lg shadow-lg" alt="Inspection photo full size" />
        </div>
    @endif

    {{-- Condition notes (NO inspector name, NO damage flag) --}}
    @if($inspection->condition_notes)
        <p class="mt-3 text-sm text-gray-700">{{ $inspection->condition_notes }}</p>
    @endif
</div>
