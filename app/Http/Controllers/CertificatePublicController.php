<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Support\RegionalDirectorSignatory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificatePublicController extends Controller
{
    public function verify(Request $request)
    {
        $t = $request->query('t');

        $cert = null;
        $found = false;
        $code = null;
        $reason = null;

        if (!$t) {
            $reason = 'Missing token.';
            return view('verify.show', compact('cert','found','code','reason'));
        }

        $cert = Certificate::where('public_token', $t)->first();

        if (!$cert) {
            $reason = 'Token not found.';
            return view('verify.show', compact('cert','found','code','reason'));
        }

        $found = true;
        $code = $cert->certificate_code;

        return view('verify.show', compact('cert','found','code','reason'));
    }

    public function print(Request $request)
    {
        $t = $request->query('t');
        if (!$t) {
            abort(404, 'Missing token.');
        }

        $cert = Certificate::where('public_token', $t)->firstOrFail();

        if (!$cert->isValid()) {
            abort(403, 'Certificate is not valid for print.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $verifyUrl = $baseUrl . route('cert.verify', ['t' => $cert->public_token], false);
        $regionalDirectorSignatory = RegionalDirectorSignatory::viewData();

        return view('certificate.print', compact('cert', 'verifyUrl', 'regionalDirectorSignatory'));
    }

    public function download(Request $request)
    {
        $t = $request->query('t');

        if (!$t) {
            abort(404, 'Missing token.');
        }

        $cert = Certificate::where('public_token', $t)->firstOrFail();

        if (!$cert->isValid()) {
            abort(403, 'Certificate is not valid for download.');
        }

        if (empty($cert->stamped_pdf_path)) {
            abort(404, 'Stamped PDF not available.');
        }

        $storage = $this->resolveCertificateStorage((string) $cert->stamped_pdf_path);
        if (!$storage) {
            abort(404, 'File missing in storage.');
        }

        $downloadName = $cert->certificate_code . '.pdf';
        return $storage['disk']->download($storage['path'], $downloadName);
    }

    private function resolveCertificateStorage(string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return [
                'disk' => $localDisk,
                'path' => $path,
            ];
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($path)) {
            return [
                'disk' => $publicDisk,
                'path' => $path,
            ];
        }

        return null;
    }
}
