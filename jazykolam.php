
<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;

/**
 * Jazykolam Plugin
 * Advanced i18n helpers (pluralization, months, relative time) as Twig filters
 */
class JazykolamPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
        ];
    }

    /**
     * Register Twig extension with filters
     */
    public function onTwigExtensions(): void
    {
        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $twig->twig()->addExtension(new \JazykolamTwigExtension($this->grav));
    }
}
