<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\AnalyticsRefresh;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsTargetsRequest;
use App\Models\AnalyticsTarget;
use App\Models\Certificate;
use App\Services\CertificateGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Regional Director screen for setting annual per-program targets, which the
 * dashboard's "Pace" chapter compares actuals against.
 */
class AnalyticsTargetsController extends Controller
{
    public function __construct(private readonly CertificateGenerationService $generator) {}

    public function edit(Request $request): View
    {
        $year = $this->year($request->query('year'));

        // Optional non-destructive prefill: ?copy_from=2026 fills the inputs
        // from that year's targets but saves nothing until Save is pressed.
        $copyFrom = $request->query('copy_from') !== null
            ? $this->year($request->query('copy_from'))
            : null;

        $existing = AnalyticsTarget::query()->forYear($year)->get()->keyBy('program_code');
        $source = $copyFrom !== null && $copyFrom !== $year
            ? AnalyticsTarget::query()->forYear($copyFrom)->get()->keyBy('program_code')
            : $existing;

        // The source year's programs have to join the list too, or a prefill
        // would silently drop the very rows it exists to carry over.
        $rows = $this->programs($year, $copyFrom)->map(fn (string $program) => [
            'program_code' => $program,
            'target_certificates' => $source[$program]->target_certificates ?? null,
            'target_people' => $source[$program]->target_people ?? null,
            'notes' => $source[$program]->notes ?? null,
            'is_canonical' => in_array($program, $this->canonicalPrograms(), true),
        ])->values();

        $saved = $existing->first(fn (AnalyticsTarget $t) => $t->updated_at !== null);

        return view('admin.analytics.targets.edit', [
            'year' => $year,
            'copyFrom' => $copyFrom,
            'years' => $this->selectableYears(),
            'rows' => $rows,
            'lastUpdated' => $existing->max('updated_at'),
            'lastUpdatedBy' => $saved?->updatedBy?->name,
            'hasAnyTargets' => $existing->isNotEmpty(),
        ]);
    }

    public function update(AnalyticsTargetsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $year = (int) $data['period_year'];
        $userId = (int) $request->user()->id;

        $seen = [];

        foreach ($data['rows'] as $row) {
            $program = $row['program_code'];

            // A duplicated program in one submission would otherwise have its
            // last occurrence silently win.
            if (isset($seen[$program])) {
                continue;
            }
            $seen[$program] = true;

            $certificates = $row['target_certificates'] ?? null;
            $people = $row['target_people'] ?? null;
            $notes = $row['notes'] ?? null;

            $scope = [
                'program_code' => $program,
                'period_year' => $year,
                'province_code' => AnalyticsTarget::SCOPE_REGION,
            ];

            // An all-blank row means "no target for this program": delete any
            // existing row rather than storing a row that means nothing.
            if ($certificates === null && $people === null && ($notes === null || $notes === '')) {
                AnalyticsTarget::query()->where($scope)->delete();

                continue;
            }

            AnalyticsTarget::updateOrCreate($scope, [
                'target_certificates' => $certificates,
                'target_people' => $people,
                'notes' => $notes,
                'updated_by' => $userId,
            ]);
        }

        // The dashboard payload embeds targets, and it is cached per filter set
        // keyed by this version. Without a bump, a freshly saved target would
        // not appear until the nightly refresh.
        $this->bumpAnalyticsVersion();

        return redirect()
            ->route('admin.analytics.targets.edit', ['year' => $year])
            ->with('status', "Targets for {$year} saved.");
    }

    /**
     * The program list is the union of several sources, and each one matters:
     * the canonical DOST programs, any free-text program already used on a
     * certificate (the create form rewrites "Others" to free text), any
     * program that already has a target row this year, and — when prefilling —
     * the programs targeted in the year being copied from. Without the third,
     * a target set for a program with no certificates yet would vanish from
     * its own form, and setting targets before the first certificate exists is
     * the normal case, not the exception.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function programs(int $year, ?int $alsoFromYear = null): \Illuminate\Support\Collection
    {
        $used = Certificate::query()
            ->whereNotNull('dost_program')
            ->where('dost_program', '!=', '')
            ->distinct()
            ->orderBy('dost_program')
            ->pluck('dost_program');

        $years = array_values(array_unique(array_filter(
            [$year, $alsoFromYear],
            fn (?int $y) => $y !== null,
        )));

        $targeted = AnalyticsTarget::query()
            ->whereIn('period_year', $years)
            ->where('province_code', AnalyticsTarget::SCOPE_REGION)
            ->pluck('program_code');

        return collect($this->canonicalPrograms())
            ->concat($used)
            ->concat($targeted)
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Canonical programs, minus the "Others" sentinel — it is a UI affordance
     * on the certificate form (rewritten to free text on save), never a
     * program a target can be set against.
     *
     * @return array<int, string>
     */
    private function canonicalPrograms(): array
    {
        return array_values(array_filter(
            $this->generator->dostPrograms(),
            fn (string $p) => $p !== 'Others',
        ));
    }

    /** @return array<int, int> */
    private function selectableYears(): array
    {
        $current = (int) now()->year;

        // Two years back for revision, two forward for planning.
        return range($current - 2, $current + 2);
    }

    private function year(mixed $value): int
    {
        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }

    private function bumpAnalyticsVersion(): void
    {
        $version = (int) Cache::get(AnalyticsRefresh::VERSION_KEY, 0) + 1;
        Cache::forever(AnalyticsRefresh::VERSION_KEY, $version);
    }
}
