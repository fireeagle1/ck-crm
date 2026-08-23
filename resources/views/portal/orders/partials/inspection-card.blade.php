<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden"
     x-data="{
        lightboxOpen: false,
        currentIndex: 0,
        photos: {{ json_encode(collect($inspection->photos ?? [])->map(fn($p) => route('portal.inspection-photo', $p))->values()->toArray()) }},
        open(index) { this.currentIndex = index; this.lightboxOpen = true; },
        close() { this.lightboxOpen = false; },
        next() { this.currentIndex = (this.currentIndex + 1) % this.photos.length; },
        prev() { this.currentIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length; },
     }">

    {{-- Card header --}}
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex items-center gap-2">
            @if(str_contains(strtolower($title), 'checkout'))
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </span>
            @else
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                </span>
            @endif
            <h5 class="font-semibold text-sm text-gray-800">{{ $title }}</h5>
        </div>
        <div class="flex items-center gap-2">
            @if(!empty($inspection->photos))
                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{{ count($inspection->photos) }} {{ Str::plural('photo', count($inspection->photos)) }}</span>
            @endif
            <span class="text-xs text-gray-500">{{ $inspection->inspected_at->format('d M Y, H:i') }}</span>
        </div>
    </div>

    {{-- Photo gallery --}}
    @if(!empty($inspection->photos))
        <div class="p-4">
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                @foreach($inspection->photos as $index => $photo)
                    <button @click="open({{ $index }})"
                            class="group relative aspect-square rounded-lg overflow-hidden bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 hover:ring-2 hover:ring-blue-300 hover:shadow-md">
                        <img src="{{ route('portal.inspection-photo', $photo) }}"
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                             alt="Inspection photo {{ $index + 1 }}"
                             loading="lazy" />
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-200 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Lightbox overlay with navigation --}}
        <div x-show="lightboxOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="close()"
             @keydown.right.window="if(lightboxOpen) next()"
             @keydown.left.window="if(lightboxOpen) prev()"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
             x-cloak>

            {{-- Close button --}}
            <button @click="close()"
                    class="absolute top-4 right-4 min-w-[44px] min-h-[44px] flex items-center justify-center text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition focus:outline-none focus:ring-2 focus:ring-white"
                    aria-label="Close lightbox">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Photo counter --}}
            <div class="absolute top-5 left-1/2 -translate-x-1/2 text-white/80 text-sm font-medium bg-black/40 px-3 py-1 rounded-full">
                <span x-text="currentIndex + 1"></span> / <span x-text="photos.length"></span>
            </div>

            {{-- Previous button --}}
            <button @click.stop="prev()"
                    x-show="photos.length > 1"
                    class="absolute left-3 sm:left-6 min-w-[44px] min-h-[44px] flex items-center justify-center text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition focus:outline-none focus:ring-2 focus:ring-white"
                    aria-label="Previous photo">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Main image --}}
            <img :src="photos[currentIndex]"
                 @click.stop
                 class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain"
                 alt="Inspection photo full size" />

            {{-- Next button --}}
            <button @click.stop="next()"
                    x-show="photos.length > 1"
                    class="absolute right-3 sm:right-6 min-w-[44px] min-h-[44px] flex items-center justify-center text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-full transition focus:outline-none focus:ring-2 focus:ring-white"
                    aria-label="Next photo">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Thumbnail strip --}}
            <div x-show="photos.length > 1" class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2 bg-black/40 px-3 py-2 rounded-full max-w-[90vw] overflow-x-auto">
                <template x-for="(photo, i) in photos" :key="i">
                    <button @click.stop="currentIndex = i"
                            :class="i === currentIndex ? 'ring-2 ring-white scale-110' : 'opacity-60 hover:opacity-100'"
                            class="w-10 h-10 rounded-md overflow-hidden flex-shrink-0 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <img :src="photo" class="w-full h-full object-cover" alt="" />
                    </button>
                </template>
            </div>
        </div>
    @endif

    {{-- Condition notes --}}
    @if($inspection->condition_notes)
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                </svg>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $inspection->condition_notes }}</p>
            </div>
        </div>
    @endif
</div>
