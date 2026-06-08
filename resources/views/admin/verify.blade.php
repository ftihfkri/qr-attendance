@extends('layouts.app')
@section('title', 'Verify Submissions')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Verify Submissions</h1>
        <div class="flex gap-2 items-center">
            <a href="/admin" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow mb-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input id="search" type="text" placeholder="Search by name or Koperasi ID…"
                class="flex-grow min-w-[240px] p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            <button id="refreshBtn" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Refresh</button>
            <span id="count" class="text-sm text-gray-500"></span>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-300 text-sm">
                <thead class="bg-gray-100 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 border text-left">#</th>
                        <th class="px-3 py-2 border text-left">Full Name</th>
                        <th class="px-3 py-2 border text-left">Koperasi ID</th>
                        <th class="px-3 py-2 border text-left">Phone</th>
                        <th class="px-3 py-2 border text-left">Email</th>
                        <th class="px-3 py-2 border text-left">Time</th>
                        <th class="px-3 py-2 border text-left">Method</th>
                    </tr>
                </thead>
                <tbody id="listBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let all = [];
    function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

    function render(rows) {
        const body = document.getElementById('listBody');
        document.getElementById('count').textContent = `${rows.length} of ${all.length}`;
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 border text-center text-gray-500">No matching submissions.</td></tr>';
            return;
        }
        body.innerHTML = rows.map((r, i) => `<tr>
            <td class="px-3 py-2 border">${i + 1}</td>
            <td class="px-3 py-2 border">${esc(r.name)}</td>
            <td class="px-3 py-2 border font-medium">${esc(r.koperasi_id)}</td>
            <td class="px-3 py-2 border">${esc(r.phone_number)}</td>
            <td class="px-3 py-2 border">${esc(r.email)}</td>
            <td class="px-3 py-2 border">${esc(r.time)}</td>
            <td class="px-3 py-2 border">${r.method === 'manual' ? '<span class="text-indigo-600">Manual</span>' : '<span class="text-green-600">Scanned</span>'}</td>
        </tr>`).join('');
    }

    function applyFilter() {
        const q = document.getElementById('search').value.trim().toLowerCase();
        if (!q) return render(all);
        render(all.filter(r =>
            String(r.name ?? '').toLowerCase().includes(q) ||
            String(r.koperasi_id ?? '').toLowerCase().includes(q)
        ));
    }

    async function load() {
        const res = await window.apiFetch('/admin/attendance');
        const { data } = await res.json();
        all = data;
        applyFilter();
    }

    document.getElementById('search').addEventListener('input', applyFilter);
    document.getElementById('refreshBtn').addEventListener('click', load);
    load();
</script>
@endpush
