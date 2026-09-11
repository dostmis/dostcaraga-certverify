<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CertificateAdminController;
use App\Models\Setting;
use App\Models\User;
use App\Support\RegionalDirectorSignatory;
use App\Support\TemplateNameBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class CertificateLayoutOptionsTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Alignmentcheck';
    private const CODE = 'TEST-CODE-001';
    private const VERIFY_URL = 'https://certify.example.test/verify?t=layout-options';
    private const CAPTION_SENTENCE = 'for outstanding participation and exemplary commitment demonstrated '
        . 'throughout the capacity building activity conducted by the Department of Science and Technology '
        . 'Regional Office No. XIII. ';

    private ?string $stubSignaturePath = null;

    /** @var list<string> */
    private array $temporaryTemplates = [];

    public function test_payload_defaults_to_centered_name_and_visible_qr_labels(): void
    {
        [$data] = $this->validatePayload();

        $this->assertSame('center', $data['name_alignment']);
        $this->assertTrue($data['qr_show_code']);
        $this->assertTrue($data['qr_show_link']);
    }

    public function test_payload_keeps_the_chosen_name_alignment_and_qr_label_toggles(): void
    {
        [$data] = $this->validatePayload([
            'name_alignment' => 'right',
            'qr_show_code' => '0',
            'qr_show_link' => '0',
        ]);

        $this->assertSame('right', $data['name_alignment']);
        $this->assertFalse($data['qr_show_code']);
        $this->assertFalse($data['qr_show_link']);
    }

    public function test_unknown_name_alignment_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->validatePayload(['name_alignment' => 'diagonal']);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name_alignment', $exception->errors());

            throw $exception;
        }
    }

    public function test_training_payload_carries_the_layout_choices_to_the_endorsement(): void
    {
        $controller = app(CertificateAdminController::class);
        [$data] = $this->validatePayload([
            'name_alignment' => 'left',
            'qr_show_code' => '1',
            'qr_show_link' => '0',
        ]);

        $method = new ReflectionMethod($controller, 'buildTrainingPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($controller, $data);

        $this->assertSame('left', $payload['name_alignment']);
        $this->assertTrue($payload['qr_show_code']);
        $this->assertFalse($payload['qr_show_link']);
    }

    public function test_certificate_row_stores_the_layout_choices_used_for_stamping(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'createCertificate');
        $method->setAccessible(true);

        $cert = $method->invoke($controller, [
            'participant_name' => 'Juan Dela Cruz',
            'training_title' => 'Layout Options Workshop',
            'training_date' => '2026-08-01',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
            'name_alignment' => 'right',
            'qr_show_code' => false,
            'qr_show_link' => false,
        ]);

        $cert->refresh();

        $this->assertSame('right', $cert->name_alignment);
        $this->assertFalse($cert->qr_show_code);
        $this->assertFalse($cert->qr_show_link);
    }

    public function test_certificate_row_falls_back_to_the_legacy_layout_when_unspecified(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'createCertificate');
        $method->setAccessible(true);

        $cert = $method->invoke($controller, [
            'participant_name' => 'Juan Dela Cruz',
            'training_title' => 'Layout Options Workshop',
            'training_date' => '2026-08-01',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
        ]);

        $cert->refresh();

        $this->assertSame('center', $cert->name_alignment);
        $this->assertTrue($cert->qr_show_code);
        $this->assertTrue($cert->qr_show_link);
    }

    public function test_name_alignment_moves_the_participant_name_across_the_page(): void
    {
        $left = $this->nameOffsetFor('left');
        $center = $this->nameOffsetFor('center');
        $right = $this->nameOffsetFor('right');

        $this->assertLessThan($center, $left, 'Left alignment should sit left of centred.');
        $this->assertLessThan($right, $center, 'Centred alignment should sit left of right-aligned.');
    }

    public function test_nudging_the_name_shifts_it_by_one_pixel_per_step(): void
    {
        $base = $this->nameOffsetFor('center');
        $plusTen = $this->nameOffsetFor('center', nameOffsetPx: 10);
        $minusTen = $this->nameOffsetFor('center', nameOffsetPx: -10);

        // pdftotext reports PDF points, so 10 CSS pixels is 10 * 72/96 points.
        $this->assertEqualsWithDelta(7.5, $plusTen - $base, 0.35, 'A +10px nudge should move the name right by 7.5pt.');
        $this->assertEqualsWithDelta(-7.5, $minusTen - $base, 0.35, 'A -10px nudge should move the name left by 7.5pt.');
    }

    public function test_a_single_pixel_nudge_is_applied_rather_than_ignored(): void
    {
        $base = $this->nameOffsetFor('center');
        $nudged = $this->nameOffsetFor('center', nameOffsetPx: 1);

        $this->assertGreaterThan($base, $nudged, 'One click should move the name right.');
        $this->assertEqualsWithDelta(0.75, $nudged - $base, 0.3, 'One pixel is 0.75pt.');
    }

    public function test_offsets_are_clamped_to_the_supported_range(): void
    {
        [$data] = $this->validatePayload([
            'name_offset_x' => 5,
            'signature_offset_x' => -12,
        ]);

        $this->assertSame(5, $data['name_offset_x']);
        $this->assertSame(-12, $data['signature_offset_x']);

        $this->expectException(ValidationException::class);
        $this->validatePayload(['name_offset_x' => 99999]);
    }

    public function test_a_cleared_offset_field_validates_as_no_nudge(): void
    {
        [$data] = $this->validatePayload([
            'name_offset_x' => '',
            'signature_offset_x' => null,
        ]);

        $this->assertSame(0, $data['name_offset_x']);
        $this->assertSame(0, $data['signature_offset_x']);
    }

    public function test_a_typed_offset_reaches_the_rendered_pdf(): void
    {
        [$data] = $this->validatePayload(['name_offset_x' => '-24']);
        $this->assertSame(-24, $data['name_offset_x']);

        $base = $this->nameOffsetFor('center');
        $typed = $this->nameOffsetFor('center', nameOffsetPx: $data['name_offset_x']);

        // -24 CSS pixels is -18pt in the rendered page.
        $this->assertEqualsWithDelta(-18.0, $typed - $base, 0.4);
    }

    public function test_non_numeric_offsets_fall_back_to_no_nudge(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'normalizeOffsetPx');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($controller, null));
        $this->assertSame(0, $method->invoke($controller, ''));
        $this->assertSame(0, $method->invoke($controller, 'left'));
        $this->assertSame(-3, $method->invoke($controller, '-3'));
        $this->assertSame(200, $method->invoke($controller, 9999));
        $this->assertSame(-200, $method->invoke($controller, -9999));
    }

    public function test_signature_nudge_moves_the_stamped_esign(): void
    {
        $this->withStubSignatureImage();

        $base = $this->signatureDrawX(0);
        $this->assertNotNull($base, 'The stub signature image should have been picked up.');

        // 20 CSS pixels is 20 * 25.4/96 mm, the unit FPDI positions images in.
        $this->assertEqualsWithDelta(5.2917, $this->signatureDrawX(20) - $base, 0.01, 'A +20px nudge should move the signature right.');
        $this->assertEqualsWithDelta(-5.2917, $this->signatureDrawX(-20) - $base, 0.01, 'A -20px nudge should move the signature left.');
        $this->assertEqualsWithDelta(0.2646, $this->signatureDrawX(1) - $base, 0.01, 'One click should move the signature one pixel.');
    }

    /**
     * Points the Regional Director e-sign setting at a throwaway PNG so the
     * placement assertions do not depend on a deployed signature file.
     */
    private function withStubSignatureImage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'esign_') . '.png';
        $image = imagecreatetruecolor(240, 80);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);
        imagedestroy($image);

        Setting::setValue(RegionalDirectorSignatory::KEY_ENABLED, '1');
        Setting::setValue(RegionalDirectorSignatory::KEY_PATH, $path);

        $this->stubSignaturePath = $path;
    }

    protected function tearDown(): void
    {
        if ($this->stubSignaturePath !== null) {
            @unlink($this->stubSignaturePath);
            $this->stubSignaturePath = null;
        }

        foreach ($this->temporaryTemplates as $template) {
            @unlink($template);
        }
        $this->temporaryTemplates = [];

        parent::tearDown();
    }

    /**
     * The e-sign is stamped as an image, so its placement is read from the
     * stamping method's returned geometry rather than from extracted text.
     */
    private function signatureDrawX(int $offsetPx): ?float
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'stampRegionalDirectorESign');
        $method->setAccessible(true);

        $pageSize = ['width' => 297.0, 'height' => 210.0];
        $pdf = new Fpdi();
        $pdf->AddPage('L', [$pageSize['width'], $pageSize['height']]);

        $placement = $method->invoke($controller, $pdf, $pageSize, $offsetPx);

        return $placement === null ? null : (float) $placement['x'];
    }

    /**
     * A name wider than the template's name band used to be drawn at a negative
     * X and clipped off both page edges, so it is now scaled down to fit.
     */
    public function test_an_overlong_name_is_scaled_to_stay_inside_the_page(): void
    {
        $long = 'Maria Concepcion Alejandra Villanueva-Bustamante Jr.';
        $box = $this->textBounds($this->render('center', false, false, name: $long));

        $this->assertGreaterThanOrEqual(0.0, $box['xMin'], 'The name must not start off the left edge.');
        $this->assertLessThanOrEqual($box['pageWidth'], $box['xMax'], 'The name must not run past the right edge.');
    }

    public function test_a_name_that_already_fits_keeps_the_requested_font_size(): void
    {
        $fitted = $this->fitName('Juan Dela Cruz');

        $this->assertSame(45.0, $fitted[0], 'A short name should not be shrunk.');
        $this->assertSame(['Juan Dela Cruz'], $fitted[1], 'A short name should stay on one line.');
    }

    /**
     * A name too wide for the band now takes a second line at close to full
     * size, instead of being scaled down to fit on one. The templates leave
     * 35mm clear between "is presented to" and the rule under the name, so the
     * room for that second line was there all along.
     */
    public function test_a_long_name_wraps_at_close_to_full_size(): void
    {
        [$size, $lines] = $this->fitName('Jose Protacio Rizal Mercado y Alonso Realonda');

        $this->assertCount(2, $lines, 'A name wider than the band should take a second line.');
        $this->assertGreaterThan(
            34.5,
            $size,
            'Wrapping should hold a larger size than shrinking onto a single line did.'
        );
    }

    public function test_a_wrapped_name_stays_clear_of_the_header_text(): void
    {
        foreach ([
            'Maria Concepcion Villanueva-Bustamante',
            'Jose Protacio Rizal Mercado y Alonso Realonda',
            str_repeat('Aaaaaaaaaa', 12),
        ] as $name) {
            [$size, $lines] = $this->fitName($name);

            $lineHeight = $size * (25.4 / 72) * 1.15;
            $ascent = ((count($lines) - 1) * $lineHeight) + ($size * (25.4 / 72) * 0.66);

            $this->assertLessThanOrEqual(
                26.0,
                $ascent,
                "The block for '{$name}' must not reach into \"is presented to\"."
            );
        }
    }

    public function test_a_two_line_name_is_split_evenly_rather_than_greedily(): void
    {
        [, $lines] = $this->fitName('Jose Protacio Rizal Mercado y Alonso Realonda');

        $this->assertCount(2, $lines);
        $this->assertNotSame(
            'Realonda',
            $lines[1],
            'A greedy wrap strands the last word on its own; the split should be evened out.'
        );
        $shorter = min(str_word_count($lines[0]), str_word_count($lines[1]));
        $this->assertGreaterThan(1, $shorter, 'Neither line should be left with a single word.');
    }

    public function test_a_name_with_no_break_points_still_wraps(): void
    {
        [$size, $lines] = $this->fitName(str_repeat('Aaaaaaaaaa', 12));

        $this->assertGreaterThan(1, count($lines), 'An unbreakable name must still be split to fit.');
        $this->assertGreaterThanOrEqual(20.0, $size, 'Shrinking should stop at the minimum legible size.');
    }

    /**
     * An overlong caption used to trip FPDF's automatic page break, turning one
     * certificate into dozens of blank pages.
     */
    public function test_an_overlong_caption_does_not_spawn_extra_pages(): void
    {
        $caption = '<p>' . str_repeat(self::CAPTION_SENTENCE, 12) . '</p>';
        $pdf = $this->render('center', false, false, caption: $caption);

        $this->assertSame(1, $this->pageCount($pdf), 'The certificate must stay a single page.');
    }

    public function test_a_long_caption_is_tightened_to_clear_the_signature(): void
    {
        $this->withStubSignatureImage();

        $caption = '<p>' . str_repeat(self::CAPTION_SENTENCE, 4) . 'Endmarker</p>';
        $boxes = $this->wordBoxes($this->render('center', false, false, caption: $caption, applyESign: true), 'yMax');

        $this->assertArrayHasKey('Endmarker', $boxes, 'The last caption line should be on the page.');

        // pdftotext reports points; the signature box top is resolved in mm.
        $signatureTopPt = $this->signatureBoxTop() * 72 / 25.4;
        $this->assertLessThan(
            $signatureTopPt,
            $boxes['Endmarker'],
            'The caption should be tightened rather than run over the signature.'
        );
    }

    public function test_a_caption_that_already_fits_keeps_the_standard_spacing(): void
    {
        [$fontSize, $lineHeight] = $this->fitCaption('<p>' . self::CAPTION_SENTENCE . '</p>', 60.0);

        $this->assertSame(13.2, $fontSize, 'A short caption should not be shrunk.');
        $this->assertSame(5.2, $lineHeight, 'A short caption should keep the standard line height.');
    }

    public function test_caption_font_and_line_spacing_shrink_together(): void
    {
        [$fontSize, $lineHeight] = $this->fitCaption(
            '<p>' . str_repeat(self::CAPTION_SENTENCE, 6) . '</p>',
            40.0
        );

        $this->assertLessThan(13.2, $fontSize, 'A long caption should be scaled down.');
        $this->assertGreaterThanOrEqual(8.0, $fontSize, 'Shrinking should stop at the minimum size.');
        $this->assertEqualsWithDelta(
            5.2 * ($fontSize / 13.2),
            $lineHeight,
            0.001,
            'Line spacing should track the font size so the block stays proportional.'
        );
    }

    /**
     * Designs with artwork down one edge need an asymmetric text area, so a
     * centred name lands on the middle of the clear space, not of the page.
     */
    public function test_asymmetric_margins_centre_the_name_on_the_usable_area(): void
    {
        $symmetric = $this->nameSpan(30.0, 30.0);
        $asymmetric = $this->nameSpan(8.0, 59.0);

        // 297mm page: symmetric centres on 148.5mm, the narrowed area on 123.0mm.
        $this->assertEqualsWithDelta(148.5, array_sum($symmetric) / 2, 2.0);
        $this->assertEqualsWithDelta(123.0, array_sum($asymmetric) / 2, 2.0);
        $this->assertLessThan(
            238.0,
            $asymmetric[1],
            'The name must stay left of the artwork edge the margins describe.'
        );
    }

    public function test_equal_margins_render_exactly_as_before(): void
    {
        $explicit = $this->nameSpan(30.0, 30.0);
        $defaulted = $this->nameSpan(30.0, null);

        $this->assertEqualsWithDelta($explicit[0], $defaulted[0], 0.01);
        $this->assertEqualsWithDelta($explicit[1], $defaulted[1], 0.01);
    }

    public function test_margins_that_leave_no_usable_width_fall_back_to_the_standard_band(): void
    {
        [$data] = $this->validatePayload([
            'name_margin_left' => 130,
            'name_margin_right' => 130,
        ]);

        $this->assertSame(30, $data['name_margin_left'], 'An unusable band should reset to the default.');
        $this->assertSame(30, $data['name_margin_right']);
    }

    public function test_margins_are_kept_and_stored_when_they_are_usable(): void
    {
        [$data] = $this->validatePayload([
            'name_margin_left' => 8,
            'name_margin_right' => 59,
        ]);

        $this->assertSame(8, $data['name_margin_left']);
        $this->assertSame(59, $data['name_margin_right']);

        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'createCertificate');
        $method->setAccessible(true);
        $cert = $method->invoke($controller, [
            'participant_name' => 'Juan Dela Cruz',
            'training_title' => 'Layout Options Workshop',
            'training_date' => '2026-08-01',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
            'name_margin_left' => 8,
            'name_margin_right' => 59,
        ]);

        $cert->refresh();
        $this->assertSame(8, $cert->name_margin_left);
        $this->assertSame(59, $cert->name_margin_right);
    }

    public function test_a_certificate_without_margins_defaults_to_the_standard_band(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'createCertificate');
        $method->setAccessible(true);
        $cert = $method->invoke($controller, [
            'participant_name' => 'Juan Dela Cruz',
            'training_title' => 'Layout Options Workshop',
            'training_date' => '2026-08-01',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
        ]);

        $cert->refresh();
        $this->assertSame(30, $cert->name_margin_left);
        $this->assertSame(30, $cert->name_margin_right);
    }

    /**
     * Horizontal span of the rendered name, in mm.
     *
     * @return array{0: float, 1: float}
     */
    private function nameSpan(float $marginLeft, ?float $marginRight): array
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'renderStampedPdf');
        $method->setAccessible(true);

        $report = null;
        $args = [
            public_path('templates/Participation.pdf'),
            'Bartholomew Featherstonehaugh',
            $marginLeft,
            110.0,
            45.0,
            'Times',
            'center',
            self::CODE,
            self::VERIFY_URL,
            false,
            null,
            'center',
            false,
            false,
            0,
            0,
            &$report,
            $marginRight,
        ];
        $pdf = $method->invokeArgs($controller, $args);

        $xml = $this->runPdfTool($pdf, ['pdftotext', '-bbox', '-f', '1', '-l', '1']);
        preg_match_all(
            '/<word xMin="([-0-9.]+)" yMin="[-0-9.]+" xMax="([-0-9.]+)" yMax="([-0-9.]+)">/',
            $xml,
            $matches,
            PREG_SET_ORDER
        );

        // Keep only the row the name baseline sits on (110mm == 311.8pt).
        $onNameRow = array_filter($matches, fn (array $m) => (float) $m[3] > 295.0 && (float) $m[3] < 325.0);
        $this->assertNotEmpty($onNameRow, 'The participant name should be extractable from the render.');

        $ptToMm = 25.4 / 72;

        return [
            min(array_map(fn (array $m) => (float) $m[1], $onNameRow)) * $ptToMm,
            max(array_map(fn (array $m) => (float) $m[2], $onNameRow)) * $ptToMm,
        ];
    }

    public function test_preview_all_renders_one_page_per_participant(): void
    {
        $names = ['Juan Dela Cruz', 'Maria Clara Santos-Villanueva', 'Jose Rizal'];
        $response = $this->postPreviewAll($names);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Preview-Participants', '3');
        $response->assertHeader('X-Preview-Total-Participants', '3');
        $response->assertHeader('X-Preview-Truncated', '0');

        $pdf = $response->getContent();
        $this->assertSame(3, $this->pageCount($pdf), 'One page per participant.');

        $text = $this->extractText($pdf);
        foreach ($names as $name) {
            $this->assertStringContainsString($name, $text, "{$name} should appear in the batch preview.");
        }
    }

    public function test_preview_all_reports_names_it_had_to_shrink(): void
    {
        $response = $this->postPreviewAll([
            'Juan Dela Cruz',
            'Maria Concepcion Alejandra Villanueva-Bustamante Jr.',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Preview-Participants', '2');
        // Only the long one needs scaling, so the reviewer is told it is 1 of 2.
        $response->assertHeader('X-Preview-Names-Shrunk', '1');
    }

    /**
     * A full batch is trimmed because the viewer, not the renderer, is the
     * bottleneck: the template's full-bleed background is re-rasterised on every
     * page. Nothing of ours runs in that tab to read the counter headers, so the
     * trim has to be legible from the filename alone.
     */
    public function test_an_oversized_batch_is_trimmed_and_says_so_in_the_filename(): void
    {
        $names = [];
        for ($i = 1; $i <= 40; $i++) {
            $names[] = 'Participant Number ' . $i;
        }

        $response = $this->postPreviewAll($names);

        $response->assertOk();
        $response->assertHeader('X-Preview-Participants', '25');
        $response->assertHeader('X-Preview-Total-Participants', '40');
        $response->assertHeader('X-Preview-Truncated', '1');
        $this->assertSame(25, $this->pageCount($response->getContent()));

        $this->assertStringContainsString(
            'first-25-of-40-participants',
            $response->headers->get('Content-Disposition'),
            'A trimmed preview should not be named as though it were the whole batch.'
        );
    }

    public function test_a_batch_within_the_cap_keeps_the_plain_filename(): void
    {
        $response = $this->postPreviewAll(['Juan Dela Cruz', 'Jose Rizal']);

        $response->assertOk();
        $response->assertHeader('X-Preview-Truncated', '0');
        $this->assertStringContainsString(
            'all-participants',
            $response->headers->get('Content-Disposition')
        );
    }

    /**
     * A template whose sub-heading sits low leaves no room for a second line, so
     * the name has to go back to one shrunken line rather than climbing into it.
     */
    public function test_a_heading_close_above_the_name_prevents_a_second_line(): void
    {
        $name = 'Surigao Del Norte State University - Del Carmen Campus';

        // Left-aligned, so the name shares the heading's column the way it does
        // on the real certificates; a centred name simply misses it sideways.
        [, $cramped] = $this->fitName($name, $this->inkMapWithHeadingAt(101.0), 'left');
        [, $roomy] = $this->fitName($name, $this->inkMapWithHeadingAt(78.0), 'left');

        $this->assertCount(1, $cramped, 'A heading just above the baseline leaves no room to wrap.');
        $this->assertGreaterThan(1, count($roomy), 'A heading further up should let the name wrap.');
    }

    /**
     * The regression that removed wrapping altogether: decoration beside the
     * name was measured across the whole band and read as a ceiling, so a
     * template with artwork down one side never wrapped.
     */
    public function test_decoration_beside_the_name_does_not_suppress_wrapping(): void
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297.0, 210.0]);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Text(30.0, 78.0, 'is proudly presented to');
        // A solid block down the right edge, level with the name.
        $pdf->SetFillColor(20, 20, 20);
        $pdf->Rect(250.0, 60.0, 45.0, 90.0, 'F');

        $path = tempnam(sys_get_temp_dir(), 'tpl_') . '.pdf';
        $pdf->Output('F', $path);
        $this->temporaryTemplates[] = $path;

        [, $lines] = $this->fitName(
            'Surigao Del Norte State University - Del Carmen Campus',
            TemplateNameBand::inkMap($path, 297.0, 210.0),
            'left'
        );

        $this->assertGreaterThan(1, count($lines), 'Artwork beside the name must not stop it wrapping.');
    }

    public function test_the_vertical_nudge_moves_the_name_down_the_page(): void
    {
        $base = $this->nameBaselineY(0);

        // pdftotext reports PDF points, so 20 CSS pixels is 20 * 72/96 points,
        // and yMax grows downward.
        $this->assertEqualsWithDelta(
            15.0,
            $this->nameBaselineY(20) - $base,
            0.35,
            'A +20px nudge should move the name 15pt down the page.'
        );
        $this->assertEqualsWithDelta(
            -15.0,
            $this->nameBaselineY(-20) - $base,
            0.35,
            'A -20px nudge should move the name 15pt up the page.'
        );
    }

    public function test_the_vertical_nudge_is_validated_and_normalised(): void
    {
        [$data] = $this->validatePayload(['name_offset_y' => '17']);
        $this->assertSame(17, $data['name_offset_y']);

        [$cleared] = $this->validatePayload(['name_offset_y' => '']);
        $this->assertSame(0, $cleared['name_offset_y'], 'A cleared field should read as no nudge.');

        $controller = app(CertificateAdminController::class);
        $normalize = new ReflectionMethod($controller, 'normalizeOffsetPx');
        $normalize->setAccessible(true);
        $this->assertSame(0, $normalize->invoke($controller, 'abc'), 'A non-numeric nudge normalises to zero.');
    }

    public function test_an_out_of_range_vertical_nudge_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->validatePayload(['name_offset_y' => '9999']);
    }

    /**
     * Vertical position of the participant name, read back from the rendered PDF
     * so the assertion reflects what actually lands on the page.
     */
    private function nameBaselineY(int $offsetYPx): float
    {
        $boxes = $this->wordBoxes(
            $this->render('center', false, false, nameOffsetYPx: $offsetYPx),
            'yMax'
        );
        $this->assertArrayHasKey(self::NAME, $boxes, 'Participant name missing from the render.');

        return $boxes[self::NAME];
    }

    public function test_preview_all_is_refused_without_permission(): void
    {
        $user = User::factory()->create(['role' => 'recipient']);

        $this->actingAs($user)
            ->post(route('admin.certs.preview-all'), $this->previewPayload(['Juan Dela Cruz']))
            ->assertForbidden();
    }

    public function test_every_participant_gets_its_own_fit_report_and_shares_one_qr(): void
    {
        $controller = app(CertificateAdminController::class);
        $build = new ReflectionMethod($controller, 'buildPreviewParticipants');
        $build->setAccessible(true);
        $render = new ReflectionMethod($controller, 'renderStampedPdfForParticipants');
        $render->setAccessible(true);

        $names = ['Jose Rizal', 'Maria Concepcion Alejandra Villanueva-Bustamante Jr.'];
        $participants = $build->invoke($controller, $names);

        $this->assertCount(1, array_unique(array_column($participants, 'verify_url')),
            'Preview pages should share one placeholder URL so the QR renders once.');
        $this->assertCount(2, array_unique(array_column($participants, 'code')),
            'Each preview page should still carry its own placeholder code.');

        $report = null;
        $args = [
            public_path('templates/Participation.pdf'),
            $participants,
            30.0,
            110.0,
            45.0,
            'Times',
            'center',
            false,
            null,
            'center',
            true,
            true,
            0,
            0,
            &$report,
            null,
        ];
        $render->invokeArgs($controller, $args);

        $this->assertCount(2, $report['participants']);
        $this->assertSame($names, array_column($report['participants'], 'name'));
        $this->assertFalse($report['participants'][0]['name_fit']['shrunk']);
        $this->assertTrue($report['participants'][1]['name_fit']['shrunk']);
    }

    /**
     * @param  list<string>  $names
     */
    private function postPreviewAll(array $names): \Illuminate\Testing\TestResponse
    {
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        return $this->actingAs($user)->post(
            route('admin.certs.preview-all'),
            $this->previewPayload($names)
        );
    }

    /**
     * A batch normally arrives as an uploaded participant list, so the preview
     * is exercised the same way.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    private function previewPayload(array $names): array
    {
        return array_merge($this->basePayload(), [
            'template_source' => 'default',
            'participant_source' => 'file',
            'participants_file' => UploadedFile::fake()->createWithContent(
                'participants.csv',
                "name\n" . implode("\n", $names) . "\n"
            ),
        ]);
    }

    public function test_the_renderer_reports_a_caption_that_does_not_fit(): void
    {
        $this->withStubSignatureImage();

        $fits = $this->layoutReport('<p>' . self::CAPTION_SENTENCE . '</p>');
        $this->assertFalse($fits['caption']['overflows'], 'A short caption should be reported as fitting.');
        $this->assertSame(13.2, $fits['caption']['font_size']);

        $overflows = $this->layoutReport('<p>' . str_repeat(self::CAPTION_SENTENCE, 12) . '</p>');
        $this->assertTrue($overflows['caption']['overflows'], 'An overlong caption should be reported.');
        $this->assertSame(8.0, $overflows['caption']['font_size'], 'It should be reported at the floor size.');
        $this->assertGreaterThan(
            $overflows['caption']['max_lines'],
            $overflows['caption']['lines'],
            'The report should say how many lines over the caption is.'
        );
    }

    public function test_the_renderer_reports_a_missing_regional_director_signature(): void
    {
        $this->withStubSignatureImage();
        $this->assertTrue($this->layoutReport(null)['signature_stamped']);

        // Point the setting at a file that is not there, the way a cleared or
        // stale upload leaves it.
        Setting::setValue(RegionalDirectorSignatory::KEY_PATH, 'certificates/signatories/does-not-exist.png');

        $this->assertFalse(
            $this->layoutReport(null)['signature_stamped'],
            'A signature that cannot be resolved must be reported, not silently skipped.'
        );
    }

    public function test_the_live_preview_advertises_the_fit_in_its_headers(): void
    {
        $this->withStubSignatureImage();
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $response = $this->actingAs($user)->post(route('admin.certs.live-preview'), [
            'template_source' => 'default',
            'certificate_type' => 'Certificate of Participation',
            'participant_name' => 'Juan Dela Cruz',
            'caption_text' => '<p>' . str_repeat(self::CAPTION_SENTENCE, 12) . '</p>',
            'caption_alignment' => 'center',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Caption-Overflow', '1');
        $response->assertHeader('X-Signature-Expected', '1');
        $response->assertHeader('X-Signature-Stamped', '1');
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutReport(?string $caption): array
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'renderStampedPdf');
        $method->setAccessible(true);

        $report = null;
        $args = [
            public_path('templates/Participation.pdf'),
            self::NAME,
            30.0,
            110.0,
            45.0,
            'Times',
            'center',
            self::CODE,
            self::VERIFY_URL,
            true,
            $caption,
            'center',
            true,
            true,
            0,
            0,
            &$report,
        ];
        $method->invokeArgs($controller, $args);

        return $report;
    }

    /**
     * @return array{0: float, 1: list<string>}
     */
    private function fitName(string $name, ?array $inkMap = null, string $alignment = 'center'): array
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'fitNameToBand');
        $method->setAccessible(true);

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297.13, 210.08]);

        return $method->invoke(
            $controller,
            $pdf,
            $name,
            'Times',
            45.0,
            297.13 - 60.0,
            $inkMap,
            110.0,
            $alignment,
            30.0
        );
    }

    /**
     * Ink map for a throwaway template whose sub-heading sits at $headingY,
     * letting the fit be exercised against real artwork rather than a number.
     */
    private function inkMapWithHeadingAt(float $headingY): ?array
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297.0, 210.0]);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Text(30.0, $headingY, 'is proudly presented to');

        $path = tempnam(sys_get_temp_dir(), 'tpl_') . '.pdf';
        $pdf->Output('F', $path);
        $this->temporaryTemplates[] = $path;

        return TemplateNameBand::inkMap($path, 297.0, 210.0);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function fitCaption(string $captionMarkup, float $maxHeight): array
    {
        $controller = app(CertificateAdminController::class);
        $toLines = new ReflectionMethod($controller, 'captionMarkupToStyledLines');
        $toLines->setAccessible(true);
        $fit = new ReflectionMethod($controller, 'fitCaptionToBox');
        $fit->setAccessible(true);

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', [297.13, 210.08]);

        $paragraphs = $toLines->invoke($controller, $captionMarkup);
        [$fontSize, $lineHeight] = $fit->invoke($controller, $pdf, $paragraphs, 297.13 - 80.0, $maxHeight);

        return [$fontSize, $lineHeight];
    }

    private function signatureBoxTop(): float
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'regionalDirectorESignBox');
        $method->setAccessible(true);

        $box = $method->invoke($controller, ['width' => 297.13, 'height' => 210.08], 0);
        $this->assertNotNull($box, 'The stub signature image should have been picked up.');

        return (float) $box['y'];
    }

    /**
     * @return array{xMin: float, xMax: float, pageWidth: float}
     */
    private function textBounds(string $pdf): array
    {
        $xml = $this->runPdfTool($pdf, ['pdftotext', '-bbox', '-f', '1', '-l', '1']);

        preg_match('/<page width="([0-9.]+)"/', $xml, $page);
        preg_match_all('/<word xMin="([-0-9.]+)" yMin="[-0-9.]+" xMax="([-0-9.]+)"/', $xml, $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches, 'The rendered page should contain extractable text.');

        return [
            'xMin' => min(array_map(fn (array $m) => (float) $m[1], $matches)),
            'xMax' => max(array_map(fn (array $m) => (float) $m[2], $matches)),
            'pageWidth' => (float) ($page[1] ?? 0.0),
        ];
    }

    private function pageCount(string $pdf): int
    {
        $info = $this->runPdfTool($pdf, ['pdfinfo']);
        if (!preg_match('/^Pages:\s+(\d+)/m', $info, $match)) {
            $this->markTestSkipped('pdfinfo (poppler-utils) is required to count rendered pages.');
        }

        return (int) $match[1];
    }

    public function test_qr_labels_are_omitted_when_both_toggles_are_off(): void
    {
        $withLabels = $this->extractText($this->render('center', true, true));
        $withoutLabels = $this->extractText($this->render('center', false, false));

        $this->assertStringContainsString(self::CODE, $withLabels);
        $this->assertStringContainsString('certify.example.test', $withLabels);

        $this->assertStringNotContainsString(self::CODE, $withoutLabels);
        $this->assertStringNotContainsString('certify.example.test', $withoutLabels);
    }

    public function test_qr_labels_can_be_toggled_independently(): void
    {
        $codeOnly = $this->extractText($this->render('center', true, false));
        $this->assertStringContainsString(self::CODE, $codeOnly);
        $this->assertStringNotContainsString('certify.example.test', $codeOnly);

        $linkOnly = $this->extractText($this->render('center', false, true));
        $this->assertStringNotContainsString(self::CODE, $linkOnly);
        $this->assertStringContainsString('certify.example.test', $linkOnly);
    }

    private function render(
        string $nameAlignment,
        bool $showQrCode,
        bool $showQrLink,
        int $nameOffsetPx = 0,
        ?string $name = null,
        ?string $caption = null,
        bool $applyESign = false,
        int $nameOffsetYPx = 0
    ): string {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'renderStampedPdf');
        $method->setAccessible(true);

        return $method->invoke(
            $controller,
            public_path('templates/Participation.pdf'),
            $name ?? self::NAME,
            30.0,
            110.0,
            45.0,
            'Times',
            $nameAlignment,
            self::CODE,
            self::VERIFY_URL,
            $applyESign,
            $caption,
            'center',
            $showQrCode,
            $showQrLink,
            $nameOffsetPx,
            nameOffsetYPx: $nameOffsetYPx
        );
    }

    /**
     * Horizontal offset of the participant name, read back from the rendered PDF
     * so the assertion reflects what actually lands on the page.
     */
    private function nameOffsetFor(string $nameAlignment, int $nameOffsetPx = 0): float
    {
        $boxes = $this->wordBoxes($this->render($nameAlignment, true, true, $nameOffsetPx));
        $this->assertArrayHasKey(self::NAME, $boxes, "Participant name missing from the {$nameAlignment} render.");

        return $boxes[self::NAME];
    }

    /**
     * @param  'xMin'|'yMax'  $edge  which bounding-box edge to report
     * @return array<string, float> word => coordinate
     */
    private function wordBoxes(string $pdf, string $edge = 'xMin'): array
    {
        $xml = $this->runPdfTool($pdf, ['pdftotext', '-bbox', '-f', '1', '-l', '1']);

        preg_match_all(
            '/<word xMin="([-0-9.]+)" yMin="[-0-9.]+" xMax="[-0-9.]+" yMax="([-0-9.]+)">([^<]*)<\/word>/',
            $xml,
            $matches,
            PREG_SET_ORDER
        );

        $boxes = [];
        foreach ($matches as $match) {
            $word = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!array_key_exists($word, $boxes)) {
                $boxes[$word] = (float) ($edge === 'yMax' ? $match[2] : $match[1]);
            }
        }

        return $boxes;
    }

    private function extractText(string $pdf): string
    {
        return $this->runPdfTool($pdf, ['pdftotext', '-layout']);
    }

    /**
     * @param  list<string>  $command
     */
    private function runPdfTool(string $pdf, array $command): string
    {
        $binary = $command[0];
        if (trim((string) shell_exec('command -v ' . escapeshellarg($binary))) === '') {
            $this->markTestSkipped("{$binary} (poppler-utils) is required to inspect rendered certificates.");
        }

        $input = tempnam(sys_get_temp_dir(), 'cert_layout_') . '.pdf';
        file_put_contents($input, $pdf);

        $command[] = $input;
        if ($binary === 'pdftotext') {
            // pdftotext needs an explicit destination; '-' streams to stdout.
            $command[] = '-';
        }

        $shell = implode(' ', array_map('escapeshellarg', $command));
        $output = (string) shell_exec($shell . ' 2>/dev/null');

        @unlink($input);

        return $output;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validatePayload(array $overrides = []): array
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'validatedCertificatePayload');
        $method->setAccessible(true);

        return $method->invoke($controller, $this->makeRequest($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRequest(array $overrides = []): Request
    {
        $payload = array_merge($this->basePayload(), $overrides);

        $request = Request::create('/admin/certificates', 'POST', $payload);
        $request->files->set('participants_file', UploadedFile::fake()->createWithContent(
            'participants.csv',
            "name\nJuan Dela Cruz\n"
        ));

        return $request;
    }

    /**
     * The training fields every certificate request needs, so individual tests
     * only state the layout values they actually care about.
     *
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'training_title' => 'Layout Options Workshop',
            'activity_type' => 'Training',
            'certificate_type' => 'Certificate of Participation',
            'recipient_type' => 'Participant',
            'venue' => 'Butuan City',
            'topic' => 'Food',
            'training_date_from' => '2026-08-01',
            'training_date_to' => '2026-08-02',
            'number_of_training_hours' => 8,
            'dost_program' => 'SSCP (Smart and Sustainable Communities Program)',
            'dost_project' => 'Others',
            'dost_project_other' => 'Certificate Layout Pilot',
            'pillar' => 'Human Well-Being Promoted',
            'source_of_funds' => 'Project Funds',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
        ];
    }
}
