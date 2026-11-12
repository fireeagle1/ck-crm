@props([
    'name' => 'company_id',
    'id' => 'company_id',
    'label' => 'Customer',
    'selected' => null,
    'emptyLabel' => 'Unassigned',
])
{{-- Accessible autocomplete backed by admin.domains.customer-search (max 20 results). --}}
<div id="{{ $id }}-picker" class="relative" data-url="{{ route('admin.domains.customer-search') }}">
    <label for="{{ $id }}-input" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $selected?->company_id }}">
    <input type="text" id="{{ $id }}-input" autocomplete="off" role="combobox"
           aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-list"
           value="{{ $selected?->company_name }}" placeholder="Search customers… ({{ $emptyLabel }} if blank)"
           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
    <ul id="{{ $id }}-list" role="listbox" aria-label="{{ $label }} matches" hidden
        class="absolute z-10 mt-1 w-full max-h-60 overflow-auto bg-white border rounded-md shadow-lg text-sm"></ul>
    <p id="{{ $id }}-status" class="sr-only" aria-live="polite"></p>
</div>
<script>
(function () {
    var root = document.getElementById(@json($id . '-picker'));
    var input = document.getElementById(@json($id . '-input'));
    var hidden = document.getElementById(@json($id));
    var list = document.getElementById(@json($id . '-list'));
    var status = document.getElementById(@json($id . '-status'));
    var timer = null;

    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }
    function choose(item) {
        hidden.value = item ? item.id : '';
        input.value = item ? item.name : '';
        close();
    }
    function render(items) {
        list.innerHTML = '';
        var none = document.createElement('li');
        none.setAttribute('role', 'option');
        none.tabIndex = -1;
        none.className = 'px-3 py-2 cursor-pointer text-gray-500 hover:bg-gray-100 focus:bg-gray-100';
        none.textContent = @json($emptyLabel);
        none.addEventListener('click', function () { choose(null); });
        list.appendChild(none);
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.tabIndex = -1;
            li.className = 'px-3 py-2 cursor-pointer hover:bg-gray-100 focus:bg-gray-100';
            li.textContent = item.name;
            li.addEventListener('click', function () { choose(item); });
            li.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); choose(item); }
            });
            list.appendChild(li);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        status.textContent = items.length + ' customers found';
    }
    function search() {
        fetch(root.dataset.url + '?q=' + encodeURIComponent(input.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(render);
    }
    input.addEventListener('input', function () {
        if (input.value.trim() === '') { hidden.value = ''; }
        clearTimeout(timer);
        timer = setTimeout(search, 250);
    });
    input.addEventListener('focus', search);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(); }
        if (e.key === 'ArrowDown' && list.firstChild) { e.preventDefault(); list.firstChild.focus(); }
    });
    list.addEventListener('keydown', function (e) {
        var cur = document.activeElement;
        if (e.key === 'ArrowDown' && cur.nextSibling) { e.preventDefault(); cur.nextSibling.focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); (cur.previousSibling || input).focus(); }
        if (e.key === 'Escape') { close(); input.focus(); }
        if (e.key === 'Enter' && cur.tagName === 'LI' && !cur.dataset.bound) { cur.click(); }
    });
    document.addEventListener('click', function (e) { if (!root.contains(e.target)) { close(); } });
})();
</script>
