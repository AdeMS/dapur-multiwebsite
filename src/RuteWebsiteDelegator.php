<?php

declare(strict_types=1);

namespace AdeMS\Dapur\MultiWebsite;

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Menambahkan route dari config/routes.php milik website ke Application.
 *
 * Formatnya sama dengan config/routes.php inti:
 *     return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void { ... };
 *
 * Route website didaftarkan sebelum route inti. Path yang sama tidak boleh didaftarkan dua kali.
 */
final class RuteWebsiteDelegator
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): Application
    {
        $app = $callback();
        assert($app instanceof Application);

        $website = Website::aktif();
        $file    = $website?->folderConfig . '/routes.php';
        if ($website !== null && is_file($file)) {
            $rute = require $file;
            if (is_callable($rute)) {
                $rute($app, $container->get(MiddlewareFactory::class), $container);
            }
        }

        return $app;
    }
}
