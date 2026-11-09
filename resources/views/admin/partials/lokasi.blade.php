<div class="field @error('nama') has-error @enderror">
    <label for="nama">Nama {{ $jenis }} <span class="required">*</span></label>
    <input class="input" id="nama" name="nama" maxlength="150" required value="{{ old('nama', $item->nama) }}" @error('nama') aria-invalid="true" @enderror>
</div>
<div class="field @error('alamat') has-error @enderror">
    <label for="alamat">Alamat <span class="required">*</span></label>
    <textarea class="textarea" id="alamat" name="alamat" maxlength="500" required @error('alamat') aria-invalid="true" @enderror>{{ old('alamat', $item->alamat) }}</textarea>
</div>
<p class="field-help" id="koordinat-help">Gunakan derajat desimal dengan titik, contoh latitude -6.9175000 dan longitude 107.6191000. Maksimal 7 angka di belakang titik disimpan.</p>
<div class="grid grid-2">
    @foreach (['latitude' => ['Latitude', -90, 90], 'longitude' => ['Longitude', -180, 180]] as $field => [$label, $min, $max])
        <div class="field @error($field) has-error @enderror">
            <label for="{{ $field }}">{{ $label }} <span class="required">*</span></label>
            <input class="input" type="number" step="any" min="{{ $min }}" max="{{ $max }}" id="{{ $field }}" name="{{ $field }}" required value="{{ old($field, $item->$field) }}" aria-describedby="koordinat-help" @error($field) aria-invalid="true" @enderror>
        </div>
    @endforeach
</div>
