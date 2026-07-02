<x-admin-layout>
    <x-slot:title>Communications</x-slot:title>

    <h1 class="text-2xl font-bold mb-2">Send Email</h1>
    <p class="text-sm text-gray-500 mb-6">Send branded emails to all customers or selected ones.</p>

    <div x-data="commsForm()">
        {{-- Step 1: Compose --}}
        <div x-show="step === 1">
            <div class="bg-white rounded-lg border p-6 max-w-3xl">
                <div class="space-y-5">
                    {{-- Recipients --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Recipients</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="recipients" value="all" class="text-blue-600 focus:ring-blue-500">
                                All customers
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="recipients" value="selected" class="text-blue-600 focus:ring-blue-500">
                                Selected
                            </label>
                        </div>
                    </div>

                    <div x-show="recipients === 'selected'" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Customers</label>
                        <select x-model="customerIds" multiple size="6"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}">{{ $customer->company_name ?: $customer->customer_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple.</p>
                    </div>

                    {{-- Subject --}}
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700">Subject</label>
                        <input type="text" id="subject" x-model="subject" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Important update about your services">
                    </div>

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Message</label>
                        <div class="border rounded-md overflow-hidden">
                            <div class="bg-gray-50 border-b px-3 py-2 flex gap-1">
                                <button type="button" @click="insertTag('b')" class="px-2 py-1 text-xs font-bold border rounded hover:bg-gray-200">B</button>
                                <button type="button" @click="insertTag('em')" class="px-2 py-1 text-xs italic border rounded hover:bg-gray-200">I</button>
                                <button type="button" @click="insertLink()" class="px-2 py-1 text-xs border rounded hover:bg-gray-200">🔗 Link</button>
                                <button type="button" @click="insertBreak()" class="px-2 py-1 text-xs border rounded hover:bg-gray-200">↵ Break</button>
                            </div>
                            <textarea id="body" x-model="body" rows="10"
                                      class="block w-full border-0 focus:ring-0 text-sm"
                                      placeholder="Write your message here. Select text and click Bold/Italic to format."></textarea>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Select text then click a toolbar button to format. The email wraps in the branded template.</p>
                    </div>

                    <button type="button" @click="preview()"
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Preview & Review →
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 2: Preview & Send --}}
        <div x-show="step === 2" x-transition>
            <div class="max-w-4xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Review Your Email</h2>
                    <button type="button" @click="step = 1" class="text-sm text-blue-600 hover:underline">&larr; Back to Edit</button>
                </div>

                <div class="bg-white rounded-lg border overflow-hidden mb-4" style="min-height: 500px;">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <p class="text-sm font-bold text-gray-700">Email Preview</p>
                        <p class="text-xs text-gray-400">This is how your email will appear to recipients.</p>
                    </div>
                    <iframe :srcdoc="previewHtml" style="width:100%; height:550px; border:0;"></iframe>
                </div>

                <form method="POST" action="{{ route('admin.communications.send') }}">
                    @csrf
                    <input type="hidden" name="recipients" :value="recipients">
                    <input type="hidden" name="subject" :value="subject">
                    <input type="hidden" name="body" :value="body">
                    <template x-for="id in customerIds" :key="id">
                        <input type="hidden" name="customer_ids[]" :value="id">
                    </template>

                    <div class="flex gap-3">
                        <button type="submit" class="px-5 py-2.5 bg-green-600 text-white rounded-md text-sm font-semibold hover:bg-green-700"
                                onclick="return confirm('Send this email now? This cannot be undone.')">
                            ✓ Confirm & Send
                        </button>
                        <button type="button" @click="step = 1" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function commsForm() {
            return {
                step: 1,
                recipients: 'all',
                customerIds: [],
                subject: '',
                body: '',
                previewHtml: '',

                preview() {
                    if (!this.subject || !this.body) {
                        alert('Please enter a subject and message.');
                        return;
                    }

                    fetch('{{ route("admin.communications.preview") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'text/html',
                        },
                        body: JSON.stringify({ subject: this.subject, body: this.body })
                    })
                    .then(r => r.text())
                    .then(html => {
                        this.previewHtml = html;
                        this.step = 2;
                    })
                    .catch(e => alert('Preview failed: ' + e.message));
                },

                insertTag(tag) {
                    const ta = document.getElementById('body');
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const selected = this.body.substring(start, end) || 'text';
                    this.body = this.body.substring(0, start) + '<' + tag + '>' + selected + '</' + tag + '>' + this.body.substring(end);
                    ta.focus();
                },

                insertLink() {
                    const url = prompt('Enter URL:', 'https://');
                    if (!url) return;
                    const ta = document.getElementById('body');
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const selected = this.body.substring(start, end) || 'Click here';
                    const link = '<a href="' + url + '" style="color:#2563eb;text-decoration:underline;">' + selected + '</a>';
                    this.body = this.body.substring(0, start) + link + this.body.substring(end);
                    ta.focus();
                },

                insertBreak() {
                    const ta = document.getElementById('body');
                    const pos = ta.selectionStart;
                    this.body = this.body.substring(0, pos) + '<br><br>' + this.body.substring(pos);
                    ta.focus();
                }
            }
        }
    </script>
</x-admin-layout>
