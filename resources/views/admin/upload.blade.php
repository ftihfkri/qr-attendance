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
        } catch (e) {
            msg.textContent = e.message || 'Upload failed.'; msg.className = 'text-sm mt-3 text-red-600';
        } finally {
            btn.disabled = false; btn.textContent = 'Upload';
        }
    });
</script>
@endpush
