@extends('layouts.app')
@section('title', 'Upload Membership Roster')

@section('content')
<div class="max-w-3xl mx-auto p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Upload Membership Roster</h1>
        <div class="flex flex-wrap gap-2 items-center">
            <a href="/admin" class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">← Dashboard</a>
            <form method="POST" action="/logout">@csrf
                <button class="bg-gray-200 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-300 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-sm text-gray-600 mb-1">Upload an Excel (<b>.xlsx</b>) or <b>.csv</b> file with two columns:</p>
        <p class="text-sm text-gray-600 mb-4"><b>name</b> and <b>membership_id</b>. A header row is optional. Existing IDs are skipped (never overwritten).</p>

        <div class="flex flex-wrap items-center gap-3">
            <input id="memberFile" type="file" accept=".xlsx,.csv" class="text-sm">
            <button id="uploadMembersBtn" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 disabled:opacity-50">Upload</button>
        </div>
        <div id="uploadMsg" class="text-sm mt-3 min-h-5"></div>

        <div class="mt-6 border-t pt-4 text-xs text-gray-400">
            <p class="font-medium text-gray-500 mb-1">Example</p>
            <pre class="bg-gray-50 border rounded p-2 text-gray-600">name,membership_id
Ahmad bin Ali,A001
Siti Nurhaliza,A002</pre>
        </div>
    </div>

    <!-- Manage roster: search, add one, edit a wrong name/ID, delete -->
    <div class="bg-white p-6 rounded-lg shadow mt-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-4">
            <h2 class="text-lg font-semibold">Current Roster <span id="rosterCount" class="text-sm font-normal text-gray-500"></span></h2>
            <input id="rosterSearch" type="text" placeholder="Search name or ID…" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-64">
        </div>

        <!-- Add a single member -->
        <div class="flex flex-wrap items-end gap-2 mb-4 border-b pb-4">
            <div class="grow sm:grow-0">
                <label class="block text-xs text-gray-500 mb-1">Name</label>
                <input id="newName" type="text" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-56">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Membership ID</label>
                <input id="newId" type="text" class="border rounded-lg px-3 py-2 text-sm w-32">
            </div>
            <button id="addMemberBtn" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm">Add member</button>
            <span id="addMsg" class="text-sm self-center"></span>
        </div>

        <div id="rosterList" class="divide-y border rounded-lg max-h-[28rem] overflow-y-auto"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('uploadMembersBtn').addEventListener('click', async () => {
        const input = document.getElementById('memberFile');
        const msg = document.getElementById('uploadMsg');
        const btn = document.getElementById('uploadMembersBtn');
        const file = input.files[0];
        if (!file) { msg.textContent = 'Choose a .xlsx or .csv file first.'; msg.className = 'text-sm mt-3 text-red-600'; return; }

        const form = new FormData();
        form.append('file', file);
        btn.disabled = true; btn.textContent = 'Uploading…';
        msg.textContent = 'Uploading…'; msg.className = 'text-sm mt-3 text-gray-500';
        try {
            const res = await fetch('/admin/memberships/upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: form,
            });
            if (res.status === 401 || res.status === 419) { showSessionExpired(); return; }
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Upload failed.');
            const { added, skipped, errors } = data.data;
            let text = `${added} added, ${skipped} skipped`;
            if (errors.length) text += `, ${errors.length} error${errors.length > 1 ? 's' : ''} — ${errors.slice(0, 5).join('; ')}${errors.length > 5 ? '…' : ''}`;
            msg.textContent = text;
            msg.className = 'text-sm mt-3 ' + (errors.length ? 'text-amber-600' : 'text-green-600');
            input.value = '';
            loadRoster();
        } catch (e) {
            msg.textContent = e.message || 'Upload failed.'; msg.className = 'text-sm mt-3 text-red-600';
        } finally {
            btn.disabled = false; btn.textContent = 'Upload';
        }
    });

    // ---- Manage roster (list / add / edit / delete) ----
    let rosterData = [];
    const editing = new Set();

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function loadRoster() {
        const q = document.getElementById('rosterSearch').value.trim();
        const res = await window.apiFetch('/admin/memberships' + (q ? '?q=' + encodeURIComponent(q) : ''));
        const data = await res.json();
        rosterData = data.data || [];
        renderRoster();
    }

    function renderRoster() {
        const box = document.getElementById('rosterList');
        document.getElementById('rosterCount').textContent = rosterData.length ? `(${rosterData.length})` : '';
        if (!rosterData.length) {
            box.innerHTML = '<div class="p-4 text-sm text-gray-500">No members found. Upload a file or add one above.</div>';
            return;
        }
        box.innerHTML = rosterData.map(m => {
            if (editing.has(m.id)) {
                return `<div class="p-3 flex flex-wrap items-center gap-2" data-id="${m.id}">
                    <input class="edit-name border rounded px-2 py-1 text-sm flex-1 min-w-[8rem]" value="${esc(m.name)}">
                    <input class="edit-id border rounded px-2 py-1 text-sm w-28" value="${esc(m.member_id)}">
                    <button class="save-btn bg-green-600 text-white text-xs px-3 py-1 rounded hover:bg-green-700">Save</button>
                    <button class="cancel-btn bg-gray-200 text-xs px-3 py-1 rounded hover:bg-gray-300">Cancel</button>
                    <span class="row-msg text-xs text-red-600"></span>
                </div>`;
            }
            return `<div class="p-3 flex items-center gap-2" data-id="${m.id}">
                <span class="flex-1 truncate"><span class="font-medium">${esc(m.name)}</span> <span class="text-gray-400 text-sm">${esc(m.member_id)}</span></span>
                <button class="editrow-btn text-indigo-600 text-xs px-2 py-1 hover:underline">Edit</button>
                <button class="delrow-btn text-red-600 text-xs px-2 py-1 hover:underline">Delete</button>
            </div>`;
        }).join('');
    }

    document.getElementById('rosterList').addEventListener('click', async (e) => {
        const row = e.target.closest('[data-id]');
        if (!row) return;
        const id = Number(row.dataset.id);

        if (e.target.classList.contains('editrow-btn')) {
            editing.add(id); renderRoster();
        } else if (e.target.classList.contains('cancel-btn')) {
            editing.delete(id); renderRoster();
        } else if (e.target.classList.contains('delrow-btn')) {
            if (!confirm('Remove this member from the roster?')) return;
            const res = await window.apiFetch('/admin/memberships/' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.status === 'success') loadRoster(); else alert(data.message || 'Could not remove.');
        } else if (e.target.classList.contains('save-btn')) {
            const name = row.querySelector('.edit-name').value.trim();
            const memberId = row.querySelector('.edit-id').value.trim();
            const rowMsg = row.querySelector('.row-msg');
            if (!name || !memberId) { rowMsg.textContent = 'Name and ID are required.'; return; }
            const res = await window.apiFetch('/admin/memberships/' + id, {
                method: 'POST', body: JSON.stringify({ name, member_id: memberId }),
            });
            const data = await res.json();
            if (data.status === 'success') { editing.delete(id); loadRoster(); }
            else rowMsg.textContent = data.message || 'Could not save.';
        }
    });

    document.getElementById('addMemberBtn').addEventListener('click', async () => {
        const name = document.getElementById('newName').value.trim();
        const memberId = document.getElementById('newId').value.trim();
        const msg = document.getElementById('addMsg');
        if (!name || !memberId) { msg.textContent = 'Enter a name and ID.'; msg.className = 'text-sm self-center text-red-600'; return; }
        const res = await window.apiFetch('/admin/memberships', {
            method: 'POST', body: JSON.stringify({ name, member_id: memberId }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('newName').value = '';
            document.getElementById('newId').value = '';
            msg.textContent = 'Added.'; msg.className = 'text-sm self-center text-green-600';
            loadRoster();
        } else {
            msg.textContent = data.message || 'Could not add.'; msg.className = 'text-sm self-center text-red-600';
        }
    });

    let searchTimer;
    document.getElementById('rosterSearch').addEventListener('input', () => {
        clearTimeout(searchTimer); searchTimer = setTimeout(loadRoster, 250);
    });

    loadRoster();
</script>
@endpush
