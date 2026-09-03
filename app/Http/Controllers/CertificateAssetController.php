<?php

namespace App\Http\Controllers;

use App\Support\RegionalDirectorSignatory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CertificateAssetController extends Controller
{
    /**
     * Stream the Regional Director e-signature for the on-screen certificate.
     *
     * The stamped PDFs read the image straight off disk, so they were never
     * affected by the missing `public/storage` symlink; only the HTML view,
     * which needs a URL, was left with a broken image.
     */
    public function regionalDirectorSignature(): Response
    {
        $path = RegionalDirectorSignatory::resolvedPath();
        if ($path === null) {
            abort(404);
        }

        return (new BinaryFileResponse($path))
            ->setPublic()
            ->setMaxAge(86400);
    }
}
