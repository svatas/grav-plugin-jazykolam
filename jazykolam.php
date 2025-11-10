<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\Event;

/**
 * Jazykolam plugin
 *
 * Provides extended translation handling, Admin Translation Manager
 * and optional frontend inline editing of translations.
 */
class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            // Frontend inline save endpoint (task)
            'onTask.jazykolam.inlineSave' => ['onInlineSave', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            // Admin context: navigation + controller integration
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminControllerInit' => ['onAdminControllerInit', 0],
            ]);
        } else {
            // Site context: Twig integration, Gantry integration, debug + inline assets
            $this->enable([
                'onTwigExtensions' => ['onTwigExtensions', 0],
                'onOutputGenerated' => ['onOutputGenerated', 0],
                'onThemeInitialized' => ['onThemeInitialized', 0],
            ]);
        }
    }

    /**
     * Register Twig extension and optional auto-override filters for frontend.
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
    }

    /**
     * Theme init: optional Gantry 5 integration when enabled.
     */
    public function onThemeInitialized(): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        if (empty($cfg['auto_override']['gantry'])) {
            return;
        }

        if (!class_exists('\\Gantry\\Framework\\Gantry')) {
            return;
        }

        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        $ext = new \JazykolamTwigExtension($this->grav);

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
            // Gantry is optional; never break the site.
        }
    }

    /**
     * Inject debug panel and inline editor bootstrap (only when enabled).
     */
    public function onOutputGenerated(Event $e): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        $response = $e['response'] ?? null;
        if (!$response) {
            return;
        }

        $contentType = $response->headers->get('Content-Type', 'text/html');
        if (stripos($contentType, 'html') === false) {
            return;
        }

        $content = (string)$response->getContent();

        // Debug panel
        $debugEnabled = (bool)($cfg['debug']['enabled'] ?? false);
        $debugInject = $cfg['debug']['inject'] ?? false;

        if ($debugEnabled && $debugInject && class_exists('\JazykolamTwigExtension') && method_exists('\JazykolamTwigExtension', 'getDebugLog')) {
            $log = \JazykolamTwigExtension::getDebugLog();
            if (!empty($log)) {
                $panel = '<div id="jazykolam-debug-panel" style="position:fixed;bottom:0;left:0;right:0;max-height:40vh;overflow:auto;background:#111;color:#eee;font:12px monospace;z-index:9999;padding:8px;border-top:2px solid #e91e63;">';
                $panel .= '<strong>Jazykolam debug</strong><br />';
                foreach ($log as $row) {
                    $panel .= htmlspecialchars((string)$row, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
                }
                $panel .= '</div>';

                if (stripos($content, '</body>') !== false) {
                    $content = preg_replace('~</body>~i', $panel . '</body>', $content, 1);
                } else {
                    $content .= $panel;
                }
            }
        }

        // Inline editor JS only for authorized inline-edit session
        if ($this->isInlineEditActive()) {
            $nonce = $this->grav['utils']->getNonce('jazykolam-inline');
            $script = <<<HTML
<script>
(function(){
  function jzClosestInline(el){
    while(el && el !== document && !(el.classList && el.classList.contains('jazykolam-inline'))){
      el = el.parentElement;
    }
    return (el && el.classList && el.classList.contains('jazykolam-inline')) ? el : null;
  }
  function jzEdit(span){
    var key = span.getAttribute('data-jazykolam-key') || '';
    var locale = span.getAttribute('data-jazykolam-locale') || '';
    if(!key){ return; }
    var current = span.textContent || '';
    var value = window.prompt('Upravit překlad ['+key+'] ('+locale+'): ', current);
    if(value === null){ return; }
    var xhr = new XMLHttpRequest();
    var base = (window.GravAdmin && GravAdmin.config && GravAdmin.config.base_url_relative) ? GravAdmin.config.base_url_relative : '';
    xhr.open('POST', base + '/task/jazykolam.inlineSave', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function(){
      if(xhr.status === 200){
        try {
          var res = JSON.parse(xhr.responseText);
          if(res && res.status === 'ok'){
            span.textContent = value;
          } else {
            alert('Jazykolam: uložení selhalo: ' + (res.message || 'neznámá chyba'));
          }
        } catch(e){
          span.textContent = value;
        }
      } else {
        alert('Jazykolam: chyba při ukládání ('+xhr.status+').');
      }
    };
    var body = 'key=' + encodeURIComponent(key) +
               '&locale=' + encodeURIComponent(locale) +
               '&value=' + encodeURIComponent(value) +
               '&nonce=' + encodeURIComponent('%s');
    xhr.send(body);
  }
  document.addEventListener('click', function(ev){
    var span = jzClosestInline(ev.target);
    if(!span){ return; }
    ev.preventDefault();
    ev.stopPropagation();
    jzEdit(span);
  }, false);
})();
</script>
HTML;
            $script = sprintf($script, $nonce);

            if (stripos($content, '</body>') !== false) {
                $content = preg_replace('~</body>~i', $script . '</body>', $content, 1);
            } else {
                $content .= $script;
            }
        }

        $response->setContent($content);
        $e['response'] = $response;
    }

    /**
     * Add Jazykolam to Admin navigation.
     */
    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['Jazykolam'] = [
            'route' => 'jazykolam',
            'icon'  => 'fa-language',
        ];
    }

    /**
     * Admin controller: set up data for Translation Manager and handle save.
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
     * Build translations matrix + detect missing keys for Admin UI.
     */
    protected function prepareAdminTranslations(): void
    {
        $language = $this->grav['language'];
        $languages = (array)$language->getLanguages();
        if (empty($languages)) {
            $languages = [$language->getDefault() ?: 'en'];
        }

        $allTranslations = (array)$language->getTranslations();
        $matrix = [];

        foreach ($allTranslations as $locale => $items) {
            foreach ((array)$items as $key => $value) {
                if (!isset($matrix[$key])) {
                    $matrix[$key] = [];
                }
                $matrix[$key][$locale] = is_array($value) ? json_encode($value) : (string)$value;
            }
        }

        // Collect used keys from Twig templates
        $usedKeys = $this->collectUsedKeys();
        foreach ($usedKeys as $key) {
            if (!isset($matrix[$key])) {
                $matrix[$key] = [];
            }
        }

        ksort($matrix);

        // Determine which keys are missing at least one language
        $missing = [];
        foreach ($matrix as $key => $langs) {
            foreach ($languages as $locale) {
                $v = $langs[$locale] ?? '';
                if ($v === '' || $v === null) {
                    $missing[$key] = true;
                    break;
                }
            }
        }

        $twig = $this->grav['twig'];
        $twig->twig_vars['jazykolam_languages'] = $languages;
        $twig->twig_vars['jazykolam_translations'] = $matrix;
        $twig->twig_vars['jazykolam_missing_keys'] = array_keys($missing);
    }

    /**
     * Save translations from Admin UI into user/languages.jazykolam.yaml
     * including new key row and creating a backup.
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

        $post = $request->getPost();
        $data = $post['jazykolam'] ?? null;
        $newKey = trim((string)($post['jazykolam_new_key'] ?? ''));
        $newVals = $post['jazykolam_new'] ?? [];

        $file = $this->getJazykolamLangFile();
        $content = $file->exists() ? (array)$file->content() : [];

        // Backup before write
        $this->backupLangFile($file, $content);

        if (is_array($data)) {
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
                        if (!isset($content[$key]) || !is_array($content[$key])) {
                            $content[$key] = [];
                        }
                        $content[$key][$locale] = $value;
                    }
                }
                if (isset($content[$key]) && empty($content[$key])) {
                    unset($content[$key]);
                }
            }
        }

        // New key support
        if ($newKey !== '' && is_array($newVals)) {
            foreach ($newVals as $locale => $value) {
                $value = trim((string)$value);
                if ($value !== '') {
                    if (!isset($content[$newKey]) || !is_array($content[$newKey])) {
                        $content[$newKey] = [];
                    }
                    $content[$newKey][$locale] = $value;
                }
            }
            if (isset($content[$newKey]) && empty($content[$newKey])) {
                unset($content[$newKey]);
            }
        }

        $file->save($content);
        $file->free();

        if (isset($this->grav['admin'])) {
            $this->grav['admin']->setMessage('Jazykolam: translations saved.', 'info');
        }
    }

    /**
     * Frontend inline save handler.
     */
    public function onInlineSave(): void
    {
        $grav = $this->grav;
        $request = $grav['request'] ?? null;
        $user = $grav['user'] ?? null;

        if (!$request || !$user || !$user->authenticated) {
            $this->inlineJson(['status' => 'error', 'message' => 'Not authorized']);
            return;
        }

        $cfg = (array)$this->config->get('plugins.jazykolam');
        $allowed = (array)($cfg['inline_edit']['allowed_roles'] ?? ['admin']);
        $ok = false;
        foreach ($allowed as $role) {
            if ($user->authorize($role)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $this->inlineJson(['status' => 'error', 'message' => 'Forbidden']);
            return;
        }

        $nonce = $request->getPost()['nonce'] ?? null;
        if (!$grav['utils']->verifyNonce($nonce, 'jazykolam-inline')) {
            $this->inlineJson(['status' => 'error', 'message' => 'Invalid nonce']);
            return;
        }

        $key = trim((string)($request->getPost()['key'] ?? ''));
        $locale = trim((string)($request->getPost()['locale'] ?? ''));
        $value = (string)($request->getPost()['value'] ?? '');

        if ($key === '' || $locale === '') {
            $this->inlineJson(['status' => 'error', 'message' => 'Missing key/locale']);
            return;
        }

        $file = $this->getJazykolamLangFile();
        $content = $file->exists() ? (array)$file->content() : [];

        if ($value === '') {
            unset($content[$key][$locale]);
            if (isset($content[$key]) && empty($content[$key])) {
                unset($content[$key]);
            }
        } else {
            if (!isset($content[$key]) || !is_array($content[$key])) {
                $content[$key] = [];
            }
            $content[$key][$locale] = $value;
        }

        $file->save($content);
        $file->free();

        $this->inlineJson(['status' => 'ok']);
    }

    /**
     * Get jazykolam language file instance.
     */
    protected function getJazykolamLangFile(): CompiledYamlFile
    {
        $locator = $this->grav['locator'];
        $path = $locator->findResource('user://languages.jazykolam.yaml', true, true);
        return CompiledYamlFile::instance($path);
    }

    /**
     * Create a timestamped backup of language file before overwrite.
     */
    protected function backupLangFile(CompiledYamlFile $file, array $content): void
    {
        if (!$file->exists() || empty($content)) {
            return;
        }

        $path = $file->filename();
        $dir = dirname($path);
        $name = basename($path, '.yaml');
        $ts = date('Ymd_His');
        $backup = $dir . '/' . $name . '.' . $ts . '.bak.yaml';

        if (!empty($content) && class_exists('Grav\\Common\\Yaml')) {
            @file_put_contents($backup, \Grav\Common\Yaml::dump($content));
        }
    }

    /**
     * Return JSON response and terminate.
     */
    protected function inlineJson(array $data): void
    {
        $grav = $this->grav;
        $response = $grav['response'] ?? null;
        $json = json_encode($data);

        if ($response) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            $response->setContent($json);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo $json;
        }

        if (method_exists($grav, 'close')) {
            $grav->close();
        } else {
            exit;
        }
    }

    /**
     * Determine if inline edit mode is active for current request.
     */
    protected function isInlineEditActive(): bool
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        if (empty($cfg['inline_edit']['enabled'])) {
            return false;
        }

        $grav = $this->grav;
        $user = $grav['user'] ?? null;
        if (!$user || !$user->authenticated) {
            return false;
        }

        $allowed = (array)($cfg['inline_edit']['allowed_roles'] ?? ['admin']);
        $ok = false;
        foreach ($allowed as $role) {
            if ($user->authorize($role)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return false;
        }

        $uri = $grav['uri'] ?? null;
        if ($uri && ($uri->query('jazykolam_inline') == 1 || $uri->param('jazykolam_inline') == 1)) {
            return true;
        }

        return false;
    }

    /**
     * Scan user themes and plugins for Twig templates and collect translation keys.
     */
    protected function collectUsedKeys(): array
    {
        $locator = $this->grav['locator'];
        $keys = [];

        $paths = [];
        $themes = $locator->findResources('theme://');
        foreach ($themes as $t) {
            $paths[] = $t;
        }
        $userThemes = $locator->findResource('user://themes', false);
        if ($userThemes) {
            $paths[] = $userThemes;
        }
        $userPlugins = $locator->findResource('user://plugins', false);
        if ($userPlugins) {
            $paths[] = $userPlugins;
        }

        $pattern = '~['"]([A-Z0-9_.:-]+)['"]\s*\|\s*(t|trans|tu|tl|jazykolam_t)~';

        foreach ($paths as $base) {
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (substr($file->getFilename(), -5) !== '.twig') {
                    continue;
                }
                $code = @file_get_contents($file->getPathname());
                if ($code === false) {
                    continue;
                }
                if (preg_match_all($pattern, $code, $m)) {
                    foreach ($m[1] as $key) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        return array_keys($keys);
    }
}
