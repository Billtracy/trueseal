<?php

namespace App\Services;

use App\Models\Verification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ForensicAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(Verification $verification): array
    {
        $inputPath = Storage::disk('local')->path($verification->document_path);
        $outputDir = Storage::disk('local')->path('verifications/heatmaps');

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            throw new RuntimeException('Unable to create forensic output directory.');
        }

        $process = new Process([
            config('services.trueseal.python_bin'),
            config('services.trueseal.engine_path'),
            '--input',
            $inputPath,
            '--output-dir',
            $outputDir,
            '--candidate-name',
            $verification->candidate_name,
            '--original-name',
            $verification->original_filename,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Forensic engine failed.');
        }

        $result = json_decode($process->getOutput(), true);

        if (! is_array($result)) {
            throw new RuntimeException('Forensic engine returned invalid JSON.');
        }

        return $result;
    }

    public function scanAndPersist(Verification $verification): Verification
    {
        $verification->update(['status' => Verification::STATUS_PROCESSING]);

        try {
            $result = $this->analyze($verification);
            $heatmapPath = $this->relativeLocalPath((string) data_get($result, 'heatmap_path'));
            $status = data_get($result, 'verdict') === 'FAIL' ? Verification::STATUS_FAIL : Verification::STATUS_PASS;

            $verification->update([
                'status' => $status,
                'verdict' => data_get($result, 'verdict'),
                'score' => (int) data_get($result, 'score', 0),
                'findings' => data_get($result, 'findings', []),
                'suspicious_regions' => data_get($result, 'suspicious_regions', []),
                'heatmap_path' => $heatmapPath,
                'engine_error' => null,
                'scanned_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $verification->update([
                'status' => Verification::STATUS_ERROR,
                'verdict' => 'ERROR',
                'engine_error' => $exception->getMessage(),
                'scanned_at' => now(),
            ]);
        }

        return $verification->refresh();
    }

    private function relativeLocalPath(string $absolutePath): string
    {
        $root = Storage::disk('local')->path('');

        if (str_starts_with($absolutePath, $root)) {
            return ltrim(substr($absolutePath, strlen($root)), DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}
