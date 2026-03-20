@if ($paginator->hasPages())
  <style>
    .admin-pg-nav {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .admin-pg-btn {
      min-width: 34px;
      height: 32px;
      border-radius: 8px;
      border: 1px solid #cbd5e1;
      background: #fff;
      color: #334155;
      text-decoration: none;
      font-size: 12px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 10px;
      box-sizing: border-box;
      line-height: 1;
    }

    .admin-pg-btn:hover {
      background: #f1f5f9;
    }

    .admin-pg-btn.active {
      color: #fff;
      border-color: #0f172a;
      background: #0f172a;
    }

    .admin-pg-btn.disabled {
      color: #94a3b8;
      border-color: #e2e8f0;
      background: #f8fafc;
      cursor: not-allowed;
    }
  </style>

  <nav role="navigation" aria-label="Pagination" class="admin-pg-nav">
    @if ($paginator->onFirstPage())
      <span class="admin-pg-btn disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">‹</span>
    @else
      <a class="admin-pg-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">‹</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="admin-pg-btn disabled" aria-disabled="true">{{ $element }}</span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="admin-pg-btn active" aria-current="page">{{ $page }}</span>
          @else
            <a class="admin-pg-btn" href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a class="admin-pg-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">›</a>
    @else
      <span class="admin-pg-btn disabled" aria-disabled="true" aria-label="@lang('pagination.next')">›</span>
    @endif
  </nav>
@endif
