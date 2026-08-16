@props([
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => '',
    'required' => false,
])

@php
    $editorId = $id ?? $name;
@endphp

<div x-data="richTextEditor('{{ $editorId }}', @js($value ?? ''), {{ $required ? 'true' : 'false' }})" x-init="init()" class="rich-text-editor-wrapper">
    <div x-ref="editor" class="bg-white"></div>
    <input type="hidden" name="{{ $name }}" x-ref="hiddenInput" :value="content">
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('richTextEditor', (editorId, initialValue, isRequired) => ({
        content: initialValue || '',
        quill: null,

        init() {
            this.quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder: '{{ $placeholder }}',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        [{ 'header': [1, 2, 3, false] }],
                        ['clean']
                    ]
                }
            });

            // Set initial content
            if (this.content) {
                this.quill.root.innerHTML = this.content;
            }

            // Sync changes back to hidden input
            this.quill.on('text-change', () => {
                const html = this.quill.root.innerHTML;
                // If the editor is empty (just a blank paragraph), store empty string
                this.content = (html === '<p><br></p>') ? '' : html;
            });

            // Add required validation support
            if (isRequired) {
                const form = this.$refs.hiddenInput.closest('form');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        if (!this.content || this.content.trim() === '') {
                            e.preventDefault();
                            this.quill.focus();
                        }
                    });
                }
            }
        }
    }));
});
</script>
@endpush
@endonce

@once
@push('styles')
<style>
.rich-text-editor-wrapper .ql-container {
    min-height: 120px;
    font-size: 0.875rem;
    border-bottom-left-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}
.rich-text-editor-wrapper .ql-toolbar {
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    background-color: #f9fafb;
}
.rich-text-editor-wrapper .ql-editor {
    min-height: 100px;
}
.rich-text-editor-wrapper .ql-editor.ql-blank::before {
    font-style: normal;
    color: #9ca3af;
}
</style>
@endpush
@endonce
