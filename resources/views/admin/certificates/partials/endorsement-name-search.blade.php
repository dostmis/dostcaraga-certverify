{{-- Name search on the endorsement details page; keeps the "Needs attention" filter. --}}
<form class="dl-search" method="GET" action="{{ route('admin.certs.endorsements.show', ['id' => $endorsement->id]) }}" role="search">
  @if ($showProblemsOnly)
    <input type="hidden" name="problems" value="1">
  @endif
  <input type="search" name="q" value="{{ $search }}" placeholder="Search participant name" aria-label="Search participant name">
  <button type="submit" class="dl-btn">Search</button>
  @if ($search !== '')
    <a class="dl-btn" href="{{ $listRoute(['q' => null]) }}">Clear</a>
  @endif
</form>
