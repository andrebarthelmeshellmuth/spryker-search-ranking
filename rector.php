<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

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
    ])
    // Picks up the PHP floor (>=8.3) from composer.json.
    ->withPhpSets()
    // Gradual levels (0 = safest rules only). Raising in batches; stop at the first hit that
    // conflicts with established Spryker style rather than applying it automatically.
    ->withDeadCodeLevel(15)
    ->withCodeQualityLevel(15)
    ->withoutParallel();
