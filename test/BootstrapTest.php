<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\Bootstrap;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/dapur-multiwebsite-uji-' . bin2hex(random_bytes(6));
        mkdir($this->folder . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/config/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->folder . '/config');
        rmdir($this->folder);
    }

    public function testIntiDenganKaitDidukung(): void
    {
        file_put_contents($this->folder . '/config/config.php', '<?php ...(DAPUR_CONFIG_PROVIDERS) ...');

        self::assertTrue(Bootstrap::intiMendukung($this->folder));
    }

    public function testIntiTanpaKaitTidakDidukung(): void
    {
        file_put_contents($this->folder . '/config/config.php', '<?php // Dapur 2.3');

        self::assertFalse(Bootstrap::intiMendukung($this->folder));
    }

    public function testIntiTanpaConfigTidakDidukung(): void
    {
        self::assertFalse(Bootstrap::intiMendukung($this->folder));
    }

    public function testTanpaDaftarWebsiteMultiWebsiteTidakAktif(): void
    {
        self::assertNull(Bootstrap::bacaDaftar($this->folder));
    }

    public function testDaftarWebsiteDibaca(): void
    {
        file_put_contents($this->folder . '/config/multiwebsite.local.php', "<?php return ['dashboard' => ['dash.uji']];");

        self::assertSame(['dashboard' => ['dash.uji']], Bootstrap::bacaDaftar($this->folder));
    }
}
