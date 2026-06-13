<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\HederaService;
use App\Support\RegionalDirectorSignatory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificatePublicController extends Controller
{
    public function verify(Request $request, HederaService $hedera)
    {
        $t = $request->query('t');

        $cert = null;
        $found = false;
        $code = null;
        $reason = null;
        $blockchain = null;
        $blockchainExplorerUrl = null;

        if (!$t) {
            $reason = 'Missing token.';
            return view('verify.show', compact('cert','found','code','reason','blockchain','blockchainExplorerUrl'));
        }

        $cert = Certificate::where('public_token', $t)->first();

        if (!$cert) {
            $reason = 'Token not found.';
            return view('verify.show', compact('cert','found','code','reason','blockchain','blockchainExplorerUrl'));
        }

        $found = true;
        $code = $cert->certificate_code;

        // Only surface the blockchain panel when the feature is on and the
        // certificate is valid. Verification is read-only and never throws.
        if ($hedera->enabled() && $cert->isValid() && $cert->isAnchored()) {
            $blockchain = $hedera->verifyCertificate($cert);
            $blockchainExplorerUrl = $hedera->explorerTopicUrl($cert->blockchain_topic_id);
        }

        return view('verify.show', compact('cert','found','code','reason','blockchain','blockchainExplorerUrl'));
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

    public function preview(Request $request)
    {
        $t = $request->query('t');

        if (!$t) {
            abort(404, 'Missing token.');
        }

        $cert = Certificate::where('public_token', $t)->firstOrFail();

        if (!$cert->isValid()) {
            abort(403, 'Certificate is not valid for preview.');
        }

        if (empty($cert->stamped_pdf_path)) {
            abort(404, 'Stamped PDF not yet available.');
        }

        $storage = $this->resolveCertificateStorage((string) $cert->stamped_pdf_path);
        if (!$storage) {
            abort(404, 'File missing in storage.');
        }

        return $storage['disk']->response($storage['path'], null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cert->certificate_code . '.pdf"',
        ]);
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
