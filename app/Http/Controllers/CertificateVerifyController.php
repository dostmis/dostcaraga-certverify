<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateVerifyController extends Controller
{
    public function show(Request $request)
    {
        $token = $request->query('t');

        if (!$token) {
            return view('verify.show', [
                'found' => false,
                'code' => null,
                'cert' => null,
                'reason' => 'Missing verification token.',
            ]);
        }

        $cert = Certificate::where('public_token', $token)->first();

        if (!$cert) {
            return view('verify.show', [
                'found' => false,
                'code' => null,
                'cert' => null,
                'reason' => 'Invalid or unknown token.',
            ]);
        }

        return view('verify.show', [
            'found' => true,
            'code' => $cert->certificate_code,
            'cert' => $cert,
            'reason' => null,
        ]);
    }
}
