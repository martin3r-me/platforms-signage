<?php

namespace Platform\Signage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Services\ContextFileService;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageMediaPage;
use Symfony\Component\Process\Process;

/**
 * Wandelt hochgeladene Dokumente (PDF, PPTX/PPT) in einzelne Seitenbilder um,
 * damit jede Seite/Folie als eigener Frame in der Playlist angezeigt werden kann.
 *
 * Pipeline:
 *   PPTX/PPT --(LibreOffice headless)--> PDF --(Ghostscript)--> PNG je Seite --(ContextFileService)--> WebP
 *
 * Ghostscript ist bereits Teil des Stacks (vgl. Events PdfFloorPlanMerger);
 * LibreOffice wird nur für PowerPoint benötigt.
 */
class DocumentConversionService
{
    public function __construct(private ContextFileService $files)
    {
    }

    /**
     * Konvertiert ein Dokument-Medium in Seitenbilder. Idempotent: vorhandene
     * Seiten werden vorher entfernt.
     */
    public function convert(SignageMedia $media): void
    {
        if ($media->kind !== 'document') {
            return;
        }

        $media->update(['processing_status' => 'processing']);

        $workDir = $this->makeWorkDir();
        $created = [];

        try {
            // 1) Original auf lokale Platte holen (auch von S3 o.ä.).
            $localInput = $this->fetchOriginal($media, $workDir);
            $created[] = $localInput;

            // 2) PowerPoint -> PDF
            $pdfPath = $this->isPowerPoint($media)
                ? $this->convertToPdf($localInput, $workDir)
                : $localInput;
            if ($pdfPath !== $localInput) {
                $created[] = $pdfPath;
            }

            // 3) PDF -> PNG je Seite
            $pagePngs = $this->renderPdfPages($pdfPath, $workDir);
            $created = array_merge($created, $pagePngs);

            if (empty($pagePngs)) {
                throw new \RuntimeException('Keine Seiten erzeugt.');
            }

            // 4) Alte Seiten entfernen, neue speichern.
            $media->pages()->delete();

            $teamId = $media->team_id;
            $pageNumber = 1;
            foreach ($pagePngs as $png) {
                $upload = new UploadedFile(
                    $png,
                    'page-'.$pageNumber.'.png',
                    'image/png',
                    null,
                    true // test mode: umgeht is_uploaded_file()-Prüfung
                );

                $result = $this->files->uploadForContext($upload, 'signage_media_page', $media->id, [
                    'team_id'           => $teamId,
                    'user_id'           => $media->user_id,
                    'keep_original'     => false,
                    'generate_variants' => false,
                ]);

                SignageMediaPage::create([
                    'media_id'    => $media->id,
                    'page_number' => $pageNumber,
                    'disk'        => config('filesystems.default', 'public'),
                    'path'        => $result['path'],
                    'token'       => $result['token'],
                    'width'       => $result['width'] ?? null,
                    'height'      => $result['height'] ?? null,
                ]);

                $pageNumber++;
            }

            $media->update([
                'page_count'        => count($pagePngs),
                'processing_status' => 'ready',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Signage] Dokument-Konvertierung fehlgeschlagen', [
                'media_id' => $media->id,
                'error'    => $e->getMessage(),
            ]);
            $media->update(['processing_status' => 'failed']);
            throw $e;
        } finally {
            $this->cleanup($created);
            @rmdir($workDir);
        }
    }

    private function isPowerPoint(SignageMedia $media): bool
    {
        $ext = strtolower(pathinfo((string) $media->original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['pptx', 'ppt'], true)
            || in_array($media->mime_type, [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-powerpoint',
            ], true);
    }

    private function fetchOriginal(SignageMedia $media, string $workDir): string
    {
        $ext = strtolower(pathinfo((string) $media->original_name, PATHINFO_EXTENSION)) ?: 'bin';
        $target = $workDir.DIRECTORY_SEPARATOR.'input.'.$ext;

        $content = Storage::disk($media->disk)->get($media->path);
        if ($content === null) {
            throw new \RuntimeException('Originaldatei nicht lesbar: '.$media->path);
        }

        file_put_contents($target, $content);

        return $target;
    }

    private function convertToPdf(string $inputPath, string $workDir): string
    {
        $soffice = $this->binary([
            'soffice', 'libreoffice',
        ], [
            '/usr/bin/soffice', '/usr/local/bin/soffice',
            '/opt/homebrew/bin/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ]);

        if ($soffice === null) {
            throw new \RuntimeException('LibreOffice (soffice) nicht gefunden – PowerPoint-Konvertierung nicht möglich.');
        }

        $proc = new Process([
            $soffice,
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $workDir,
            $inputPath,
        ]);
        // Eigenes User-Profile, damit parallele Worker sich nicht blockieren.
        $proc->setEnv(['HOME' => $workDir]);
        $proc->setTimeout(120);
        $proc->run();

        if (!$proc->isSuccessful()) {
            throw new \RuntimeException('LibreOffice-Konvertierung fehlgeschlagen: '.trim($proc->getErrorOutput()));
        }

        $pdf = $workDir.DIRECTORY_SEPARATOR.pathinfo($inputPath, PATHINFO_FILENAME).'.pdf';
        if (!is_file($pdf)) {
            // Fallback: erstes PDF im Arbeitsverzeichnis.
            $candidates = glob($workDir.DIRECTORY_SEPARATOR.'*.pdf') ?: [];
            if (empty($candidates)) {
                throw new \RuntimeException('LibreOffice lieferte keine PDF-Datei.');
            }
            $pdf = $candidates[0];
        }

        return $pdf;
    }

    /**
     * Rendert jede PDF-Seite als PNG (150 dpi) und gibt die Dateipfade in
     * Seitenreihenfolge zurück.
     *
     * @return list<string>
     */
    private function renderPdfPages(string $pdfPath, string $workDir): array
    {
        $gs = $this->binary(['gs'], ['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs']);
        if ($gs === null) {
            throw new \RuntimeException('Ghostscript (gs) nicht gefunden.');
        }

        $pattern = $workDir.DIRECTORY_SEPARATOR.'page-%04d.png';

        $proc = new Process([
            $gs,
            '-dNOPAUSE',
            '-dBATCH',
            '-dQUIET',
            '-sDEVICE=png16m',
            '-r150',
            '-dTextAlphaBits=4',
            '-dGraphicsAlphaBits=4',
            '-sOutputFile='.$pattern,
            $pdfPath,
        ]);
        $proc->setTimeout(180);
        $proc->run();

        if (!$proc->isSuccessful()) {
            throw new \RuntimeException('Ghostscript-Rendering fehlgeschlagen: '.trim($proc->getErrorOutput()));
        }

        $pages = glob($workDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
        sort($pages, SORT_STRING);

        return $pages;
    }

    /**
     * Sucht ein Binary: erst per PATH ("which"), dann in bekannten Pfaden.
     *
     * @param list<string> $names
     * @param list<string> $knownPaths
     */
    private function binary(array $names, array $knownPaths): ?string
    {
        foreach ($names as $name) {
            try {
                $which = new Process(['which', $name]);
                $which->setTimeout(3);
                $which->run();
                if ($which->isSuccessful()) {
                    $path = trim($which->getOutput());
                    if ($path !== '' && is_executable($path)) {
                        return $path;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        foreach ($knownPaths as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'signage_'.bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);

        return $dir;
    }

    /**
     * @param list<string> $paths
     */
    private function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            @unlink($p);
        }
    }
}
