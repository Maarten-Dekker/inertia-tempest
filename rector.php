<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\StaticCall\StaticCallOnNonStaticToInstanceCallRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Tempest\Upgrade\Set\TempestSetList;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withCache('./.cache/rector', FileCacheStorage::class)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        PHPUnitSetList::PHPUNIT_120,
        TempestSetList::TEMPEST_30,
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
    )
    ->withSkip([
        AddArrowFunctionReturnTypeRector::class,
        ClosureReturnTypeRector::class,
        ClosureToArrowFunctionRector::class,
        EncapsedStringsToSprintfRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        StaticCallOnNonStaticToInstanceCallRector::class,
        NullableCompareToNullRector::class,
        ExplicitBoolCompareRector::class,
        ArrayToFirstClassCallableRector::class,
    ]);
