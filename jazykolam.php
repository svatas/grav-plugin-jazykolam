
<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use RocketTheme\Toolbox\Event\Event;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions'      => ['onTwigExtensions', 0],
            'onThemeInitialized'    => ['onThemeInitialized', 0],
            'onOutputGenerated'     => ['onOutputGenerated', 0],
            // Admin mini-dashboard (very light)
            'onAdminMenu'           => ['onAdminMenu', 0],
            'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
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
            $env->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
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
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_plural', [$ext, 'pluralFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_month', [$ext, 'monthFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('jazykolam_time', [$ext, 'timeFilter']));
            $renderer->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
            $renderer->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime']));
            $renderer->addFunction(new \Twig\TwigFunction('jazykolam_set_locale', [$ext, 'setLocaleFunction']));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Auto-inject debug panel + console at end of HTML when enabled
     */
    public function onOutputGenerated(Event $e): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        $inject = (bool)($cfg['debug']['inject'] ?? false);
        $enabled = (bool)($cfg['debug']['enabled'] ?? false);
        $console = (bool)($cfg['debug']['console'] ?? false);
        $queryFlag = false;
        try { $queryFlag = (bool)$this->grav['uri']->query('jazykolam_debug'); } catch (\Throwable $ex) {}

        if (!($inject && ($enabled || $queryFlag))) { return; }
        if ($this->isAdmin()) { return; }

        $output = $e['output'] ?? '';
        if (!is_string($output) || stripos($output, '<html') === false) { return; }

        // Build panel + console
        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        $ext = new \JazykolamTwigExtension($this->grav);
        // Pull internal log and compose snipped scripts via our extension method
        $panel = $ext->debugPanelFunction();
        $consoleSnippet = $console ? $ext->debugConsoleFunction() : '';

        // Inject before </body> if possible
        $injected = $panel . $consoleSnippet;
        if ($injected) {
            if (false !== stripos($output, '</body>')) {
                $output = preg_replace('~</body>~i', $injected . '</body>', $output, 1);
            } else {
                $output .= $injected;
            }
            $e['output'] = $output;
        }
    }

    // --- Admin mini-dashboard (simple link to plugin config + tools partial) ---
    public function onAdminMenu(): void
    {
        if (!$this->isAdmin()) return;
        $this->grav['twig']->plugins_hooked_nav['Jazykolam'] = ['route' => 'plugins/jazykolam', 'icon' => 'fa-language'];
    }

    public function onAdminTwigTemplatePaths(Event $e): void
    {
        $paths = $e['paths'];
        $paths[] = __DIR__ . '/admin/templates';
        $e['paths'] = $paths;
    }
}
