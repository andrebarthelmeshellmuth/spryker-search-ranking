<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->withSkip([
        __DIR__ . '/tests/*/_support/_generated',
        __DIR__ . '/tests/*/_output',
        __DIR__ . '/tests/*/_data',
        // Bridge classes are Spryker's generated dependency-glue boilerplate — real Spryker core Bridges
        // (see e.g. vendor/spryker/rest-request-validator's StoreBridge) still ship the classic `@var` +
        // assign-in-constructor form, not promoted properties. Promoting these would permanently diverge
        // from what the Spryker code generator itself produces, so a future regeneration would just
        // revert it — same "generated code" exemption class as skipping generated test support dirs above.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/*Bridge.php',
        ],
        // Spryker.Commenting.DocBlockParam (active via the base Spryker ruleset, phpcs.xml) requires
        // exactly one @param tag per method parameter, typed or not, plus a specific docblock spacing
        // that a stripped tag breaks. This rule removes @param tags for natively typed params, which
        // empirically produced "There must be exactly one blank line before the tags" phpcs errors
        // (verified live on FunctionScoreBuilderInterface.php) — a direct, systemic contradiction of
        // that convention across every file in this codebase, not just an isolated case.
        RemoveUselessParamTagRector::class,
        // Typed class constants (PHP 8.3) aren't understood by the installed phpcs 3.7.1
        // (Generic.NamingConventions.UpperCaseConstantName misreads the type as the constant name).
        // Same bug already documented and skipped for this exact rule in the sibling search-debug package.
        AddTypeToConstRector::class,
        // Rewrites plain `=== null` / `!== null` checks on a nullable single-class type into
        // `instanceof \Fully\Qualified\ClassName` — strictly more verbose for a simple null check
        // (no added type-safety over the null check it replaces), breaks this codebase's consistent
        // === null idiom used everywhere else, and writes an inline FQCN instead of a use import,
        // which trips Spryker.Namespaces.UseStatement. Same rule, same reasoning, already skipped
        // in the sibling search-debug package.
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Gradual levels (0 = safest rules only). Raising in batches; stop at the first hit that
    // conflicts with established Spryker style rather than applying it automatically.
    ->withDeadCodeLevel(65)
    ->withCodeQualityLevel(65)
    ->withoutParallel();
