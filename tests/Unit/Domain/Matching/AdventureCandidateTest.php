<?php

declare(strict_types=1);

it('prefers the klappentext over the kurzbeschreibung', function (): void {
    $candidate = adventureCandidate([
        'shortDescription' => 'Die Helden untersuchen eine Einbruchsserie in Rashdul.',
        'klappentext' => 'Yol-Ghurmak. Ein Name, der Aventuriern das Blut gefrieren lässt.',
    ]);

    expect($candidate->displayDescription())->toBe([
        'kind' => 'klappentext',
        'text' => 'Yol-Ghurmak. Ein Name, der Aventuriern das Blut gefrieren lässt.',
    ]);
});

it('uses the kurzbeschreibung when no klappentext is stored', function (): void {
    $candidate = adventureCandidate([
        'shortDescription' => 'Die Helden untersuchen eine Einbruchsserie in Rashdul.',
        'klappentext' => null,
    ]);

    expect($candidate->displayDescription())->toBe([
        'kind' => 'kurzbeschreibung',
        'text' => 'Die Helden untersuchen eine Einbruchsserie in Rashdul.',
    ]);
});

it('hides a placeholder when neither text is present', function (): void {
    $candidate = adventureCandidate([
        'shortDescription' => '-',
        'klappentext' => '-',
    ]);

    expect($candidate->displayDescription())->toBeNull();
});
