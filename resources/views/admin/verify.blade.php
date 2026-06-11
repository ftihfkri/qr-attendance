@extends('layouts.app')
@section('title', 'Attendance Status')

@section('content')
<div class="max-w-5xl mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Attendance Status</h1>
        <div class="flex gap-2 items-center">
            <a href="/admin" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
            <a href="/admin/upload" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm">Upload Roster</a>
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow mb-4">
        <div class="flex flex-wrap gap-3 items-center">
            <input id="search" type="text" placeholder="Search by name or Nombor Ahli / Anggota…"
                class="flex-grow min-w-[240px] p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            <div class="flex gap-1">
                <button data-filter="all" class="filter-btn px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white">All</button>
                <button data-filter="attended" class="filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300">Attended</button>
                <button data-filter="pending" class="filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300">Not yet</button>
            </div>
            <button id="refreshBtn" class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Refresh</button>
            <span id="count" class="text-sm text-gray-500"></span>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow">
        <div class="overflow-x-auto max-h-[36rem] overflow-y-auto">
            <table class="min-w-full border border-gray-300 text-sm">
                <thead class="bg-gray-100 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 border text-left">#</th>
                        <th class="px-3 py-2 border text-left">Full Name</th>
                        <th class="px-3 py-2 border text-left">Nombor Ahli / Anggota</th>
                        <th class="px-3 py-2 border text-left">Status</th>
                        <th class="px-3 py-2 border text-left">Time</th>
                        <th class="px-3 py-2 border text-left">Method</th>
                        <th class="px-3 py-2 border text-left">Action</th>
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
    let statusFilter = 'all';
    function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

    function render(rows) {
        const body = document.getElementById('listBody');
        const attended = all.filter(r => r.submitted).length;
        document.getElementById('count').textContent = `${attended} attended of ${all.length} · showing ${rows.length}`;
        if (!all.length) {
            body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 border text-center text-gray-500">No roster yet — upload one to see attendance status.</td></tr>';
            return;
        }
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 border text-center text-gray-500">No matching members.</td></tr>';
            return;
        }
        body.innerHTML = rows.map((r, i) => `<tr>
            <td class="px-3 py-2 border">${i + 1}</td>
            <td class="px-3 py-2 border">${esc(r.name)}</td>
            <td class="px-3 py-2 border font-medium">${esc(r.member_id)}</td>
            <td class="px-3 py-2 border">${r.submitted
                ? '<span class="text-green-600 font-medium">✓ Attended</span>'
                : '<span class="text-gray-400">Not yet</span>'}</td>
            <td class="px-3 py-2 border">${esc(r.time) || '—'}</td>
            <td class="px-3 py-2 border">${r.submitted ? (r.method === 'manual' ? '<span class="text-indigo-600">Manual</span>' : '<span class="text-green-600">Scanned</span>') : '—'}</td>
            <td class="px-3 py-2 border">${r.submitted
                ? `<button class="mark-btn text-red-600 hover:underline" data-id="${esc(r.member_id)}" data-attend="0" data-name="${esc(r.name)}">Mark not attended</button>`
                : `<button class="mark-btn text-green-700 hover:underline" data-id="${esc(r.member_id)}" data-attend="1" data-name="${esc(r.name)}">Mark attended</button>`}</td>
        </tr>`).join('');
        body.querySelectorAll('.mark-btn').forEach(b =>
            b.addEventListener('click', () => mark(b.dataset.id, b.dataset.attend === '1', b.dataset.name)));
    }

    function applyFilter() {
        const q = document.getElementById('search').value.trim().toLowerCase();
        let rows = all;
        if (statusFilter === 'attended') rows = rows.filter(r => r.submitted);
        else if (statusFilter === 'pending') rows = rows.filter(r => !r.submitted);
        if (q) rows = rows.filter(r =>
            String(r.name ?? '').toLowerCase().includes(q) ||
            String(r.member_id ?? '').toLowerCase().includes(q));
        render(rows);
    }

    async function mark(memberId, attend, name) {
        if (!attend && !confirm(`Mark "${name}" as NOT attended? This removes their check-in.`)) return;
        const res = await window.apiFetch('/admin/roster/mark', {
            method: 'POST',
            body: JSON.stringify({ member_id: memberId, attend }),
        });
        const data = await res.json();
        if (!res.ok) { alert(data.message || 'Failed.'); return; }
        await load();
    }

    async function load() {
        const res = await window.apiFetch('/admin/roster');
        const { data } = await res.json();
        all = data;
        applyFilter();
    }

    document.getElementById('search').addEventListener('input', applyFilter);
    document.getElementById('refreshBtn').addEventListener('click', load);
    document.querySelectorAll('.filter-btn').forEach(b => b.addEventListener('click', () => {
        statusFilter = b.dataset.filter;
        document.querySelectorAll('.filter-btn').forEach(x => x.className = 'filter-btn px-3 py-2 rounded-lg text-sm bg-gray-200 hover:bg-gray-300');
        b.className = 'filter-btn px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white';
        applyFilter();
    }));
    load();
</script>
@endpush
