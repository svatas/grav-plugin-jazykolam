<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\Event;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminControllerInit' => ['onAdminControllerInit', 0],
            ]);
        } else {
            $this->enable([
                'onTwigExtensions' => ['onTwigExtensions', 0],
                'onOutputGenerated' => ['onOutputGenerated', 0],
                'onThemeInitialized' => ['onThemeInitialized', 0],
            ]);
        }
    }

    /**
     * Register Twig extension and filters for site side.
     */
    public function onTwigExtensions(): void
    {
        $grav = $this->grav;
        $twig = $grav['twig']->twig();

        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        $ext = new \JazykolamTwigExtension($grav);

        $twig->addExtension($ext);

        $cfg = (array)$this->config->get('plugins.jazykolam');

        // Optional override of standard translation filters
        if (!empty($cfg['auto_override']['t'])) {
            $twig->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
        }

        if (!empty($cfg['auto_override']['nicetime'])) {
            $twig->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime'], ['is_variadic' => true]));
        }

        if (!empty($cfg['auto_override']['gantry'])) {
            $this->maybeRegisterGantry($ext, $cfg);
        }
    }

    /**
     * Optional Gantry 5 integration.
     */
    protected function maybeRegisterGantry($ext, array $cfg): void
    {
        if (!class_exists('\\Gantry\\Framework\\Gantry')) {
            return;
        }

        try {
            $gantry = \Gantry\Framework\Gantry::instance();
            if (!isset($gantry['theme'])) {
                return;
            }
            $engine = $gantry['theme'];
            $renderer = $engine->renderer();
            $renderer->addExtension($ext);

            if (!empty($cfg['auto_override']['t'])) {
                $renderer->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
                $renderer->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
            }

            if (!empty($cfg['auto_override']['nicetime'])) {
                $renderer->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'autoNicetime'], ['is_variadic' => true]));
            }
        } catch (\Throwable $e) {
            // Fail silently, Gantry is optional.
        }
    }

    /**
     * Inject simple debug panel when enabled.
     */
    public function onOutputGenerated(Event $e): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        $enabled = (bool)($cfg['debug']['enabled'] ?? false);
        $inject = $cfg['debug']['inject'] ?? false;

        if (!$enabled || !$inject) {
            return;
        }

        $response = $e['response'];
        if (!$response) {
            return;
        }

        $contentType = $response->headers->get('Content-Type', 'text/html');
        if (stripos($contentType, 'html') === false) {
            return;
        }

        if (!class_exists('\JazykolamTwigExtension') || !method_exists('\JazykolamTwigExtension', 'getDebugLog')) {
            return;
        }

        $log = \JazykolamTwigExtension::getDebugLog();
        if (empty($log)) {
            return;
        }

        $panel = '<div id="jazykolam-debug-panel" style="position:fixed;bottom:0;left:0;right:0;max-height:40vh;overflow:auto;background:#111;color:#eee;font:12px monospace;z-index:9999;padding:8px;border-top:2px solid #e91e63;">';
        $panel .= '<strong>Jazykolam debug</strong><br />';
        foreach ($log as $row) {
            $panel .= htmlspecialchars((string)$row, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
        }
        $panel .= '</div>';

        $content = $response->getContent();
        $injected = $panel;

        if (stripos($content, '</body>') !== false) {
            $content = str_ireplace('</body>', $injected . '</body>', $content);
        } else {
            $content .= $injected;
        }

        $response->setContent($content);
        $e['response'] = $response;
    }

    /**
     * Add Jazykolam item to Admin menu.
     */
    public function onAdminMenu(): void
    {
        if (!isset($this->grav['twig']->plugins_hooked_nav['Jazykolam'])) {
            $this->grav['twig']->plugins_hooked_nav['Jazykolam'] = [
                'route' => 'jazykolam',
                'icon'  => 'fa-language'
            ];
        }
    }

    /**
     * Handle Admin controller init: prepare data and handle save task.
     */
    public function onAdminControllerInit(Event $event): void
    {
        $controller = $event['controller'];
        $route = $controller->getRoute();
        $task  = $controller->task;

        if ($route === 'jazykolam') {
            $this->prepareAdminTranslations();
        }

        if ($task === 'jazykolam.saveTranslations') {
            $this->handleSaveTranslations();
        }
    }

    /**
     * Build translations matrix for Admin UI.
     */
    protected function prepareAdminTranslations(): void
    {
        $language = $this->grav['language'];
        $languages = (array)$language->getLanguages();
        if (empty($languages)) {
            $languages = [$language->getDefault() ?: 'en'];
        }

        $keys = [];
        $all = (array)$language->getTranslations();

        foreach ($all as $locale => $items) {
            foreach ((array)$items as $k => $v) {
                if (!isset($keys[$k])) {
                    $keys[$k] = [];
                }
                // For complex values store JSON string to avoid breaking layout.
                $keys[$k][$locale] = is_array($v) ? json_encode($v) : (string)$v;
            }
        }

        ksort($keys);

        $twig = $this->grav['twig'];
        $twig->twig_vars['jazykolam_languages'] = $languages;
        $twig->twig_vars['jazykolam_translations'] = $keys;
    }

    /**
     * Handle saving translations from Admin UI into user/languages.jazykolam.yaml.
     */
    protected function handleSaveTranslations(): void
    {
        if (!$this->isAdmin()) {
            return;
        }

        $request = $this->grav['request'] ?? null;
        if (!$request) {
            return;
        }

        $data = $request->getPost()['jazykolam'] ?? null;
        if (!is_array($data)) {
            return;
        }

        $locator = $this->grav['locator'];
        $file = CompiledYamlFile::instance($locator->findResource('user://languages.jazykolam.yaml', true, true));
        $content = $file->exists() ? (array)$file->content() : [];

        foreach ($data as $key => $langs) {
            if (!is_array($langs)) {
                continue;
            }
            foreach ($langs as $locale => $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    if (isset($content[$key][$locale])) {
                        unset($content[$key][$locale]);
                    }
                } else {
                    if (!isset($content[$key])) {
                        $content[$key] = [];
                    }
                    $content[$key][$locale] = $value;
                }
            }
            // Remove empty keys
            if (isset($content[$key]) && empty($content[$key])) {
                unset($content[$key]);
            }
        }

        $file->save($content);
        $file->free();

        if (isset($this->grav['admin'])) {
            $this->grav['admin']->setMessage('Jazykolam: translations saved.', 'info');
        }
    }
}
