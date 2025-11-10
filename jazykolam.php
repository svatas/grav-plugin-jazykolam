
<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;

/**
 * Jazykolam Plugin
 * Advanced i18n helpers (pluralization, months, relative time) as Twig filters
 * Now with optional auto-override of Grav filters (t/tu/tl, nicetime).
 */
class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
        ];
    }

    public function onTwigExtensions(): void
    {
        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $ext = new \JazykolamTwigExtension($this->grav);

        // Always provide explicit filters
        $twig->twig()->addExtension($ext);

        // Optional automatic overrides (no template changes)
        $cfg = (array)$this->config->get('plugins.jazykolam.auto_override');
        if (!empty($cfg['t'])) {
            $twig->twig()->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->twig()->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->twig()->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
        }
        if (!empty($cfg['nicetime'])) {
            // Grav themes often use |nicetime for relative times
            $twig->twig()->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime']));
        }
    }
}
