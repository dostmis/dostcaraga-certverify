<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CertificateAdminController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class CertificateCustomDostProjectTest extends TestCase
{
    public function test_sscp_allows_a_custom_dost_project_value(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'validatedCertificatePayload');
        $method->setAccessible(true);

        [$data, $participants] = $method->invoke($controller, $this->makeRequest([
            'dost_program' => 'SSCP (Smart and Sustainable Communities Program)',
            'dost_project' => 'Others',
            'dost_project_other' => 'Smart Barangay Innovation Pilot',
        ]));

        $this->assertSame('Smart Barangay Innovation Pilot', $data['dost_project']);
        $this->assertNull($data['project_code']);
        $this->assertSame('Project Funds', $data['source_of_funds']);
        $this->assertCount(1, $participants);
        $this->assertSame('Juan Dela Cruz', $participants[0]['name']);
    }

    public function test_custom_dost_project_option_is_rejected_for_non_sscp_programs(): void
    {
        $controller = app(CertificateAdminController::class);
        $method = new ReflectionMethod($controller, 'validatedCertificatePayload');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);

        try {
            $method->invoke($controller, $this->makeRequest([
                'dost_program' => 'LGIA (Local Grants-in-Aid Program)',
                'dost_project' => 'Others',
                'dost_project_other' => 'Custom LGIA Project',
            ]));
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dost_project', $exception->errors());
            $this->assertSame(
                'Others, please specify is only available for SSCP.',
                $exception->errors()['dost_project'][0]
            );

            throw $exception;
        }
    }

    private function makeRequest(array $overrides = []): Request
    {
        $payload = array_merge([
            'training_title' => 'Community Innovation Workshop',
            'activity_type' => 'Training',
            'certificate_type' => 'Certificate of Participation',
            'recipient_type' => 'Participant',
            'venue' => 'Butuan City',
            'topic' => 'Food',
            'training_date_from' => '2026-03-01',
            'training_date_to' => '2026-03-02',
            'number_of_training_hours' => 8,
            'dost_program' => 'SSCP (Smart and Sustainable Communities Program)',
            'dost_project' => 'SMARTER AGUSAN: Deploying a Rural Model for Electric Mobility and Charging Infrastructure in Buenavista, Agusan del Norte (E-Move)',
            'pillar' => 'Human Well-Being Promoted',
            'source_of_funds' => 'Project Funds',
            'issuing_office' => 'DOST Caraga - Fields Operation Division',
        ], $overrides);

        $request = Request::create('/admin/certificates/preview', 'POST', $payload);
        $request->files->set('participants_file', UploadedFile::fake()->createWithContent(
            'participants.csv',
            "name\nJuan Dela Cruz\n"
        ));
        $request->files->set('certificate_pdf_shared', UploadedFile::fake()->createWithContent(
            'template.pdf',
            $this->minimalPdf()
        ));

        return $request;
    }

    private function minimalPdf(): string
    {
        return <<<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] >>
endobj
xref
0 4
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
trailer
<< /Size 4 /Root 1 0 R >>
startxref
178
%%EOF
PDF;
    }
}
