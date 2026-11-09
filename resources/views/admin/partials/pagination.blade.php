<nav class="wilayah-pagination" aria-label="Navigasi halaman {{ $label }}">
    <span class="text-muted">{{ number_format($paginator->total(), 0, ',', '.') }} {{ $label }} · Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
    <div class="page-actions">
        @if ($paginator->previousPageUrl())<a class="btn btn-secondary" rel="prev" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>@endif
        @if ($paginator->nextPageUrl())<a class="btn btn-secondary" rel="next" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>@endif
    </div>
</nav>
