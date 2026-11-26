<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use PHPUnit\Framework\TestCase;

// The search is asked from the dashboard, its results drawn there while typing, not from an entry of its own in the column
class DashboardSearchTest extends TestCase
{
    // The dashboard and the results page draw the same field, sent to the search page
    public function testDashboardHoldsTheSearchField(): void
    {
        $field = $this->template('_search.html.twig');

        $this->assertStringContainsString("action=\"{{ path('pilot_search') }}\"", $field);
        $this->assertStringContainsString('name="q"', $field);
        $this->assertStringContainsString("'@c975LPilot/pilot/_search.html.twig' with {words: '', focus: false}", $this->template('dashboard.html.twig'));
        $this->assertStringContainsString("<h2 class=\"pilot-title\">{{ pilot_icon('magnifying-glass', 'pilot-icon-before') }}{{ 'label.search'|trans({}, 'pilot') }}</h2>", $this->template('dashboard.html.twig'));
        $this->assertStringContainsString("'@c975LPilot/pilot/_search.html.twig'", $this->template('search.html.twig'));
    }

    // The results are drawn under the field while typing, by a controller the bundle's script registers, the dashboard left as it is
    public function testResultsDrawnWhileTyping(): void
    {
        $field = $this->template('_search.html.twig');

        $this->assertStringContainsString('data-controller="pilot-search"', $field);
        $this->assertStringContainsString('data-action="input->pilot-search#type"', $field);
        $this->assertStringContainsString('data-pilot-search-target="results"', $field);
        $this->assertStringContainsString("'@c975LPilot/pilot/_search_results.html.twig'", $field);
        $this->assertStringNotContainsString('_doc.html.twig', $this->template('_search_results.html.twig'));
        $this->assertStringContainsString('app.register("pilot-search", PilotSearchController);', (string) file_get_contents(__DIR__ . '/../assets/controllers-admin.js'));
        $this->assertStringContainsString('X-Requested-With', (string) file_get_contents(__DIR__ . '/../assets/js/pilot-search.js'));
    }

    // Donovan's panel is the back office's own script on this shell's markup, drawn once the back office's AI key is filled in, its cards opened in their space rather than in a new tab
    public function testDonovanIsTheBackOfficePanel(): void
    {
        $layout = $this->template('_layout.html.twig');

        $this->assertStringContainsString('{% if pilot_ask_enabled() %}', $layout);
        $this->assertStringContainsString('data-ai-assistant-panel-open', $layout);
        $this->assertStringContainsString('<aside class="ai-assistant-panel__body" id="pilot-ask-panel"', $layout);
        $this->assertStringContainsString('data-controller="aiAssistant"', $layout);
        $this->assertStringContainsString("data-ai-assistant-ask-url-value=\"{{ path('pilot_ask') }}\"", $layout);
        $this->assertStringContainsString('data-ai-assistant-link-target-value="_self"', $layout);
        $this->assertStringContainsString("{{ pilot_icon('robot') }}{{ ai_assistant_name() }}<kbd class=\"pilot-kbd\" aria-hidden=\"true\">Ctrl K</kbd>", $layout);
        $this->assertStringContainsString('import AiAssistantController from "@c975l/ui-bundle/ai-assistant.js";', (string) file_get_contents(__DIR__ . '/../assets/controllers-admin.js'));
        $this->assertFileDoesNotExist(__DIR__ . '/../assets/js/pilot-ask.js');
    }

    // The column links neither the search nor the dashboard, the brand leading there and current on its results
    public function testColumnHasNoSearchNorDashboardEntry(): void
    {
        $layout = $this->template('_layout.html.twig');

        $this->assertStringNotContainsString("path('pilot_search')", $layout);
        $this->assertSame(1, substr_count($layout, "path('pilot_dashboard')"));
        $this->assertStringContainsString("in ['pilot_dashboard', 'pilot_search', 'pilot_ask'] ? ' aria-current=\"page\"'", $layout);
    }

    // The text of one of the piloting templates
    private function template(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../templates/pilot/' . $name);
    }
}
