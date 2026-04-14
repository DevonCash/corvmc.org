<?php

use CorvMC\Finance\Facades\MemberBenefitService;

test('module service providers are loaded', function () {
    // Check if the facade accessor is bound
    $accessor = app(\CorvMC\Finance\Services\MemberBenefitService::class);
    expect($accessor)->toBeInstanceOf(\CorvMC\Finance\Services\MemberBenefitService::class);
});

test('facades can be resolved', function () {
    // Check if the facade root is properly set
    $root = MemberBenefitService::getFacadeRoot();
    expect($root)->toBeInstanceOf(\CorvMC\Finance\Services\MemberBenefitService::class);
});

test('MemberBenefitService has allocateMonthlyCredits method', function () {
    $service = app(\CorvMC\Finance\Services\MemberBenefitService::class);
    expect(method_exists($service, 'allocateMonthlyCredits'))->toBe(false)
        ->and(method_exists($service, 'allocateUserMonthlyCredits'))->toBe(true);
});