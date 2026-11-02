<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\EnvironmentBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ENVIRONMENT_BANNER_TEXT puts a strip naming the environment across the top
 * of every layout, so a local or staging site can't be mistaken for the live
 * one. Unset (production), nothing renders.
 */
class EnvironmentBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_layout_shows_the_banner_and_reserves_its_space(): void
    {
        config(['app.environment_banner.text' => 'STAGING - not live data']);

        $responses = [
            'public' => $this->get(route('home')),
            'login' => $this->get(route('login')),
            'error' => $this->get('/no-such-page')->assertNotFound(),
            'admin' => $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))->assertOk(),
        ];

        foreach ($responses as $layout => $response) {
            $html = $response->getContent();

            $this->assertStringContainsString('class="ui-environment-banner"', $html, $layout);
            $this->assertStringContainsString('STAGING - not live data</span>', $html, $layout);
            $this->assertMatchesRegularExpression('/<body class="[^"]*\bhas-environment-banner\b/', $html, $layout);
        }

        $this->assertDoesNotMatchRegularExpression('/<body class="[^"]*\bpt-4\b/', $responses['login']->getContent());
    }

    public function test_nothing_renders_while_the_text_is_unset(): void
    {
        config(['app.environment_banner.text' => '  ']);

        $html = $this->get(route('login'))->getContent();

        $this->assertStringNotContainsString('ui-environment-banner', $html);
        $this->assertStringNotContainsString('has-environment-banner', $html);
        $this->assertMatchesRegularExpression('/<body class="[^"]*\bpt-4\b/', $html);
    }

    public function test_the_text_is_escaped(): void
    {
        config(['app.environment_banner.text' => '<b>STAGING</b>']);

        $this->get(route('login'))
            ->assertSee('&lt;b&gt;STAGING&lt;/b&gt;', false)
            ->assertDontSee('<b>STAGING</b>', false);
    }

    public function test_a_malformed_colour_falls_back_to_the_default(): void
    {
        foreach (['red', '#12345', 'url(x)', '#fff; position: static', ''] as $color) {
            config(['app.environment_banner.color' => $color]);

            $this->assertSame(EnvironmentBanner::DEFAULT_COLOR, EnvironmentBanner::color(), $color);
        }

        config(['app.environment_banner.color' => '#0AF']);

        $this->assertSame('#00aaff', EnvironmentBanner::color());
    }

    public function test_the_text_colour_is_whichever_contrasts_more_with_the_background(): void
    {
        $cases = ['#facc15' => '#000000', '#ffffff' => '#000000', '#dc2626' => '#ffffff', '#1e3a8a' => '#ffffff', '#000000' => '#ffffff'];

        foreach ($cases as $background => $expected) {
            config(['app.environment_banner.color' => $background]);

            $this->assertSame($expected, EnvironmentBanner::contentColor(), $background);
        }
    }
}
