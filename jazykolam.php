
<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
            'onThemeInitialized' => ['onThemeInitialized', 0],
        ];
    }

    public function onTwigExtensions(): void
    {
        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $ext = new \JazykolamTwigExtension($this->grav);
        $twig->twig()->addExtension($ext);

        $this->maybeRegisterGantry($ext);
    }

    public function onThemeInitialized(): void
    {
        // In case Gantry initializes after Twig, try again
        if (class_exists('JazykolamTwigExtension', false)) {
            $ext = new \JazykolamTwigExtension($this->grav);
            $this->maybeRegisterGantry($ext);
        }
    }

    protected function maybeRegisterGantry($ext): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam.auto_override');
        $gantryOn = (bool)($cfg['gantry'] ?? true);
        if (!$gantryOn) return;

        // Only for Gantry-powered themes
        if (!class_exists('Gantry\Framework\Gantry')) return;
        try {
            $gantry = \Gantry\Framework\Gantry::instance();
            if (!$gantry || !isset($gantry['theme'])) return;
            $renderer = $gantry['theme']->renderer(); // Twig Environment
            // Reuse our extension and (re)register explicit filters
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_plural', [$ext, 'pluralFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_month', [$ext, 'monthFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_time', [$ext, 'timeFilter']));

            // Auto overrides
            $renderer->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime']));
        } catch (\Throwable $e) {
            // swallow – Gantry not ready or not present
        }
    }
}
