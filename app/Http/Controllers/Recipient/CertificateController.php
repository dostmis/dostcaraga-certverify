<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $recipient = $request->user();

        $certificates = Certificate::where('recipient_id', $recipient->id)
            ->orderByDesc('created_at')
            ->paginate(4);

        return view('recipient.certificates.index', compact('certificates', 'recipient'));
    }
}
