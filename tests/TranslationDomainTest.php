<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use PHPUnit\Framework\TestCase;

// Every key this bundle names is one its catalogue ships, in the three locales - the fields UiBundle's traits add included, whose labels are looked up in this bundle's domain without any of its files spelling them out
class TranslationDomainTest extends TestCase
{
    use PilotTestStubs;

    private const array LOCALES = ['en', 'fr', 'es'];

    // "payment" is this bundle's own dependency and stays legitimate; the other satellites may simply not be installed
    private const array FOREIGN_DOMAINS = ['shop', 'book', 'gallery', 'social', 'crowdfunding', 'site'];

    // Nothing here may name another satellite's catalogue
    public function testNoFileSpeaksAnotherBundleDomain(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            foreach (self::FOREIGN_DOMAINS as $domain) {
                $this->assertDoesNotMatchRegularExpression(
                    sprintf("/trans_default_domain\\s+'%s'|,\\s*'%s'\\s*\\)|'translation_domain'\\s*=>\\s*'%s'/", $domain, $domain, $domain),
                    $contents,
                    sprintf('%s names the "%s" domain, whose catalogue this bundle does not ship', basename($file), $domain)
                );
            }
        }
    }

    // A key named in the code and absent from the catalogue renders as itself - "label.anchor" printed as such in the block's form
    public function testEveryKeyTheCodeNamesIsShipped(): void
    {
        $used = $this->usedKeys();
        $this->assertNotEmpty($used, 'No key was read from "src/", "templates/" or "config/", so this test checked nothing at all.');

        foreach (self::LOCALES as $locale) {
            $catalogue = $this->catalogue(__DIR__ . '/../translations/pilot.' . $locale . '.xlf');

            foreach ($used as $key => $file) {
                $this->assertArrayHasKey($key, $catalogue, sprintf('"%s" names "%s", which "pilot.%s.xlf" does not ship.', $file, $key, $locale));
            }
        }
    }

    // The other way round: a key nobody names any more is dead weight in a catalogue written by hand
    public function testNoKeyIsShippedWithoutBeingNamed(): void
    {
        $used = $this->usedKeys();

        foreach (array_keys($this->catalogue(__DIR__ . '/../translations/pilot.en.xlf')) as $key) {
            $this->assertArrayHasKey($key, $used, sprintf('"pilot.en.xlf" ships "%s", which nothing in "src/", "templates/" or "config/" names any more.', $key));
        }
    }

    // The block borrows PaymentBundle's own word for what the basket does ("added!"): a key renamed there prints raw here, so it is read from the installed PaymentBundle
    public function testEveryKeyBorrowedFromPaymentIsShipped(): void
    {
        $directory = \dirname(__DIR__) . '/vendor/c975l/payment-bundle/translations';
        if (!is_dir($directory)) {
            $this->markTestSkipped('PaymentBundle is not installed.');
        }

        $borrowed = [];
        foreach ($this->sourceFiles() as $file) {
            preg_match_all("/['\"]([a-zA-Z0-9_.]+)['\"]\\s*\\|\\s*trans\\(\\s*\\{[^}]*\\}\\s*,\\s*['\"]payment['\"]/", (string) file_get_contents($file), $matches);
            $borrowed = array_merge($borrowed, $matches[1]);
        }
        $this->assertNotEmpty($borrowed, 'No key borrowed from "payment" was found, so this test checked nothing at all.');

        foreach (self::LOCALES as $locale) {
            $catalogue = $this->catalogue($directory . '/payment.' . $locale . '.xlf');
            foreach (array_unique($borrowed) as $key) {
                $this->assertArrayHasKey($key, $catalogue, sprintf('"%s" is borrowed from PaymentBundle, whose "payment.%s.xlf" does not ship it.', $key, $locale));
            }
        }
    }

    // The narrations live in "pilot_narration", spoken and never drawn: every step's is shipped there in the three locales, and nothing else
    public function testEveryNarrationIsShipped(): void
    {
        $narrations = $this->guidedProjectKeys('narration');
        $this->assertNotEmpty($narrations);
        foreach (self::LOCALES as $locale) {
            $catalogue = array_keys($this->catalogue(__DIR__ . '/../translations/pilot_narration.' . $locale . '.xlf'));
            sort($catalogue);
            $this->assertSame($narrations, $catalogue, sprintf('"pilot_narration.%s.xlf" does not ship exactly the steps\' narrations.', $locale));
        }
    }

    // The keys the guided projects name under one field ("label", "description" or "narration"), the projects' own and their steps', Donovan on and off, sorted
    /** @return list<string> */
    private function guidedProjectKeys(string $field): array
    {
        $keys = [];
        foreach ([...$this->guidedProjects(), ...$this->guidedProjects(false)] as $project) {
            $keys = array_merge($keys, array_filter([$project[$field] ?? null]), array_column($project['steps'], $field));
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    // Every key this bundle resolves in its own domain, keyed by the file naming it
    /** @return array<string, string> */
    private function usedKeys(): array
    {
        $used = [];

        foreach ($this->sourceFiles() as $file) {
            foreach ($this->keysNamedIn($file, (string) file_get_contents($file)) as $key) {
                $used[$key] = basename($file);
            }
        }

        return $used;
    }

    // The ways a key is named, a file being read by whichever of them applies to it
    /** @return list<string> */
    private function keysNamedIn(string $file, string $contents): array
    {
        $keys = array_merge($this->twigKeys($contents), $this->phpKeys($contents));

        // A "ui.block" tag names its label, description and category as plain attributes
        if (str_ends_with($file, '.yaml')) {
            $keys = array_merge($keys, $this->yamlKeys($contents));
        }

        // The guided projects build their keys from each project and step, read off what the provider returns rather than its source
        if (str_ends_with($file, 'PilotGuidedProjectProvider.php')) {
            return array_merge($keys, $this->guidedProjectKeys('label'), $this->guidedProjectKeys('description'));
        }

        // A form type and the menu provider name their labels as plain array values, resolved in the domain the class itself names
        if (str_contains($contents, "'translation_domain' => 'pilot'")) {
            $keys = array_merge($keys, $this->declaredLabelKeys($contents), $this->traitLabelKeys($contents));
        }

        // The demo dataset names its texts as the values of its rows, said in the site's locale through the pilot domain
        if (str_contains($contents, 'implements DemoFixtureProviderInterface')) {
            preg_match_all("/'(label\\.demo_[a-z0-9_]+)'/", $contents, $demo);
            $keys = array_merge($keys, $demo[1]);
        }

        // A credit reader names the labels of its services as the values of providers(), shown in the pilot domain by CreditType
        if (str_contains($contents, 'implements CreditReaderInterface')) {
            preg_match_all("/=> '(label\\.provider_[a-z0-9_]+)'/", $contents, $providers);
            $keys = array_merge($keys, $providers[1]);
        }

        return $keys;
    }

    // The fields a form type opts into rather than writes: UiBundle's traits add them, and their labels are looked up in the domain the type names
    /** @return list<string> */
    private function traitLabelKeys(string $contents): array
    {
        $traits = [
            'HasAnchorFieldTrait' => ['label.anchor', 'label.anchor_help'],
            'HasBackgroundFieldTrait' => [
                'label.section_background',
                'label.section_background_help',
                'label.section_background_muted',
                'label.section_background_primary',
                'label.section_background_dark',
                'label.section_background_none',
            ],
        ];

        $keys = [];
        foreach ($traits as $trait => $traitKeys) {
            if (str_contains($contents, 'use ' . $trait . ';')) {
                $keys = array_merge($keys, $traitKeys);
            }
        }

        return $keys;
    }

    // Twig: 'key'|trans({...}, 'pilot'), in either quote
    /** @return list<string> */
    private function twigKeys(string $contents): array
    {
        preg_match_all("/['\"]([a-zA-Z0-9_.]+)['\"]\\s*\\|\\s*trans\\(\\s*(?:\\{[^}]*\\})?\\s*,\\s*['\"]pilot['\"]/", $contents, $matches);

        return $matches[1];
    }

    // PHP: trans('key', [...], 'pilot'), with or without a locale after it, and the t() the CRUD fields take
    /** @return list<string> */
    private function phpKeys(string $contents): array
    {
        preg_match_all("/(?:trans|\\bt)\\(\\s*['\"]([a-zA-Z0-9_.]+)['\"]\\s*,\\s*\\[[^\\]]*\\]\\s*,\\s*['\"]pilot['\"]/", $contents, $matches);

        return $matches[1];
    }

    // The labels declared as plain array values (an account section's 'title' among them), and the choices of a ChoiceType, whose key stands left of the value it stores
    /** @return list<string> */
    private function declaredLabelKeys(string $contents): array
    {
        preg_match_all("/'(?:label|help|description|title|placeholder)' => '([a-z][a-zA-Z0-9_]*\\.[a-zA-Z0-9_.]+)'/", $contents, $matches);
        preg_match_all("/'((?:label|text)\\.[a-zA-Z0-9_.]+)'\\s*=>/", $contents, $choices);

        return array_merge($matches[1], $choices[1]);
    }

    /** @return list<string> */
    private function yamlKeys(string $contents): array
    {
        preg_match_all("/^\\s*(?:label|description|category):\\s*['\"]([a-z_]+\\.[a-zA-Z0-9_.]+)['\"]/m", $contents, $matches);

        return $matches[1];
    }

    /** @return array<string, string> */
    private function catalogue(string $path): array
    {
        $xliff = simplexml_load_file($path);
        $translations = [];

        foreach ($xliff->file->body->{'trans-unit'} as $unit) {
            $translations[(string) $unit->source] = (string) $unit->target;
        }

        return $translations;
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['/../src', '/../templates', '/../config'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . $directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && \in_array($file->getExtension(), ['php', 'twig', 'yaml'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
