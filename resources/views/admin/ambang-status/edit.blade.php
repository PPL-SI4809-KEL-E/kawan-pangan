@extends('layouts.admin')
@section('title', 'Ambang Status Pangan')
@section('content')
<div class="page-header"><div><h1>Ambang Status Pangan</h1><p class="text-muted">Atur batas hari ketersediaan pangan untuk status Kritis, Waspada, dan Aman.</p></div></div>
<div class="grid grid-3" aria-label="Aturan status yang sedang berlaku">
    <div class="card stat-card"><span class="badge badge-kritis">Kritis</span><span class="stat-value">&lt; {{ $ambang->kritis_hari }} hari</span><span class="field-help">Hari ketersediaan kurang dari batas kritis.</span></div>
    <div class="card stat-card"><span class="badge badge-waspada">Waspada</span><span class="stat-value">{{ $ambang->kritis_hari }}–{{ $ambang->aman_hari }} hari</span><span class="field-help">Termasuk tepat pada kedua batas.</span></div>
    <div class="card stat-card"><span class="badge badge-aman">Aman</span><span class="stat-value">&gt; {{ $ambang->aman_hari }} hari</span><span class="field-help">Hari ketersediaan lebih dari batas aman.</span></div>
</div>
<section class="card wilayah-edit" aria-labelledby="judul-ambang">
    <h2 id="judul-ambang">Ubah ambang</h2>
    <p id="aturan-ambang" class="field-help">Isi bilangan bulat positif. Batas kritis harus lebih kecil daripada batas aman. Status ditentukan dari nilai hari sebelum pembulatan.</p>
    <form method="post" action="{{ route('admin.ambang-status.update') }}">
        @csrf
        @method('PATCH')
        @foreach (['kritis_hari' => 'Batas kritis (hari)', 'aman_hari' => 'Batas aman (hari)'] as $field => $label)
            <div class="field @error($field) has-error @enderror">
                <label for="{{ $field }}">{{ $label }} <span class="required">*</span></label>
                <input class="input" type="number" id="{{ $field }}" name="{{ $field }}" required min="1" max="65535" step="1" value="{{ old($field, $ambang->$field) }}" aria-describedby="aturan-ambang {{ $field }}-error" @error($field) aria-invalid="true" @enderror>
                <span id="{{ $field }}-error" class="field-error">@error($field){{ $message }}@enderror</span>
            </div>
        @endforeach
        <button class="btn btn-primary" type="submit">Simpan ambang status</button>
    </form>
</section>
@endsection
