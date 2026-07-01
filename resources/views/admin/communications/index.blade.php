<x-admin-layout>
    <x-slot:title>Communications</x-slot:title>

    <h1 class="text-2xl font-bold mb-2">Send Email</h1>
    <p class="text-sm text-gray-500 mb-6">Send branded emails to all customers or selected ones. Supports basic HTML formatting.</p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Compose --}}
        <div class="bg-white rounded-lg border p-6">
            <form method="POST" action="{{ route('admin.communications.send') }}" id="emailForm" x-data="{ recipients: 'all' }">
                @csrf

                <div class="space-y-5">
                    {{-- Recipients --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Recipients</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="recipients" value="all" x-model="recipients" class="text-blue-600 focus:ring-blue-500">
                                All customers
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="recipients" value="selected" x-model="recipients" class="text-blue-600 focus:ring-blue-500">
                                Selected
                            </label>
                        </div>
                    </div>

                    <div x-show="recipients === 'selected'" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Customers</label>
                        <select name="customer_ids[]" multiple size="6"
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
                        <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Important update about your services">
                    </div>

                    {{-- Body with formatting toolbar --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Message (HTML supported)</label>
                        <div class="border rounded-md overflow-hidden">
                            {{-- Toolbar --}}
                            <div class="bg-gray-50 border-b px-3 py-2 flex gap-1">
                                <button type="button" onclick="insertTag('b')" class="px-2 py-1 text-xs font-bold border rounded hover:bg-gray-200" title="Bold">B</button>
                                <button type="button" onclick="insertTag('em')" class="px-2 py-1 text-xs italic border rounded hover:bg-gray-200" title="Italic">I</button>
                                <button type="button" onclick="insertLink()" class="px-2 py-1 text-xs border rounded hover:bg-gray-200" title="Insert link">🔗 Link</button>
                                <button type="button" onclick="insertText('<br><br>')" class="px-2 py-1 text-xs border rounded hover:bg-gray-200" title="Line break">↵ Break</button>
                                <button type="button" onclick="insertColour()" class="px-2 py-1 text-xs border rounded hover:bg-gray-200" title="Colour text">🎨 Colour</button>
                            </div>
                            <textarea name="body" id="body" rows="12" required
                                      class="block w-full border-0 focus:ring-0 text-sm font-mono"
                                      placeholder="Write your message here. Use the toolbar above for formatting.&#10;&#10;Example: <b>Bold text</b>, <a href='https://...'>click here</a>">{{ old('body') }}</textarea>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">The email wraps your content in the branded template with logo, greeting, and footer links.</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700"
                                onclick="return confirm('Send this email? This cannot be undone.')">
                            Send Email
                        </button>
                        <button type="button" onclick="showPreview()" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">
                            Preview
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Preview pane --}}
        <div>
            <div class="bg-white rounded-lg border overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700">Email Preview</h2>
                    <p class="text-xs text-gray-400">Click "Preview" to see how the email will look.</p>
                </div>
                <div id="previewPane" class="p-0" style="min-height: 400px;">
                    <div class="flex items-center justify-center h-64 text-gray-400 text-sm">
                        Click "Preview" to see the rendered email
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function insertTag(tag) {
            const textarea = document.getElementById('body');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end);
            const replacement = `<${tag}>${selected || 'text'}</${tag}>`;
            textarea.setRangeText(replacement, start, end, 'end');
            textarea.focus();
        }

        function insertLink() {
            const url = prompt('Enter URL:', 'https://');
            if (!url) return;
            const textarea = document.getElementById('body');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end) || 'Click here';
            const replacement = `<a href="${url}" style="color:#2563eb; text-decoration:underline;">${selected}</a>`;
            textarea.setRangeText(replacement, start, end, 'end');
            textarea.focus();
        }

        function insertColour() {
            const colour = prompt('Enter colour (e.g. #dc2626 for red, #2563eb for blue):', '#2563eb');
            if (!colour) return;
            const textarea = document.getElementById('body');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end) || 'coloured text';
            const replacement = `<span style="color:${colour}; font-weight:600;">${selected}</span>`;
            textarea.setRangeText(replacement, start, end, 'end');
            textarea.focus();
        }

        function insertText(text) {
            const textarea = document.getElementById('body');
            const pos = textarea.selectionStart;
            textarea.setRangeText(text, pos, pos, 'end');
            textarea.focus();
        }

        function showPreview() {
            const subject = document.getElementById('subject').value;
            const body = document.getElementById('body').value;

            if (!subject || !body) {
                alert('Please enter a subject and message first.');
                return;
            }

            fetch('{{ route('admin.communications.preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'text/html',
                },
                body: JSON.stringify({ subject, body })
            })
            .then(res => res.text())
            .then(html => {
                const pane = document.getElementById('previewPane');
                pane.innerHTML = `<iframe srcdoc="${html.replace(/"/g, '&quot;')}" style="width:100%; height:600px; border:0;"></iframe>`;
            })
            .catch(err => {
                alert('Preview failed: ' + err.message);
            });
        }
    </script>
</x-admin-layout>
