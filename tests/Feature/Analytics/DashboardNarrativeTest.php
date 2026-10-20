<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structural checks on the scrollytelling shell: the six chapters render in
 * narrative order, each is anchored and accent-scoped, and the RD-only
 * affordances appear for the RD alone.
 */
class DashboardNarrativeTest extends TestCase
{
    use RefreshDatabase;

    private const CHAPTERS = ['reach', 'who', 'pace', 'proof', 'delivery', 'integrity'];

    private function staff(string $role, bool $admin = false): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => $admin,
            'approval_status' => 'approved',
        ]);
    }

    public function test_all_six_chapters_render_in_narrative_order(): void
    {
        $html = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        $positions = [];
        foreach (self::CHAPTERS as $chapter) {
            $pos = strpos($html, 'data-chapter="'.$chapter.'"');
            $this->assertNotFalse($pos, "Chapter [{$chapter}] is missing from the dashboard.");
            $positions[$chapter] = $pos;
        }

        $sorted = $positions;
        asort($sorted);
        $this->assertSame(
            self::CHAPTERS,
            array_keys($sorted),
            'Chapters must appear in narrative order, since the page is read by scrolling.'
        );
    }

    public function test_every_chapter_carries_its_accent_scope_and_anchor(): void
    {
        $html = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (self::CHAPTERS as $chapter) {
            // data-ch drives --ch (the accent); id is the deep-link target.
            $this->assertStringContainsString('data-ch="'.$chapter.'"', $html);
            $this->assertStringContainsString('id="'.$chapter.'"', $html);
            $this->assertStringContainsString('[data-ch="'.$chapter.'"]{--ch:', $html);
        }
    }

    public function test_the_targets_link_is_offered_to_the_regional_director_only(): void
    {
        // The URL reaches the page through @js, which json-encodes it and so
        // escapes the slashes — match the encoded form.
        $encoded = trim(json_encode(route('admin.analytics.targets.edit')) ?: '', '"');

        $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee($encoded, escape: false);

        foreach ([User::ROLE_UNIT_SUPERVISOR, User::ROLE_ORGANIZER] as $role) {
            $this->actingAs($this->staff($role))
                ->get(route('admin.dashboard'))->assertOk()
                ->assertDontSee($encoded, escape: false)
                // And the Pace chapter tells them who does set targets.
                ->assertSee('The Regional Director sets these');
        }
    }

    public function test_both_themes_are_offered_and_defined(): void
    {
        $html = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        // The toggle control itself, with both options.
        $this->assertStringContainsString('class="an-theme"', $html);
        $this->assertStringContainsString("setTheme('dark')", $html);
        $this->assertStringContainsString("setTheme('light')", $html);

        // Both themes must actually define their own token block — a toggle
        // that switches to an undefined theme renders an unstyled page.
        $this->assertStringContainsString(':root[data-theme="light"] .an-scope{', $html);
        $this->assertStringContainsString(':root[data-theme="dark"] .an-scope,', $html);

        // Each theme supplies its own accent for every chapter.
        foreach (self::CHAPTERS as $chapter) {
            $this->assertStringContainsString('--ch-'.$chapter.':', $html);
        }
    }

    /**
     * Monospace is reserved for telemetry and system metadata. If it leaks onto
     * headings, KPI values or table text the typographic hierarchy is gone.
     */
    public function test_monospace_is_reserved_for_telemetry(): void
    {
        $html = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['.an-kpi-value{', '.an-ch-head h2{', '.an-table{', '.an-rail-label{'] as $selector) {
            $start = strpos($html, $selector);
            $this->assertNotFalse($start, "Missing selector [{$selector}].");
            $rule = substr($html, $start, (int) strpos($html, '}', $start) - $start);
            $this->assertStringNotContainsString('--font-mono', $rule, "[{$selector}] must not use the mono face.");
        }

        // And it IS used where it belongs.
        $this->assertStringContainsString('font-family:var(--font-mono)', $html);
    }

    public function test_every_new_panel_has_a_mount_point(): void
    {
        $html = $this->actingAs($this->staff(User::ROLE_REGIONAL_DIRECTOR, true))
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        // Panel ids the renderer in dashboard.js draws into. A typo in either
        // place leaves a silently blank panel, which no other test would catch.
        foreach ([
            'map', 'age', 'program', 'trend', 'targets',
            'verify-trend', 'verify-devices',
            'pipeline', 'errors', 'email', 'claim', 'intake',
            'integrity-outcomes', 'integrity-trend',
        ] as $panel) {
            $this->assertStringContainsString('data-panel="'.$panel.'"', $html, "Panel [{$panel}] has no mount point.");
        }
    }
}
