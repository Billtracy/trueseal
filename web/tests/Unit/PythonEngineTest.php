<?php

it('emits JSON and creates a heatmap image', function () {
    $input = tempnam(sys_get_temp_dir(), 'trueseal-input-').'.jpg';
    $outputDir = sys_get_temp_dir().'/trueseal-heatmaps-'.uniqid();

    $image = imagecreatetruecolor(420, 280);
    $background = imagecolorallocate($image, 245, 245, 240);
    $text = imagecolorallocate($image, 20, 20, 20);
    imagefilledrectangle($image, 0, 0, 420, 280, $background);
    imagestring($image, 5, 80, 90, 'Ada Lovelace', $text);
    imagejpeg($image, $input, 92);
    imagedestroy($image);

    $process = new Symfony\Component\Process\Process([
        'python3',
        dirname(__DIR__, 3).'/python/trueseal_forensics.py',
        '--input',
        $input,
        '--output-dir',
        $outputDir,
        '--candidate-name',
        'Ada Lovelace',
        '--original-name',
        'ada-degree-2024.jpg',
    ]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue();

    $payload = json_decode($process->getOutput(), true);

    expect($payload)->toBeArray()
        ->and($payload['verdict'])->toBeIn(['PASS', 'FAIL'])
        ->and($payload['heatmap_path'])->not->toBeEmpty()
        ->and(file_exists($payload['heatmap_path']))->toBeTrue();
});
