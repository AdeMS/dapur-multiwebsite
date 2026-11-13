<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite\Test;

use AdeMS\Dapur\MultiWebsite\Website;
use PHPUnit\Framework\TestCase;

final class WebsiteTest extends TestCase
{
    public function testFolderDomainMenyimpanDataDiFolderWebsite(): void
    {
        $website = Website::dariFolderDomain('contoh.com', '/srv/contoh.com/');

        self::assertSame('/srv/contoh.com/data/cache/config-cache.php', $website->fileCacheKonfigurasi());
        self::assertSame('/srv/contoh.com/data/cache/app', $website->folderCacheAplikasi());
        self::assertSame('/srv/contoh.com/data/log/error.log', $website->fileLogError());
    }

    public function testTerpusatMenyimpanDataDiFolderDataInti(): void
    {
        $website = Website::dariInti('contoh.com', '/srv/dapur');

        self::assertSame('/srv/dapur/config/website/contoh.com', $website->folderConfig);
        self::assertSame('/srv/dapur/data/website/contoh.com/log/error.log', $website->fileLogError());
    }
}
