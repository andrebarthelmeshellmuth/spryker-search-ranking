<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

/**
 * Verifies that this package's declared composer floors are REAL rather than guessed.
 *
 * Run via `composer check-floors`, which first resolves every declared constraint down to its oldest
 * allowed version (`--prefer-lowest --prefer-stable --no-dev`) and then executes this script against
 * that tree. Any symbol `src/` references but which does not exist at those versions means a declared
 * floor is too low — the package would fatal on a shop that legitimately installed the versions we claim
 * to support. Mirrors spryker-community/search-debug's floor-check, which found 6 undeclared dependencies
 * the very first time it was run — the classic community-package bug: works in the monorepo it was
 * developed in (which has the full Spryker suite installed), breaks on a real `composer require` in a
 * leaner shop.
 *
 * Production dependencies only (`--no-dev`): dev tooling is irrelevant to what an adopter installs, and
 * excluding it is also what makes the OPTIONAL_SYMBOL_PREFIXES allowlist below meaningful.
 */

$packageDir = dirname(__DIR__);

if (!is_file($packageDir . '/vendor/autoload.php')) {
    fwrite(STDERR, "No vendor/autoload.php — run `composer check-floors` rather than this script directly.\n");

    exit(1);
}

require $packageDir . '/vendor/autoload.php';

/**
 * Symbols allowed to be absent because the module providing them is a composer `suggest` (never
 * `require`), so a shop without it gets the feature guarded off rather than a crash.
 *
 * `spryker-community/search-debug` backs the OPTIONAL `SearchRankingProductDebugDataExpanderPlugin`
 * (adds a business-signal breakdown section to search-debug's own SRP overlay) — dead code unless a
 * project deliberately installs and wires both packages. Per the standalone-packages rule, search-ranking
 * must never hard-depend on search-debug; this allowlist is what lets that rule be verified by the same
 * mechanism that catches undeclared REQUIRED dependencies, rather than trusting it by convention.
 *
 * @var array<string>
 */
const OPTIONAL_SYMBOL_PREFIXES = [
    'SprykerCommunity\\Client\\SearchDebug\\',
    'SprykerCommunity\\Shared\\SearchDebug\\',
];

/**
 * Generated at build time by the host project — `Generated\` by `transfer:generate`, `Orm\` by
 * `propel:install`/`propel:model:build` from this package's OWN shipped schema.xml (merged with the
 * host's other schemas) — never shipped in any vendor tree, so their absence here is expected and says
 * nothing about dependency floors. Unlike search-debug (no Propel schema of its own), search-ranking owns
 * several tables, so `Orm\` classes appear here for real.
 *
 * @var array<string>
 */
const HOST_GENERATED_PREFIXES = [
    'Generated\\',
    'Orm\\',
];

/**
 * This package's OWN namespace root. Broader than search-debug's equivalent check: search-ranking spans
 * several modules under the same `SprykerCommunity\` root (SearchRanking, SearchRankingGui,
 * SearchRankingStorage, Client/Zed/Shared variants of each) as well as the OPTIONAL search-debug
 * integration living under that identical root — so optional prefixes above are checked FIRST, and only
 * symbols that don't match one of those are treated as "this package's own code, skip".
 *
 * @var string
 */
const OWN_CODE_PREFIX = 'SprykerCommunity\\';

$usedSymbols = [];
$sourceFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageDir . '/src'));

foreach ($sourceFiles as $sourceFile) {
    if (!$sourceFile->isFile() || $sourceFile->getExtension() !== 'php') {
        continue;
    }

    preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)/m', (string)file_get_contents($sourceFile->getPathname()), $matches);

    foreach ($matches[1] as $symbol) {
        $usedSymbols[$symbol] = true;
    }
}

ksort($usedSymbols);

$missing = [];
$optionalAbsent = [];
$hostGenerated = 0;
$resolved = 0;

foreach (array_keys($usedSymbols) as $symbol) {
    $isOptional = false;

    foreach (OPTIONAL_SYMBOL_PREFIXES as $optionalPrefix) {
        if (str_starts_with($symbol, $optionalPrefix)) {
            $isOptional = true;

            break;
        }
    }

    if ($isOptional) {
        if (class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol)) {
            $resolved++;
        } else {
            $optionalAbsent[] = $symbol;
        }

        continue;
    }

    if (str_starts_with($symbol, OWN_CODE_PREFIX)) {
        continue;
    }

    $isHostGenerated = false;

    foreach (HOST_GENERATED_PREFIXES as $hostGeneratedPrefix) {
        if (str_starts_with($symbol, $hostGeneratedPrefix)) {
            $isHostGenerated = true;

            break;
        }
    }

    if ($isHostGenerated) {
        $hostGenerated++;

        continue;
    }

    if (class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol)) {
        $resolved++;

        continue;
    }

    $missing[] = $symbol;
}

printf(
    "floor-check: %d resolved | %d host-generated (skipped) | %d optional-absent (expected) | %d MISSING\n",
    $resolved,
    $hostGenerated,
    count($optionalAbsent),
    count($missing),
);

foreach ($optionalAbsent as $symbol) {
    echo "  optional (suggest, guarded at runtime): $symbol\n";
}

foreach ($missing as $symbol) {
    echo "  MISSING: $symbol\n";
}

if ($missing !== []) {
    fwrite(STDERR, "\nA declared floor is too low, or a dependency is undeclared entirely.\n");

    exit(1);
}

echo "All required symbols exist at the lowest allowed dependency versions.\n";

exit(0);
