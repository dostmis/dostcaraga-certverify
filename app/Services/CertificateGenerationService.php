<?php

namespace App\Services;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Support\PdfImageNormalizer;
use App\Support\RegionalDirectorSignatory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use setasign\Fpdi\Fpdi;

/**
 * Certificate row creation and PDF stamping.
 *
 * Extracted from CertificateAdminController so the synchronous approval path
 * and the queued GenerateCertificateJob share one implementation. Keeping the
 * per-participant body here is what stops the two paths drifting.
 */
class CertificateGenerationService
{
    public const STANDARD_NAME_POS_X = 30.0;
    public const STANDARD_NAME_POS_Y = 110.0;
    public const STANDARD_NAME_FONT_SIZE = 45.0;
    public const STANDARD_NAME_FONT_FAMILY = 'Times';
    public const NOT_APPLICABLE = 'Not Applicable';

    public const PROGRAM_NATIONAL_REGULAR = 'National/Regular Program';
    public const PROGRAM_SSCP = 'SSCP (Smart and Sustainable Communities Program)';
    public const PROGRAM_SETUP = 'SETUP (Small Enterprise Technology Upgrading Program)';

    public function dostPrograms(): array
    {
        return [
            self::PROGRAM_NATIONAL_REGULAR,
            'LGIA (Local Grants-in-Aid Program)',
            'CEST (Community Empowerment through Science and Technology Program)',
            self::PROGRAM_SSCP,
            self::PROGRAM_SETUP,
            'Others',
        ];
    }

    /**
     * Build the certificate row payload for one participant.
     *
     * @param  array<string, mixed>  $payload    Endorsement training payload.
     * @param  array<string, mixed>  $participant One parsed participant row.
     * @return array<string, mixed>
     */
    public function buildParticipantRow(array $payload, array $participant, ?int $recipientId): array
    {
        return [
            'participant_name' => $participant['name'],
            'email' => $participant['email'] ?? null,
            'recipient_id' => $recipientId,
            'gender' => $participant['gender'] ?? null,
            'age' => $participant['age'] ?? null,
            'block_lot_purok' => $participant['block_lot_purok'] ?? null,
            'region' => $participant['region'] ?? null,
            'city_municipality' => $participant['city_municipality'] ?? null,
            'barangay' => $participant['barangay'] ?? null,
            'province' => $participant['province'] ?? null,
            'industry' => $participant['industry'] ?? null,
            'training_title' => $payload['training_title'] ?? '',
            'caption_text' => $payload['caption_text'] ?? null,
            'caption_alignment' => $payload['caption_alignment'] ?? 'center',
            'activity_type' => $payload['activity_type'] ?? null,
            'certificate_type' => $payload['certificate_type'] ?? null,
            'recipient_type' => $payload['recipient_type'] ?? null,
            'venue' => $payload['venue'] ?? null,
            'topic' => $payload['topic'] ?? null,
            'training_date' => $payload['training_date_from'] ?? null,
            'training_date_to' => $payload['training_date_to'] ?? null,
            'number_of_training_hours' => $payload['number_of_training_hours'] ?? null,
            'dost_program' => $payload['dost_program'] ?? null,
            'setup_office_province' => $payload['setup_office_province'] ?? self::NOT_APPLICABLE,
            'pillar' => $payload['pillar'] ?? null,
            'dost_project' => $payload['dost_project'] ?? null,
            'project_code' => $payload['project_code'] ?? null,
            'source_of_funds' => $payload['source_of_funds'] ?? self::NOT_APPLICABLE,
            'training_budget' => $payload['training_budget'] ?? null,
            'expected_number_of_participants' => $payload['expected_number_of_participants'] ?? null,
            'issuing_office' => $payload['issuing_office'] ?? '',
        ];
    }

    /**
     * Generate one participant's certificate: row, source copy, stamped PDF.
     *
     * $templatePath must already be FPDI-readable — normalizeTemplateForFpdi()
     * is called once by the caller, never per participant, or Ghostscript runs
     * once per certificate and dominates the whole run.
     */
    public function generateForParticipant(
        array $payload,
        array $participant,
        ?int $recipientId,
        string $templatePath,
        bool $applyRegionalDirectorESign = false,
        ?int $endorsementId = null,
        ?int $participantIndex = null
    ): Certificate {
        $storage = Storage::disk('local');

        $rowData = $this->buildParticipantRow($payload, $participant, $recipientId);
        $rowData['endorsement_id'] = $endorsementId;
        $rowData['endorsement_participant_index'] = $endorsementId === null ? null : $participantIndex;

        $cert = $this->createCertificate($rowData);
        $sourcePath = 'certificates/source/' . $cert->certificate_code . '.pdf';
        $storage->copy($templatePath, $sourcePath);
        $this->stampCertificatePdf(
            $cert,
            $sourcePath,
            self::STANDARD_NAME_POS_X,
            self::STANDARD_NAME_POS_Y,
            self::STANDARD_NAME_FONT_SIZE,
            self::STANDARD_NAME_FONT_FAMILY,
            true,
            $applyRegionalDirectorESign,
            $payload['caption_text'] ?? null,
            $payload['caption_alignment'] ?? 'center'
        );

        return $cert->fresh();
    }

    /**
     * Ensure the training payload has everything generation needs.
     *
     * @throws \RuntimeException
     */
    public function assertPayloadIsGeneratable(array $payload, string $templatePath): void
    {
        if (!Storage::disk('local')->exists($templatePath)) {
            throw new \RuntimeException('Template PDF for this request is missing in storage.');
        }
        if (empty($payload['training_title']) || empty($payload['training_date_from']) || empty($payload['issuing_office'])) {
            throw new \RuntimeException('Training details for this request are incomplete.');
        }
    }

    /**
     * Return a template path FPDI can parse directly, converting once if needed.
     *
     * @return array{0: string, 1: ?string} The path to stamp from, and the
     *         temporary path to clean up afterwards (null when none was made).
     */
    public function normalizeTemplateForFpdi(string $templatePath): array
    {
        $storage = Storage::disk('local');

        try {
            (new Fpdi())->setSourceFile($storage->path($templatePath));

            return [$templatePath, null];
        } catch (\Throwable $e) {
            // Unreadable by FPDI (typically PDF 1.5+); fall back to Ghostscript.
        }

        $convertedAbs = $this->convertPdfWithGhostscript($storage->path($templatePath));

        // convertPdfWithGhostscript writes to storage/app/tmp, which sits
        // outside the local disk root, so move it onto the disk to keep the
        // per-certificate Storage::copy() below working on relative paths.
        $temporaryTemplatePath = 'tmp/template_normalized_' . Str::uuid() . '.pdf';
        $storage->put($temporaryTemplatePath, file_get_contents($convertedAbs));
        @unlink($convertedAbs);

        return [$temporaryTemplatePath, $temporaryTemplatePath];
    }

    public function createCertificate(array $data): Certificate
    {
        $officeCodeMap = [
            'DOST Caraga - Fields Operation Division' => 'FOD',
            'DOST Caraga - Financial and Administrative Services' => 'FAS',
            'DOST Caraga - Office of the Regional Director' => 'ORD',
            'DOST Caraga - Technical Support Services' => 'TSS',
            'DOST Caraga - Innovation Unit' => 'IU',
            'DOST Caraga - PSTO-Agusan Del Norte' => 'ADN',
            'DOST Caraga - PSTO-Agusan Del Sur' => 'ADS',
            'DOST Caraga - PSTO-Surigao Del Norte' => 'SDN',
            'DOST Caraga - PSTO-Surigao Del Sur' => 'SDS',
            'DOST Caraga - PSTO-Province of Dinagat Island' => 'PDI',
        ];

        $program = strtoupper(trim((string) ($data['dost_program'] ?? '')));
        if (!in_array($program, $this->dostPrograms(), true)) {
            $program = '';
        }

        if ($program === '') {
            $program = $officeCodeMap[$data['issuing_office']] ?? '';
        }

        if ($program === '') {
            $program = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($data['program_code'] ?? '')));
        }
        if ($program === '') {
            $program = 'GEN';
        }

        $stopWords = ['and', 'of', 'the', 'for', 'to', 'in', 'on', 'a', 'an', '&'];
        $words = preg_split('/[^A-Za-z0-9]+/', $data['training_title']);
        $letters = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (in_array(strtolower($word), $stopWords, true)) {
                continue;
            }
            $letters .= strtoupper($word[0]);
            if (strlen($letters) >= 3) {
                break;
            }
        }
        if ($letters === '' && !empty($words[0])) {
            $letters = strtoupper($words[0][0]);
        }
        $short = substr($letters, 0, 3);
        if ($short === '') {
            $short = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($data['training_short'] ?? '')));
            $short = substr($short, 0, 3) ?: 'GEN';
        }

        // Half-open range rather than whereDate(): wrapping created_at in a
        // function stops Postgres using the index on it, making this a
        // sequential scan once per participant. The bounds are naive strings so
        // this stays byte-for-byte equivalent to the previous date comparison
        // against the timestamp-without-time-zone column.
        $batchDate = now('Asia/Manila')->toDateString();
        $batchNumber = Certificate::where('created_at', '>=', $batchDate . ' 00:00:00')
            ->where('created_at', '<', Carbon::parse($batchDate)->addDay()->toDateString() . ' 00:00:00')
            ->count() + 1;
        $batch = str_pad((string) $batchNumber, 2, '0', STR_PAD_LEFT);

        $year = date('Y', strtotime($data['training_date']));
        $prefix = "{$year}-{$program}-{$short}-{$batch}-";

        return DB::transaction(function () use ($data, $prefix) {
            $last = Certificate::where('certificate_code', 'like', $prefix . '%')
                ->orderByDesc('certificate_code')
                ->value('certificate_code');

            $nextNumber = 1;
            if ($last) {
                $tail = substr($last, -3);
                if (ctype_digit($tail)) {
                    $nextNumber = intval($tail) + 1;
                }
            }

            $code = $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            return Certificate::create([
                'certificate_code' => $code,
                'participant_name' => $data['participant_name'],
                'email' => $data['email'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null,
                'gender' => $data['gender'] ?? null,
                'age' => $data['age'] ?? null,
                'block_lot_purok' => $data['block_lot_purok'] ?? null,
                'region' => $data['region'] ?? null,
                'city_municipality' => $data['city_municipality'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'province' => $data['province'] ?? null,
                'industry' => $data['industry'] ?? null,
                'training_title' => $data['training_title'],
                'activity_type' => $data['activity_type'] ?? null,
                'certificate_type' => $data['certificate_type'] ?? null,
                'recipient_type' => $data['recipient_type'] ?? null,
                'venue' => $data['venue'] ?? null,
                'topic' => $data['topic'] ?? null,
                'training_date' => $data['training_date'],
                'training_date_to' => $data['training_date_to'] ?? null,
                'number_of_training_hours' => $data['number_of_training_hours'] ?? null,
                'dost_program' => $data['dost_program'] ?? null,
                'setup_office_province' => $data['setup_office_province'] ?? self::NOT_APPLICABLE,
                'pillar' => $data['pillar'] ?? null,
                'dost_project' => $data['dost_project'] ?? null,
                'project_code' => $data['project_code'] ?? null,
                'source_of_funds' => $data['source_of_funds'] ?? self::NOT_APPLICABLE,
                'training_budget' => $data['training_budget'] ?? null,
                'expected_number_of_participants' => $data['expected_number_of_participants'] ?? null,
                'issuing_office' => $data['issuing_office'],
                'endorsement_id' => $data['endorsement_id'] ?? null,
                'endorsement_participant_index' => $data['endorsement_participant_index'] ?? null,
                'status' => 'valid',
            ]);
        });
    }

    public function stampCertificatePdf(
        Certificate $cert,
        string $sourcePath,
        float $namePosX,
        float $namePosY,
        float $nameFontSize,
        string $nameFontFamily,
        bool $centerName,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center'
    ): void
    {
        $verifyUrl = $this->buildVerifyUrl($cert->public_token);
        $sourceAbs = Storage::disk('local')->path($sourcePath);
        $pdfContent = $this->renderStampedPdf(
            $sourceAbs,
            $cert->participant_name,
            $namePosX,
            $namePosY,
            $nameFontSize,
            $nameFontFamily,
            $centerName,
            $cert->certificate_code,
            $verifyUrl,
            $applyRegionalDirectorESign,
            $captionText,
            $captionAlignment
        );

        $stampedRel = 'certificates/stamped/' . $cert->certificate_code . '.pdf';
        Storage::disk('local')->put($stampedRel, $pdfContent);

        $cert->update([
            'source_pdf_path' => $sourcePath,
            'stamped_pdf_path' => $stampedRel,
        ]);
    }

    public function renderStampedPdf(
        string $sourceAbs,
        string $participantName,
        float $namePosX,
        float $namePosY,
        float $nameFontSize,
        string $nameFontFamily,
        bool $centerName,
        string $codeText,
        string $verifyUrl,
        bool $applyRegionalDirectorESign = false,
        ?string $captionText = null,
        string $captionAlignment = 'center'
    ): string
    {
        $qrPng = QrCode::format('png')->size(220)->margin(1)->generate($verifyUrl);

        $tmpQr = storage_path('app/tmp_qr_' . Str::uuid() . '.png');
        @mkdir(dirname($tmpQr), 0777, true);
        file_put_contents($tmpQr, $qrPng);
        $temporaryFiles = [$tmpQr];
        $qrForPdf = PdfImageNormalizer::prepareForFpdf($tmpQr);
        if ($qrForPdf !== $tmpQr) {
            $temporaryFiles[] = $qrForPdf;
        }

        $pdf = new Fpdi();
        $converted = null;
        try {
            $pageCount = $pdf->setSourceFile($sourceAbs);
        } catch (\Throwable $e) {
            $converted = $this->convertPdfWithGhostscript($sourceAbs);
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($converted);
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            if ($pageNo === 1) {
                $pdf->SetFont($nameFontFamily, '', $nameFontSize);
                $pdf->SetTextColor(0, 0, 0);
                $nameText = $this->toLatin1($participantName);
                $nameX = $namePosX;
                if ($centerName) {
                    $textWidth = $pdf->GetStringWidth($nameText);
                    $nameX = max(0, ($size['width'] - $textWidth) / 2);
                }
                $pdf->Text($nameX, $namePosY, $nameText);

                if ($captionText && trim($captionText) !== '') {
                    $this->setPdfCaptionFont($pdf, 13.2);
                    $pdf->SetTextColor(60, 60, 60);

                    $captionLines = $this->captionMarkupToStyledLines($captionText);
                    if ($captionLines !== []) {
                        $captionWidth = max(80.0, $size['width'] - 80.0);
                        $captionX = max(40.0, ($size['width'] - $captionWidth) / 2);
                        $captionY = $namePosY + 11.0;
                        $captionAlign = match ($captionAlignment) {
                            'left' => 'L',
                            'right' => 'R',
                            'justify' => 'J',
                            default => 'C',
                        };

                        $currentCaptionY = $captionY;
                        foreach ($captionLines as $lineRuns) {
                            // An empty run list marks a blank line (a double line
                            // break in the editor) and renders as a vertical gap so
                            // the PDF mirrors the editor character-for-character.
                            if ($lineRuns === []) {
                                $currentCaptionY += 5.2;
                                continue;
                            }

                            $wrappedLines = $this->buildStyledPdfLines($pdf, $lineRuns, $captionWidth, 13.2);
                            $currentCaptionY = $this->renderStyledPdfLines(
                                $pdf,
                                $wrappedLines,
                                $captionX,
                                $currentCaptionY,
                                $captionWidth,
                                5.2,
                                $captionAlign,
                                13.2
                            );
                        }
                    }
                }

                if ($applyRegionalDirectorESign) {
                    $this->stampRegionalDirectorSignatureBlock($pdf, $size);
                }
            }

            $qrSize = 20;
            $margin = 10;
            $x = $size['width'] - $qrSize - $margin;

            $textOffset = 4;
            $requiredBottom = $qrSize + $textOffset + 9;
            $maxY = $size['height'] - $margin - $requiredBottom;
            $y = min($size['height'] - $qrSize - $margin, $maxY);
            $y = max($margin, $y);

            $pdf->Image($qrForPdf, $x, $y, $qrSize, $qrSize);

            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $textWidth = $pdf->GetStringWidth($codeText);
            $textX = $x + ($qrSize - $textWidth) / 2;
            $textX = max($margin, min($textX, $size['width'] - $margin - $textWidth));
            $textY = $y + $qrSize + $textOffset;
            $pdf->Text($textX, $textY, $codeText);

            $linkText = $this->toLatin1($verifyUrl);
            $pdf->SetFont('Helvetica', '', 5.5);
            $linkWidth = $pdf->GetStringWidth($linkText);
            $linkX = max($margin, $size['width'] - $margin - $linkWidth);
            $linkY = $textY + 3.5;
            $pdf->Text($linkX, $linkY, $linkText);
        }

        foreach (array_unique($temporaryFiles) as $temporaryFile) {
            @unlink($temporaryFile);
        }
        if ($converted) {
            @unlink($converted);
        }

        return $pdf->Output('S');
    }

    public function buildVerifyUrl(string $token): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $verifyPath = route('cert.verify', ['t' => $token], false);

        return $baseUrl . $verifyPath;
    }

    private function toLatin1(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
        if ($converted === false) {
            return utf8_decode($value);
        }

        return $converted;
    }

    private function convertPdfWithGhostscript(string $sourceAbs): string
    {
        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0777, true);
        $outPath = $tmpDir . '/fpdi_' . Str::uuid() . '.pdf';

        $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress -dNOPAUSE -dBATCH -dSAFER -o '
            . escapeshellarg($outPath) . ' ' . escapeshellarg($sourceAbs);

        @exec($cmd, $output, $code);

        if ($code !== 0 || !is_file($outPath)) {
            $message = $code === 127
                ? 'Ghostscript (gs) is not installed on the server. Please install it to process PDFs, or upload a PDF saved as version 1.4.'
                : 'This PDF cannot be processed. Please re-save it as PDF 1.4 or contact the administrator.';
            throw new \RuntimeException($message);
        }

        return $outPath;
    }

    /**
     * Turn a stored caption into the exact lines the PDF should print.
     *
     * The result is an array of "lines", each being an ordered list of styled
     * runs (`['text' => string, 'style' => '' | 'B' | 'I' | 'BI']`). An empty
     * array marks a blank line — i.e. a double line break in the editor — which
     * the renderer turns into a vertical gap.
     *
     * Formatting is read straight from the sanitized HTML (`<strong>`, `<em>`,
     * `<br>`, `<div>`). There is no `**` / `*` markdown round-trip, so literal
     * asterisks, ampersands and quotation marks are printed verbatim and bold
     * text never leaks stray markers across wrapped lines.
     */
    private function captionMarkupToStyledLines(?string $captionText): array
    {
        $markup = $this->sanitizeCaptionMarkup($captionText);
        if ($markup === null || $markup === '') {
            return [];
        }

        // Plain captions (no formatting tags) are printed exactly as typed,
        // splitting only on real line breaks. No characters are interpreted.
        if (preg_match('/<[^>]+>/', $markup) !== 1) {
            $lines = [];
            foreach (preg_split("/\n/", $markup) ?: [] as $line) {
                $line = rtrim($line);
                $lines[] = $line === ''
                    ? []
                    : [['text' => $this->toLatin1($line), 'style' => '']];
            }

            return $this->trimBlankStyledLines($lines);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $markup . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $state = ['lines' => [], 'current' => [], 'lastWasBreak' => true];
        foreach ($root->childNodes as $childNode) {
            $this->collectCaptionStyledRuns($childNode, false, false, $state);
        }
        if (!$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
        }

        return $this->trimBlankStyledLines($state['lines']);
    }

    /**
     * Walk a caption HTML node, appending styled runs and line breaks into the
     * shared $state accumulator.
     *
     * @param array{lines: array<int, array<int, array{text: string, style: string}>>, current: array<int, array{text: string, style: string}>, lastWasBreak: bool} $state
     */
    private function collectCaptionStyledRuns(\DOMNode $node, bool $bold, bool $italic, array &$state): void
    {
        if ($node instanceof \DOMText) {
            $text = html_entity_decode($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            // Line breaks are carried solely by <br>/<div>; any literal newline or
            // tab the markup picked up collapses to a single space, matching how
            // the editor and a browser render inline whitespace.
            $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
            if ($text === '') {
                return;
            }

            $state['current'][] = [
                'text' => $this->toLatin1($text),
                'style' => $this->captionStyleToken($bold, $italic),
            ];
            $state['lastWasBreak'] = false;

            return;
        }

        if (!$node instanceof \DOMElement) {
            return;
        }

        $tagName = strtolower($node->tagName);

        if ($tagName === 'br') {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;

            return;
        }

        $childBold = $bold || in_array($tagName, ['strong', 'b'], true);
        $childItalic = $italic || in_array($tagName, ['em', 'i'], true);
        $isBlock = in_array($tagName, ['div', 'p'], true);

        // A block element starts on its own line: flush any pending inline content
        // before descending into it.
        if ($isBlock && !$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;
        }

        foreach ($node->childNodes as $childNode) {
            $this->collectCaptionStyledRuns($childNode, $childBold, $childItalic, $state);
        }

        // ...and it ends the line it occupied, unless a <br> already closed it.
        if ($isBlock && !$state['lastWasBreak']) {
            $state['lines'][] = $state['current'];
            $state['current'] = [];
            $state['lastWasBreak'] = true;
        }
    }

    private function captionStyleToken(bool $bold, bool $italic): string
    {
        return ($bold ? 'B' : '') . ($italic ? 'I' : '');
    }

    /**
     * Drop blank lines from the start and end of a caption so leading/trailing
     * empty editor lines do not push the caption off-centre.
     *
     * @param array<int, array<int, array{text: string, style: string}>> $lines
     * @return array<int, array<int, array{text: string, style: string}>>
     */
    private function trimBlankStyledLines(array $lines): array
    {
        while ($lines !== [] && $lines[array_key_first($lines)] === []) {
            array_shift($lines);
        }
        while ($lines !== [] && $lines[array_key_last($lines)] === []) {
            array_pop($lines);
        }

        return array_values($lines);
    }

    public function sanitizeCaptionMarkup(?string $captionText): ?string
    {
        $captionText = trim((string) $captionText);
        if ($captionText === '') {
            return null;
        }

        $captionText = preg_replace("/\r\n?/", "\n", $captionText) ?? $captionText;
        if (preg_match('/<[^>]+>/', $captionText) !== 1) {
            return $captionText;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $captionText . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $sanitized = trim($this->sanitizeCaptionHtmlChildren($root));

        return $sanitized !== '' ? $sanitized : null;
    }

    private function sanitizeCaptionHtmlChildren(\DOMNode $node): string
    {
        $buffer = '';
        foreach ($node->childNodes as $childNode) {
            $buffer .= $this->sanitizeCaptionHtmlNode($childNode);
        }

        return $buffer;
    }

    private function sanitizeCaptionHtmlNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tagName = strtolower($node->tagName);
        $content = $this->sanitizeCaptionHtmlChildren($node);

        return match ($tagName) {
            'strong', 'b' => '<strong>' . $content . '</strong>',
            'em', 'i' => '<em>' . $content . '</em>',
            'br' => '<br>',
            'div', 'p' => '<div>' . ($content !== '' ? $content : '<br>') . '</div>',
            default => $content,
        };
    }

    private function setPdfCaptionFont(Fpdi $pdf, float $size, string $style = ''): bool
    {
        $fontDir = resource_path('fonts');
        $style = strtoupper($style);
        $fontMap = [
            '' => 'Montserrat-Regular.php',
            'B' => 'Montserrat-Bold.php',
            'I' => 'Montserrat-Italic.php',
            'BI' => 'Montserrat-BoldItalic.php',
        ];
        $fontFile = $fontMap[$style] ?? $fontMap[''];
        $fontDefinition = $fontDir . DIRECTORY_SEPARATOR . $fontFile;

        if (is_file($fontDefinition)) {
            $pdf->AddFont('Montserrat', $style, $fontFile, $fontDir);
            $pdf->SetFont('Montserrat', $style, $size);
            return true;
        }

        $regularDefinition = $fontDir . DIRECTORY_SEPARATOR . 'Montserrat-Regular.php';
        if (is_file($regularDefinition)) {
            $pdf->AddFont('Montserrat', '', 'Montserrat-Regular.php', $fontDir);
            $pdf->SetFont('Montserrat', '', $size);
            return true;
        }

        $fallbackStyle = in_array($style, ['B', 'I', 'BI'], true) ? $style : '';
        $pdf->SetFont('Helvetica', $fallbackStyle, $size);
        return false;
    }

    private function hasPdfCaptionFontDefinition(string $style = ''): bool
    {
        $style = strtoupper($style);
        $fontMap = [
            '' => 'Montserrat-Regular.php',
            'B' => 'Montserrat-Bold.php',
            'I' => 'Montserrat-Italic.php',
            'BI' => 'Montserrat-BoldItalic.php',
        ];

        return is_file(resource_path('fonts/' . ($fontMap[$style] ?? $fontMap[''])));
    }

    /**
     * Wrap a single caption line (a list of styled runs) into rendered visual
     * lines that each fit within $maxWidth.
     *
     * @param array<int, array{text: string, style: string}> $runs
     */
    private function buildStyledPdfLines(Fpdi $pdf, array $runs, float $maxWidth, float $fontSize): array
    {
        $lines = [];
        $currentLine = [];
        $currentWidth = 0.0;

        foreach ($runs as $run) {
            $pieces = preg_split('/(\s+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($pieces as $piece) {
                $isWhitespace = trim($piece) === '';
                if ($isWhitespace) {
                    if ($currentLine === [] || end($currentLine)['is_space']) {
                        continue;
                    }
                    $spaceWidth = $this->measurePdfCaptionText($pdf, ' ', $fontSize, $run['style']);
                    $currentLine[] = ['text' => ' ', 'style' => $run['style'], 'width' => $spaceWidth, 'is_space' => true];
                    $currentWidth += $spaceWidth;
                    continue;
                }

                $wordPieces = $this->splitPdfWordToWidthWithStyle($pdf, $piece, $maxWidth, $fontSize, $run['style']);
                foreach ($wordPieces as $wordPiece) {
                    $wordWidth = $this->measurePdfCaptionText($pdf, $wordPiece, $fontSize, $run['style']);

                    if ($currentLine !== [] && $currentWidth + $wordWidth > $maxWidth) {
                        while ($currentLine !== [] && end($currentLine)['is_space']) {
                            $spaceToken = array_pop($currentLine);
                            $currentWidth -= $spaceToken['width'];
                        }
                        if ($currentLine !== []) {
                            $lines[] = ['tokens' => $currentLine, 'width' => $currentWidth];
                        }
                        $currentLine = [];
                        $currentWidth = 0.0;
                    }

                    $currentLine[] = ['text' => $wordPiece, 'style' => $run['style'], 'width' => $wordWidth, 'is_space' => false];
                    $currentWidth += $wordWidth;
                }
            }
        }

        while ($currentLine !== [] && end($currentLine)['is_space']) {
            $spaceToken = array_pop($currentLine);
            $currentWidth -= $spaceToken['width'];
        }
        if ($currentLine !== []) {
            $lines[] = ['tokens' => $currentLine, 'width' => $currentWidth];
        }

        return $lines;
    }

    private function renderStyledPdfLines(
        Fpdi $pdf,
        array $lines,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        string $align,
        float $fontSize
    ): float {
        $currentY = $y;
        $lineCount = count($lines);

        foreach ($lines as $index => $line) {
            $lineWidth = (float) ($line['width'] ?? 0.0);
            $tokens = (array) ($line['tokens'] ?? []);
            $cursorX = match ($align) {
                'R' => $x + max(0.0, $width - $lineWidth),
                'C' => $x + max(0.0, ($width - $lineWidth) / 2),
                default => $x,
            };

            $spaceCount = count(array_filter($tokens, fn (array $token) => $token['is_space'] ?? false));
            $extraSpace = ($align === 'J' && $index < $lineCount - 1 && $spaceCount > 0)
                ? max(0.0, ($width - $lineWidth) / $spaceCount)
                : 0.0;

            foreach ($tokens as $token) {
                if ($token['is_space']) {
                    $cursorX += $token['width'] + $extraSpace;
                    continue;
                }

                $this->drawPdfCaptionToken($pdf, $cursorX, $currentY, $lineHeight, $token['text'], $token['style'], $fontSize);
                $cursorX += $token['width'];
            }

            $currentY += $lineHeight;
        }

        return $currentY;
    }

    private function drawPdfCaptionToken(
        Fpdi $pdf,
        float $x,
        float $y,
        float $lineHeight,
        string $text,
        string $style,
        float $fontSize
    ): void {
        $usesMontserrat = $this->setPdfCaptionFont($pdf, $fontSize, $style);
        $pdf->SetXY($x, $y);
        $pdf->Write($lineHeight, $text);

        if ($usesMontserrat && str_contains($style, 'B') && !$this->hasPdfCaptionFontDefinition($style)) {
            $boldOffsets = [
                [0.12, 0.00],
                [0.24, 0.00],
                [0.12, 0.06],
                [0.24, 0.06],
            ];

            foreach ($boldOffsets as [$offsetX, $offsetY]) {
                $pdf->SetXY($x + $offsetX, $y + $offsetY);
                $pdf->Write($lineHeight, $text);
            }
        }
    }

    private function measurePdfCaptionText(Fpdi $pdf, string $text, float $fontSize, string $style = ''): float
    {
        $this->setPdfCaptionFont($pdf, $fontSize, $style);
        return $pdf->GetStringWidth($text);
    }

    private function splitPdfWordToWidthWithStyle(Fpdi $pdf, string $word, float $maxWidth, float $fontSize, string $style = ''): array
    {
        if ($word === '' || $this->measurePdfCaptionText($pdf, $word, $fontSize, $style) <= $maxWidth) {
            return [$word];
        }

        $segments = [];
        $current = '';
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $candidate = $current . $character;
            if ($current !== '' && $this->measurePdfCaptionText($pdf, $candidate, $fontSize, $style) > $maxWidth) {
                $segments[] = $current;
                $current = $character;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments === [] ? [$word] : $segments;
    }


    private function stampRegionalDirectorSignatureBlock(Fpdi $pdf, array $pageSize): void
    {
        $this->stampRegionalDirectorESign($pdf, $pageSize);
    }

    private function stampRegionalDirectorESign(Fpdi $pdf, array $pageSize): ?array
    {
        $esignPath = $this->resolveRegionalDirectorESignPath();
        if (!$esignPath) {
            return null;
        }

        $boxWidth = (float) env('CERT_RD_ESIGN_WIDTH', 84);
        $boxHeight = (float) env('CERT_RD_ESIGN_HEIGHT', 28);
        if ($boxWidth <= 0 || $boxHeight <= 0) {
            return null;
        }

        [$width, $height] = $this->fitImageWithinBox($esignPath, $boxWidth, $boxHeight);

        $xEnv = env('CERT_RD_ESIGN_X');
        $yEnv = env('CERT_RD_ESIGN_Y');
        $yOffsetEnv = env('CERT_RD_ESIGN_Y_OFFSET', 0);
        $yOffset = is_numeric($yOffsetEnv) ? (float) $yOffsetEnv : 0.0;

        $x = is_numeric($xEnv)
            ? (float) $xEnv
            : (($pageSize['width'] - $boxWidth) / 2);
        $y = is_numeric($yEnv)
            ? (float) $yEnv
            : ($pageSize['height'] - $boxHeight - 18);
        $y += $yOffset;

        $margin = 5.0;
        $maxX = max($margin, $pageSize['width'] - $margin - $boxWidth);
        $maxY = max($margin, $pageSize['height'] - $margin - $boxHeight);
        $x = min(max($x, $margin), $maxX);
        $y = min(max($y, $margin), $maxY);

        $drawX = $x + (($boxWidth - $width) / 2);
        $drawY = $y + (($boxHeight - $height) / 2);

        $pdf->Image($esignPath, $drawX, $drawY, $width, $height);

        return [
            'x' => $drawX,
            'y' => $drawY,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function fitImageWithinBox(string $imagePath, float $boxWidth, float $boxHeight): array
    {
        $imageSize = @getimagesize($imagePath);
        $nativeWidth = (float) ($imageSize[0] ?? 0);
        $nativeHeight = (float) ($imageSize[1] ?? 0);

        if ($nativeWidth <= 0 || $nativeHeight <= 0) {
            return [$boxWidth, $boxHeight];
        }

        $scale = min($boxWidth / $nativeWidth, $boxHeight / $nativeHeight);
        if ($scale <= 0 || !is_finite($scale)) {
            return [$boxWidth, $boxHeight];
        }

        return [
            max(0.1, $nativeWidth * $scale),
            max(0.1, $nativeHeight * $scale),
        ];
    }

    private function resolveRegionalDirectorESignPath(): ?string
    {
        return RegionalDirectorSignatory::resolvedPath();
    }

    /**
     * Queue delivery emails, marking rows that cannot be emailed.
     *
     * @param  array<int, Certificate>  $certificates
     * @return array{0: int, 1: int}  [queued, skipped]
     */
    public function queueGeneratedCertificateEmails(array $certificates): array
    {
        $queued = 0;
        $skipped = 0;

        foreach ($certificates as $certificate) {
            if (! $certificate instanceof Certificate) {
                continue;
            }

            $email = trim((string) ($certificate->email ?? ''));
            if ($email === '') {
                $certificate->forceFill([
                    'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL,
                    'email_failure_message' => 'Certificate recipient does not have an email address.',
                    'email_queued_at' => null,
                    'email_last_attempt_at' => null,
                    'email_sent_at' => null,
                    'email_failed_at' => null,
                ])->save();
                $skipped++;
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $certificate->forceFill([
                    'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL,
                    'email_failure_message' => 'Certificate recipient email address is invalid.',
                    'email_queued_at' => null,
                    'email_last_attempt_at' => null,
                    'email_sent_at' => null,
                    'email_failed_at' => null,
                ])->save();
                $skipped++;
                continue;
            }

            $certificate->forceFill([
                'email_delivery_status' => Certificate::EMAIL_STATUS_QUEUED,
                'email_queued_at' => now(),
                'email_last_attempt_at' => null,
                'email_sent_at' => null,
                'email_failed_at' => null,
                'email_failure_message' => null,
            ])->save();

            SendCertificateEmailJob::dispatch($certificate->id);
            $queued++;
        }

        return [$queued, $skipped];
    }
}
