<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\CounselingSessionFormRequest;
use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Guards the fixes for the page "blink" on every navigation: elements that
 * start hidden must not be visible at first paint (x-cloak, or a server-
 * rendered display:none where the state must survive without JS), the
 * theme script sets color-scheme with the dark class, the fonts each
 * layout paints with are preloaded at the exact URL app.css uses, and the
 * sidebar keeps its scroll position across page loads.
 */
class PageLoadStabilityTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private const COLOR_SCHEME_LINE = "document.documentElement.style.colorScheme = dark ? 'dark' : 'light';";

    public function test_x_cloak_rule_is_defined_in_the_stylesheet(): void
    {
        $this->assertMatchesRegularExpression(
            '/\[x-cloak\]\s*\{\s*display:\s*none\s*!important;?\s*\}/',
            (string) file_get_contents(resource_path('css/app.css'))
        );
    }

    public function test_every_app_layout_page_reserves_the_scrollbar_space(): void
    {
        $psychometrician = $this->psychometrician();
        $counselor = $this->guidanceCounselor();

        $pages = [
            [$psychometrician, route('psychometrician.dashboard')],
            [$psychometrician, route('students.index')],
            [$psychometrician, route('users.index')],
            [$psychometrician, route('questionnaires.index')],
            [$psychometrician, route('profile.edit')],
            [$counselor, route('guidance-counselor.dashboard')],
            [$counselor, route('notifications.index')],
            [$counselor, route('notifications.index', ['archived' => 1])],
        ];

        foreach ($pages as [$user, $url]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('<html lang="en" class="[scrollbar-gutter:stable]">', false);
        }
    }

    public function test_login_guest_and_report_layouts_do_not_reserve_the_scrollbar_space(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('scrollbar-gutter', false);
        $this->get(route('password.request'))->assertOk()->assertDontSee('scrollbar-gutter', false);

        // Report layout (print view; the PDF renders the same view).
        $this->actingAs($this->psychometrician())->get(route('reports.assessment-summary.print'))
            ->assertOk()
            ->assertDontSee('scrollbar-gutter', false);
    }

    public function test_reserved_scrollbar_strip_is_coloured_to_match_the_page_and_the_dialog_backdrop(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // No fixed overlay can paint over the reserved strip, so <html>'s own
        // background is set: the page colour normally, the backdrop's
        // dimmed colour (bg-body/60) while a dialog has locked scrolling.
        $this->assertStringContainsString("background-color: theme('colors.page');", $css);
        $this->assertStringContainsString("background-color: theme('colors.slate.900');", $css);
        $this->assertStringContainsString(':has(> body.overflow-y-hidden)', $css);
        $this->assertStringContainsString("color-mix(in srgb, theme('colors.body') 60%, theme('colors.page'))", $css);
        $this->assertStringContainsString("color-mix(in srgb, theme('colors.body') 60%, theme('colors.slate.900'))", $css);
        $this->assertStringContainsString('<div class="absolute inset-0 bg-body/60"></div>', (string) file_get_contents(resource_path('views/components/modal.blade.php')), 'The strip colour assumes the dialog backdrop is bg-body/60.');
    }

    public function test_mobile_drawer_and_backdrop_are_cloaked(): void
    {
        $response = $this->actingAs($this->psychometrician())->get(route('profile.edit'));

        $response->assertOk()
            ->assertSee('<div x-show="open" x-cloak x-transition.opacity', false)
            ->assertSee('<aside x-show="open" x-cloak', false);
    }

    public function test_every_psychometrician_dashboard_chart_tooltip_is_cloaked(): void
    {
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($this->psychometrician())->get(route('psychometrician.dashboard'));

        // 6 monthly bars + the severity donut's tooltip.
        $this->assertAllChartTooltipsCloaked($response, 7);
    }

    public function test_every_guidance_counselor_dashboard_chart_tooltip_is_cloaked(): void
    {
        FlaggedCase::factory()->endorsement()->create();

        $response = $this->actingAs($this->guidanceCounselor())->get(route('guidance-counselor.dashboard'));

        // 6 monthly bars + the flag type donut's tooltip.
        $this->assertAllChartTooltipsCloaked($response, 7);
    }

    public function test_password_hide_icon_is_cloaked_and_show_icon_is_not(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<svg x-show="show" x-cloak', false)
            ->assertSee('<svg x-show="! show" class=', false);
    }

    public function test_follow_up_date_field_starts_hidden_when_follow_up_is_off(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id, 'follow_up_required' => false]);

        $html = $this->actingAs($counselor)->get(route('counseling-sessions.edit', $session))->assertOk()->getContent();

        $this->assertFollowUpFieldHidden($html, true);
    }

    public function test_follow_up_date_field_starts_visible_when_follow_up_is_on(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_required' => true,
            'follow_up_date' => now()->addWeek(),
        ]);

        $html = $this->actingAs($counselor)->get(route('counseling-sessions.edit', $session))->assertOk()->getContent();

        $this->assertFollowUpFieldHidden($html, false);
    }

    public function test_follow_up_date_field_follows_the_ticked_box_after_a_failed_submit(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id, 'follow_up_required' => false]);

        // Ticked, but no follow-up date: fails validation, and the re-rendered
        // form must show the date field because the old input has it ticked.
        $html = $this->actingAs($counselor)
            ->from(route('counseling-sessions.edit', $session))
            ->followingRedirects()
            ->put(route('counseling-sessions.update', $session), [
                'session_date' => now()->addDay()->format('Y-m-d'),
                'session_time' => '09:00',
                'session_notes' => 'Notes.',
                'session_status' => CounselingSession::STATUS_SCHEDULED,
                'follow_up_required' => '1',
                'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
            ])
            ->assertOk()
            ->assertSee(CounselingSessionFormRequest::FOLLOW_UP_DATE_REQUIRED_MESSAGE, false)
            ->getContent();

        $this->assertFollowUpFieldHidden($html, false);
    }

    public function test_only_enable_override_mode_is_visible_before_alpine_starts(): void
    {
        $this->seedOfficialThresholds();

        $html = $this->actingAs($this->psychometrician())
            ->get(route('settings.classification-thresholds'))
            ->assertOk()
            ->getContent();

        $enable = $this->tagsWith($html, 'button', 'x-show="!overrideMode"');
        $saveAndCancel = $this->tagsWith($html, 'button', 'x-show="overrideMode"');

        $this->assertCount(1, $enable);
        $this->assertStringNotContainsString('display: none', $enable[0]);

        $this->assertCount(2, $saveAndCancel);
        foreach ($saveAndCancel as $tag) {
            $this->assertStringContainsString('style="display: none;"', $tag);
        }
    }

    public function test_app_layout_theme_script_sets_color_scheme_and_sidebar_restores_scroll(): void
    {
        $this->actingAs($this->psychometrician())->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(self::COLOR_SCHEME_LINE, false)
            ->assertSeeInOrder(['id="sidebar-scroll"', "var key = 'normi.sidebarScrollTop';", "window.addEventListener('pagehide'"], false);
    }

    public function test_guest_layout_theme_script_sets_color_scheme(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(self::COLOR_SCHEME_LINE, false);
    }

    public function test_app_layout_preloads_only_the_weights_it_paints_with(): void
    {
        $response = $this->actingAs($this->psychometrician())->get(route('profile.edit'));

        $this->assertFontPreloads($response, preloaded: [400, 500, 600], notPreloaded: [700]);
    }

    public function test_guest_layout_preloads_only_the_weights_it_paints_with(): void
    {
        $this->assertFontPreloads($this->get(route('password.request')), preloaded: [400, 500, 600], notPreloaded: [700]);
    }

    public function test_login_layout_preloads_all_four_weights(): void
    {
        $this->assertFontPreloads($this->get(route('login')), preloaded: [400, 500, 600, 700], notPreloaded: []);
    }

    public function test_preloaded_font_urls_match_the_built_stylesheet(): void
    {
        // A preload is only reused when its URL is exactly the one app.css's
        // @font-face asks for; otherwise every font downloads twice.
        $css = (string) file_get_contents(public_path('build/'.$this->manifestFile('resources/css/app.css')));

        foreach ([400, 500, 600, 700] as $weight) {
            $path = (string) parse_url(Vite::asset("resources/fonts/figtree-{$weight}.woff2"), PHP_URL_PATH);

            $this->assertStringContainsString("url({$path})", $css, "figtree-{$weight} preload URL is not the one app.css uses.");
        }
    }

    private function assertFollowUpFieldHidden(string $html, bool $hidden): void
    {
        $wrapper = $this->tagsWith($html, 'div', 'x-show="followUpRequired"');

        $this->assertCount(1, $wrapper);

        $hidden
            ? $this->assertStringContainsString('style="display: none;"', $wrapper[0])
            : $this->assertStringNotContainsString('display: none', $wrapper[0]);
    }

    /**
     * Every opening `<$tag ...>` whose attributes contain $needle, in any
     * attribute order.
     *
     * @return array<int, string>
     */
    private function tagsWith(string $html, string $tag, string $needle): array
    {
        preg_match_all('/<'.$tag.'\b[^>]*>/s', $html, $matches);

        return array_values(array_filter($matches[0], fn (string $openingTag): bool => str_contains($openingTag, $needle)));
    }

    private function assertAllChartTooltipsCloaked(TestResponse $response, int $expected): void
    {
        $html = $response->assertOk()->getContent();

        $tooltips = substr_count($html, 'class="pointer-events-none fixed z-20 w-max"');
        $cloaked = preg_match_all('/x-show="show"\s+x-cloak\s+x-transition\s+class="pointer-events-none fixed z-20 w-max"/', $html);

        $this->assertSame($expected, $tooltips);
        $this->assertSame($tooltips, $cloaked);
    }

    /**
     * @param  array<int, int>  $preloaded
     * @param  array<int, int>  $notPreloaded
     */
    private function assertFontPreloads(TestResponse $response, array $preloaded, array $notPreloaded): void
    {
        $response->assertOk();

        foreach ($preloaded as $weight) {
            $response->assertSee(
                '<link rel="preload" href="'.Vite::asset("resources/fonts/figtree-{$weight}.woff2").'" as="font" type="font/woff2" crossorigin>',
                false
            );
        }

        foreach ($notPreloaded as $weight) {
            $response->assertDontSee(Vite::asset("resources/fonts/figtree-{$weight}.woff2"), false);
        }
    }

    private function manifestFile(string $entry): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        return $manifest[$entry]['file'];
    }
}
