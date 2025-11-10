<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\Event;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            // Frontend inline save endpoint
            'onTask.jazykolam.inlineSave' => ['onInlineSave', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            // Admin side: menu + controller
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminControllerInit' => ['onAdminControllerInit', 0],
            ]);
        } else {
            // Site side: Twig integration + optional Gantry + debug/inline UI
            $this->enable([
                'onTwigExtensions' => ['onTwigExtensions', 0],
                'onOutputGenerated' => ['onOutputGenerated', 0],
                'onThemeInitialized' => ['onThemeInitialized', 0],
            ]);
        }
    }

    /**
     * Register Twig extension and filters for the frontend.
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
     * Theme init hook used to register filters into Gantry 5 renderer when enabled.
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
            // Gantry is optional; fail silently
        }
    }

    /**
     * Inject debug panel and inline editor assets when enabled.
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

        // Inline editor script (only if active for this user/request)
        if ($this->isInlineEditActive()) {
            $nonce = $this->getGrav()['utils']->getNonce('jazykolam-inline');
            $script = <<<HTML
<script>
(function(){
  function jzClosest(el){ while(el && !el.dataset.jazykolamKey){ el = el.parentElement; } return el; }
  function jzClick(ev){
    var span = jzClosest(ev.target);
    if(!span) return;
    var key = span.dataset.jazykolamKey;
    var locale = span.dataset.jazykolamLocale || '';
    var current = span.textContent;
    var value = window.prompt('Upravit překlad ['+key+'] ('+locale+'): ', current);
    if(value === null) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', (window.GravAdmin && GravAdmin.config.base_url_relative ? GravAdmin.config.base_url_relative : '') + '/task/jazykolam.inlineSave', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function(){
      if(xhr.status === 200){
        try {
          var res = JSON.parse(xhr.responseText);
          if(res && res.status === 'ok'){
            span.textContent = value;
          } else {
            alert('Jazykolam: ulozeni selhalo.');
          }
        } catch(e){
          span.textContent = value;
        }
      } else {
        alert('Jazykolam: chyba pri ukladani ('+xhr.status+').');
      }
    };
    var body = 'key=' + encodeURIComponent(key) +
               '&locale=' + encodeURIComponent(locale) +
               '&value=' + encodeURIComponent(value) +
               '&nonce=' + encodeURIComponent('%s');
    xhr.send(body);
  }
  document.addEventListener('click', function(ev){
    var t = ev.target;
    while(t && t !== document){
      if(t.classList && t.classList.contains('jazykolam-inline')){
        ev.preventDefault();
        ev.stopPropagation();
        jzClick(ev);
        break;
      }
      t = t.parentElement;
    }
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
     * Admin menu entry.
     */
    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['Jazykolam'] = [
            'route' => 'jazykolam',
            'icon'  => 'fa-language'
        ];
    }

    /**
     * Admin controller hook: populate translations screen and handle save task.
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
                $keys[$k][$locale] = is_array($v) ? json_encode($v) : (string)$v;
            }
        }

        ksort($keys);

        $twig = $this->grav['twig'];
        $twig->twig_vars['jazykolam_languages'] = $languages;
        $twig->twig_vars['jazykolam_translations'] = $keys;
    }

    /**
     * Save translations from Admin UI into user/languages.jazykolam.yaml.
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

        $file = $this->getJazykolamLangFile();
        $content = $file->exists() ? (array)$file->content() : [];

        foreach ($data as $key => $langs) {
            if (!is_array($langs)) {
                continue;
            }
            foreach ($langs as $locale => $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    unset($content[$key][$locale]);
                } else {
                    $content[$key][$locale] = $value;
                }
            }
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

    /**
     * Frontend inline save handler (AJAX).
     * Triggered via /task/jazykolam.inlineSave
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

        // Check allowed roles
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

        // Nonce check
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
     * Helper to get jazykolam language file.
     */
    protected function getJazykolamLangFile(): CompiledYamlFile
    {
        $locator = $this->grav['locator'];
        $path = $locator->findResource('user://languages.jazykolam.yaml', true, true);
        return CompiledYamlFile::instance($path);
    }

    /**
     * Helper JSON response for inline save.
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

        // Stop further processing
        if (method_exists($grav, 'close')) {
            $grav->close();
        } else {
            exit;
        }
    }

    /**
     * Detect if inline edit mode should be active for current user/request.
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
}
