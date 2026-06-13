<?php

namespace App\Services;

use App\Models\Verification;
use Illuminate\Support\Facades\Log;
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
            config('services.truststack.python_bin'),
            config('services.truststack.engine_path'),
            '--input',
            $inputPath,
            '--output-dir',
            $outputDir,
            '--candidate-name',
            $verification->candidate_name,
            '--original-name',
            $verification->original_filename,
        ]);

        // Tighter timeout for demo responsiveness — engine should finish within 30s
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            Log::warning('Forensic engine stderr', ['stderr' => $stderr]);
            throw new RuntimeException($stderr ?: 'Forensic engine failed.');
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
                'forensic_details' => $this->buildForensicDetails($result),
                'heatmap_path' => $heatmapPath,
                'engine_error' => null,
                'scanned_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Forensic scan failed', [
                'verification_id' => $verification->id,
                'error' => $exception->getMessage(),
            ]);

            $verification->update([
                'status' => Verification::STATUS_ERROR,
                'verdict' => 'ERROR',
                'engine_error' => $exception->getMessage(),
                'scanned_at' => now(),
            ]);
        }

        return $verification->refresh();
    }

    /**
     * Extract the enriched forensic details envelope from the v3 engine output.
     */
    private function buildForensicDetails(array $result): array
    {
        return [
            'engine_version' => data_get($result, 'engine_version', 'unknown'),
            'analysis_duration_ms' => data_get($result, 'analysis_duration_ms', 0),
            'confidence_level' => data_get($result, 'confidence_level', 'LOW'),
            'layer_scores' => [
                'ela' => (int) data_get($result, 'ela_score', 0),
                'ocr' => (int) data_get($result, 'ocr_score', 0),
                'noise' => (int) data_get($result, 'noise_score', 0),
                'edge' => (int) data_get($result, 'edge_score', 0),
            ],
            'ela' => data_get($result, 'forensic_details.ela', []),
            'noise' => data_get($result, 'forensic_details.noise', []),
            'edge' => data_get($result, 'forensic_details.edge', []),
            'layer_errors' => data_get($result, 'layer_errors', []),
        ];
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
