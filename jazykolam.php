
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
        $env = $twig->twig();
        $env->addExtension($ext);

        $cfg = (array)$this->config->get('plugins.jazykolam.auto_override');
        if (!empty($cfg['t'])) {
            $env->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $env->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $env->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
        }
        if (!empty($cfg['nicetime'])) {
            $env->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime']));
        }

        $this->maybeRegisterGantry($ext);
    }

    public function onThemeInitialized(): void
    {
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
        if (!class_exists('Gantry\Framework\Gantry')) return;
        try {
            $gantry = \Gantry\Framework\Gantry::instance();
            if (!$gantry || !isset($gantry['theme'])) return;
            $renderer = $gantry['theme']->renderer();
            // Filters
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_plural', [$ext, 'pluralFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_month', [$ext, 'monthFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_time', [$ext, 'timeFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime']));
            // Functions
            $renderer->addFunction(new \Twig\TwigFunction('jazykolam_set_locale', [$ext, 'setLocaleFunction']));
            $renderer->addFunction(new \Twig\TwigFunction('jazykolam_debug', [$ext, 'debugFunction'], ['is_safe' => ['html']]));
            $renderer->addFunction(new \Twig\TwigFunction('jazykolam_debug_panel', [$ext, 'debugPanelFunction'], ['is_safe' => ['html']]));
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
