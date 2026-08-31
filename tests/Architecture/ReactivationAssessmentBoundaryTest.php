<?php

declare(strict_types=1);

it('keeps Assessments behind the Discipline public reactivation contract', function (): void {
    $root = dirname(__DIR__, 2).'/modules/Assessments';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        expect($source)
            ->not->toContain('Modules\\Discipline\\Domain\\Models')
            ->not->toContain('reactivation_requests');
    }
});
