<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Management;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Form\CreditType;
use c975L\PilotBundle\Form\MachineType;
use c975L\PilotBundle\Form\PlanTemplateType;
use c975L\PilotBundle\Form\ProjectType;
use c975L\PilotBundle\Service\CreditRefresher;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class PilotGuidedProjectProviderTest extends TestCase
{
    use PilotTestStubs;

    // Every project sits in the 12000 block GuidedProjectProviderInterface reserves this bundle, in the "pilot" domain, the settings' ones for the admin alone
    public function testTheProjectsSitInTheirBlock(): void
    {
        $projects = $this->guidedProjects();

        $this->assertSame(['pilot-space', 'pilot-card', 'pilot-steps', 'pilot-share', 'pilot-machine', 'pilot-template', 'pilot-run', 'pilot-credits', 'pilot-agent'], array_column($projects, 'slug'));
        $this->assertSame([12010, 12020, 12030, 12040, 12050, 12060, 12070, 12080, 12090], array_column($projects, 'order'));
        foreach ($projects as $project) {
            $this->assertSame('pilot', $project['translation_domain']);
            $this->assertGreaterThanOrEqual(12000, $project['order']);
            $this->assertLessThan(13000, $project['order']);
        }
        $roles = array_column($projects, 'role', 'slug');
        foreach (['pilot-space', 'pilot-machine', 'pilot-template', 'pilot-credits'] as $slug) {
            $this->assertSame('ROLE_ADMIN', $roles[$slug], $slug);
        }
        foreach (['pilot-card', 'pilot-steps', 'pilot-share', 'pilot-run', 'pilot-agent'] as $slug) {
            $this->assertNull($roles[$slug], $slug);
        }
    }

    // Only the opening step sends the user somewhere, every other one pointing at something on the page they were sent to
    public function testOnlyTheOpeningStepCarriesAnUrl(): void
    {
        foreach ($this->guidedProjects() as $project) {
            $steps = $project['steps'];
            $this->assertStringStartsWith('/pilot', $steps[0]['url'], $project['slug']);
            foreach (\array_slice($steps, 1) as $step) {
                $this->assertArrayNotHasKey('url', $step);
                $this->assertNotEmpty($step['highlight'], $step['label']);
            }
        }
    }

    // A highlighted field the settings form no longer has leaves the step pointing at nothing, which no other test would see
    public function testEveryHighlightedFieldIsOnItsForm(): void
    {
        $fields = [
            'project' => $this->fields(new ProjectType()),
            'machine' => $this->fields(new MachineType()),
            'plan_template' => $this->fields(new PlanTemplateType()),
            'credit' => $this->fields(new CreditType($this->createStub(CreditRefresher::class), $this->createStub(VaultEncryptor::class))),
        ];
        $highlighted = 0;
        foreach ($this->guidedProjects() as $project) {
            foreach ($project['steps'] as $step) {
                if (1 !== preg_match('/^#(project|machine|plan_template|credit)_([a-zA-Z]+)$/', $step['highlight'] ?? '', $matches)) {
                    continue;
                }
                $this->assertContains($matches[2], $fields[$matches[1]], $step['label']);
                ++$highlighted;
            }
        }
        $this->assertSame(14, $highlighted);
    }

    // Every other highlight names a class or an attribute the bundle's templates still carry, a step pointing at nothing otherwise
    public function testEveryHighlightIsInTheTemplates(): void
    {
        $templates = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../templates', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $templates .= file_get_contents((string) $file);
        }
        foreach ($this->guidedProjects() as $project) {
            foreach (\array_slice($project['steps'], 1) as $step) {
                preg_match_all('/\.([a-z][a-z-]+)|\[([a-z-]+)="([^"]+)"\]|\[([a-z-]+)\$="([^"]+)"\]/', $step['highlight'], $matches, \PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if ('' !== $match[1]) {
                        $this->assertSame(1, preg_match('/(?<![\w-])' . preg_quote($match[1], '/') . '(?![\w-])/', $templates), $step['label'] . ': ' . $match[1]);
                    } elseif ('' !== ($match[2] ?? '')) {
                        $this->assertTrue(str_contains($templates, $match[2] . '="' . $match[3] . '"'), $step['label'] . ': ' . $match[0]);
                    }
                }
            }
        }
    }

    // Every word a project and its steps need is translated in the three locales, the narration in its own domain
    public function testEveryKeyIsTranslated(): void
    {
        foreach (['fr', 'en', 'es'] as $locale) {
            $words = $this->sources('pilot', $locale);
            $narrations = $this->sources('pilot_narration', $locale);
            foreach ($this->guidedProjects() as $project) {
                $this->assertContains($project['label'], $words, $locale);
                $this->assertContains($project['description'], $words, $locale);
                foreach ($project['steps'] as $step) {
                    $this->assertContains($step['label'], $words, $locale);
                    $this->assertContains($step['description'], $words, $locale);
                    $this->assertContains($step['narration'], $narrations, $locale);
                }
            }
        }
    }

    // Every word a step needs is named after its project and its key
    public function testTheKeysFollowTheProject(): void
    {
        $step = $this->guidedProjects()[3]['steps'][3];

        $this->assertSame('label.guided_step_pilot_share_link', $step['label']);
        $this->assertSame('description.guided_step_pilot_share_link', $step['description']);
        $this->assertSame('narration.guided_step_pilot_share_link', $step['narration']);
        $this->assertSame('.pilot-share', $step['highlight']);
    }

    // The keys a translation file carries
    /** @return list<string> */
    private function sources(string $domain, string $locale): array
    {
        preg_match_all('#<source>([^<]+)</source>#', (string) file_get_contents(__DIR__ . '/../../translations/' . $domain . '.' . $locale . '.xlf'), $matches);

        return $matches[1];
    }

    // The names of the fields a settings form adds, read off its builder without the entity types' Doctrine
    /** @return list<string> */
    private function fields(AbstractType $type): array
    {
        $names = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name) use (&$names, $builder): FormBuilderInterface {
            $names[] = $name;

            return $builder;
        });
        $type->buildForm($builder, []);

        return $names;
    }
}
