<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Support\RegionalDirectorSignatory;

class CertificatePrintController extends Controller
{
    public function show(string $code)
    {
        $cert = Certificate::where('certificate_code', $code)->firstOrFail();

        $baseUrl = rtrim((string) config('app.url'), '/');
        $verifyUrl = $baseUrl . route('cert.verify', ['t' => $cert->public_token], false);

        return view('certificate.print', [
            'cert' => $cert,
            'verifyUrl' => $verifyUrl,
            'regionalDirectorSignatory' => RegionalDirectorSignatory::viewData(),
        ]);
    }
}
