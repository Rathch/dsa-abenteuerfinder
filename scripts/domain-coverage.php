<?php

declare(strict_types=1);

$coverageFile = $argv[1] ?? 'coverage.xml';
$min = (float) ($argv[2] ?? 90);

if (! is_file($coverageFile)) {
    fwrite(STDERR, "Coverage-Datei fehlt: {$coverageFile}\n");
    exit(1);
}

$root = simplexml_load_file($coverageFile);
if ($root === false) {
    fwrite(STDERR, "Coverage-Datei ungültig: {$coverageFile}\n");
    exit(1);
}

$total = 0;
$covered = 0;

foreach ($root->xpath('//file') ?: [] as $file) {
    $name = (string) $file['name'];
    if (! str_contains($name, '/app/Domain/') && ! str_contains($name, '\\app\\Domain\\')) {
        continue;
    }

    $metrics = $file->metrics;
    if ($metrics === null) {
        continue;
    }

    $total += (int) $metrics['statements'];
    $covered += (int) $metrics['coveredstatements'];
}

if ($total === 0) {
    fwrite(STDERR, "Keine Statements in app/Domain gefunden.\n");
    exit(1);
}

$percent = ($covered / $total) * 100.0;
printf('Domain-Coverage: %.2f %% (%d/%d)%s', $percent, $covered, $total, PHP_EOL);

exit($percent >= $min ? 0 : 1);
