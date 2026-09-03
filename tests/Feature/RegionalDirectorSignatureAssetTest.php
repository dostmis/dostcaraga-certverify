<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\RegionalDirectorSignatory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The signature lives on the `public` disk, which is only reachable over HTTP
 * when `public/storage` is symlinked. That symlink is deliberately absent —
 * it would also expose the issued certificate PDFs stored beside the
 * signature — so the on-screen certificate is served through its own route.
 */
class RegionalDirectorSignatureAssetTest extends TestCase
{
    use RefreshDatabase;

    private ?string $stubSignaturePath = null;

    public function test_the_signature_is_served_without_the_public_storage_symlink(): void
    {
        $this->withStubSignatureImage();

        $response = $this->get(route('cert.assets.rd-signature'));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $this->assertSame(
            realpath($this->stubSignaturePath),
            realpath($response->baseResponse->getFile()->getPathname()),
            'The route should serve the configured signature file itself.'
        );
    }

    public function test_a_missing_signature_file_is_a_404_rather_than_an_error(): void
    {
        Setting::setValue(RegionalDirectorSignatory::KEY_ENABLED, '1');
        Setting::setValue(RegionalDirectorSignatory::KEY_PATH, 'certificates/signatories/does-not-exist.png');

        $this->get(route('cert.assets.rd-signature'))->assertNotFound();
    }

    public function test_the_route_is_closed_while_the_signature_is_disabled(): void
    {
        $this->withStubSignatureImage();
        Setting::setValue(RegionalDirectorSignatory::KEY_ENABLED, '0');

        $this->get(route('cert.assets.rd-signature'))->assertNotFound();
    }

    public function test_the_view_url_points_at_the_route_and_not_the_public_disk(): void
    {
        $this->withStubSignatureImage();

        $viewData = RegionalDirectorSignatory::viewData();

        $this->assertTrue($viewData['has_image']);
        $this->assertSame(route('cert.assets.rd-signature'), $viewData['image_url']);
        $this->assertStringNotContainsString(
            '/storage/',
            (string) $viewData['image_url'],
            'Linking through /storage/ is what left the signature broken in the first place.'
        );
    }

    public function test_no_url_is_offered_when_the_signature_cannot_be_resolved(): void
    {
        Setting::setValue(RegionalDirectorSignatory::KEY_ENABLED, '1');
        Setting::setValue(RegionalDirectorSignatory::KEY_PATH, 'certificates/signatories/does-not-exist.png');

        $viewData = RegionalDirectorSignatory::viewData();

        $this->assertFalse($viewData['has_image']);
        $this->assertNull($viewData['image_url']);
    }

    /**
     * Points the setting at a throwaway PNG so the assertions do not depend on
     * a deployed signature file.
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

        parent::tearDown();
    }
}
