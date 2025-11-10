<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Yaml;
use RocketTheme\Toolbox\Event\Event;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            // Frontend inline save endpoint (used via /task/jazykolam.inlineSave)
            'onTask.jazykolam.inlineSave' => ['onInlineSave', 0],
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

    public function onTwigExtensions(): void
    {
        $grav = $this->grav;
        $twig = $grav['twig']->twig();

        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        $ext = new \JazykolamTwigExtension($grav);
        $twig->addExtension($ext);

        $cfg = (array)$this->config->get('plugins.jazykolam');

        // Optional override of Grav core translation filters
        if (!empty($cfg['auto_override']['t'])) {
            $twig->addFilter(new \Twig\TwigFilter('t', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('trans', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('tu', [$ext, 'autoT'], ['is_variadic' => true]));
            $twig->addFilter(new \Twig\TwigFilter('tl', [$ext, 'autoT'], ['is_variadic' => true]));
        }

        if (!empty($cfg['auto_override']['nicetime'])) {
            $twig->addFilter(new \Twig\TwigFilter('nicetime', [$ext, 'jazykolamTime'], ['is_variadic' => true]));
        }
    }

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
        } catch (\Throwable $e) {
            // Gantry integration is best-effort only.
        }
    }

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
        if (!empty($cfg['debug']['enabled']) && !empty($cfg['debug']['inject'])
            && class_exists('\JazykolamTwigExtension') && method_exists('\JazykolamTwigExtension', 'getDebugLog')) {

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

        // Inline editor / inspect JS – only if active for this request
        if ($this->isInlineEditActive()) {
            $nonce = $this->grav['utils']->getNonce('jazykolam-inline');
            $script = <<<HTML
<script>
(function(){
  function getMode(){
    var q = window.location.search || '';
    if(q.indexOf('jazykolam_inline=inspect') !== -1) return 'inspect';
    if(q.indexOf('jazykolam_inline=1') !== -1) return 'edit';
    return 'off';
  }
  function closestSpan(el){
    while(el && el !== document && !(el.classList && el.classList.contains('jazykolam-inline'))){
      el = el.parentElement;
    }
    return (el && el.classList && el.classList.contains('jazykolam-inline')) ? el : null;
  }
  function ensureTooltip(span){
    if(!span.getAttribute('title')){
      var key = span.getAttribute('data-jazykolam-key') || '';
      var loc = span.getAttribute('data-jazykolam-locale') || '';
      span.setAttribute('title', key + (loc ? ' ['+loc+']' : ''));
    }
  }
  function createPopup(){
    var wrap = document.createElement('div');
    wrap.id = 'jazykolam-inline-popup';
    wrap.style.position = 'fixed';
    wrap.style.left = '50%';
    wrap.style.top = '20%';
    wrap.style.transform = 'translateX(-50%)';
    wrap.style.zIndex = '99999';
    wrap.style.background = '#222';
    wrap.style.color = '#fff';
    wrap.style.padding = '12px';
    wrap.style.borderRadius = '6px';
    wrap.style.boxShadow = '0 4px 18px rgba(0,0,0,0.5)';
    wrap.style.maxWidth = '480px';
    wrap.style.fontSize = '13px';
    wrap.innerHTML =
      '<div style="margin-bottom:6px;font-weight:bold;">Jazykolam inline edit</div>'+
      '<div id="jz-key" style="margin-bottom:4px;"></div>'+
      '<textarea id="jz-value" style="width:100%;min-height:70px;font-size:13px;"></textarea>'+
      '<div style="margin-top:6px;text-align:right;">'+
        '<button type="button" id="jz-cancel" style="margin-right:4px;">Cancel</button>'+
        '<button type="button" id="jz-save">Save</button>'+
      '</div>';
    document.body.appendChild(wrap);
    return wrap;
  }
  function openPopup(span, nonce){
    var key = span.getAttribute('data-jazykolam-key') || '';
    var locale = span.getAttribute('data-jazykolam-locale') || '';
    if(!key){ return; }
    var cur = span.textContent || '';
    var popup = document.getElementById('jazykolam-inline-popup') || createPopup();
    popup.style.display = 'block';
    popup.dataset.key = key;
    popup.dataset.locale = locale;
    popup.dataset.nonce = nonce;
    popup.querySelector('#jz-key').textContent = key + (locale ? ' ['+locale+']' : '');
    popup.querySelector('#jz-value').value = cur;
    popup.querySelector('#jz-cancel').onclick = function(){ popup.style.display = 'none'; };
    popup.querySelector('#jz-save').onclick = function(){ doSave(span, popup); };
  }
  function doSave(span, popup){
    var key = popup.dataset.key;
    var locale = popup.dataset.locale;
    var nonce = popup.dataset.nonce;
    var value = popup.querySelector('#jz-value').value;
    var xhr = new XMLHttpRequest();
    var base = (window.GravAdmin && GravAdmin.config && GravAdmin.config.base_url_relative) ? GravAdmin.config.base_url_relative : '';
    xhr.open('POST', base + '/task/jazykolam.inlineSave', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function(){
      if(xhr.status === 200){
        try {
          var res = JSON.parse(xhr.responseText || '{}');
          if(res.status === 'ok'){
            span.textContent = value;
            popup.style.display = 'none';
            return;
          }
          alert('Jazykolam: uložení selhalo: ' + (res.message || 'unknown error'));
        } catch(e){
          span.textContent = value;
          popup.style.display = 'none';
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

  var mode = getMode();
  if(mode === 'off') return;

  if(mode === 'inspect'){
    document.addEventListener('mouseover', function(ev){
      var span = closestSpan(ev.target);
      if(span){ ensureTooltip(span); }
    });
  }

  if(mode === 'edit'){
    document.addEventListener('click', function(ev){
      var span = closestSpan(ev.target);
      if(!span) return;
      ev.preventDefault();
      ev.stopPropagation();
      openPopup(span, '%s');
    }, false);
  }
})();</script>
HTML;
            $script = sprintf($script, $nonce, $nonce);

            if (stripos($content, '</body>') !== false) {
                $content = preg_replace('~</body>~i', $script . '</body>', $content, 1);
            } else {
                $content .= $script;
            }
        }

        $response->setContent($content);
        $e['response'] = $response;
    }

    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['Jazykolam'] = [
            'route' => 'jazykolam',
            'icon'  => 'fa-language',
        ];
    }

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

    protected function prepareAdminTranslations(): void
    {
        $language = $this->grav['language'];
        $languages = (array)$language->getLanguages();
        if (empty($languages)) {
            $languages = [$language->getDefault() ?: 'en'];
        }

        $all = (array)$language->getTranslations();
        $matrix = [];

        foreach ($all as $locale => $items) {
            foreach ((array)$items as $key => $value) {
                if (!isset($matrix[$key])) {
                    $matrix[$key] = [];
                }
                $matrix[$key][$locale] = is_array($value) ? json_encode($value) : (string)$value;
            }
        }

        // Discover keys from templates (Twig)
        foreach ($this->collectUsedKeys() as $key) {
            if (!isset($matrix[$key])) {
                $matrix[$key] = [];
            }
        }

        ksort($matrix);

        // Mark missing keys
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

        $file = $this->getLangFile();
        $content = $file->exists() ? (array)$file->content() : [];

        // Backup
        if ($file->exists() && !empty($content)) {
            $this->backupLangFile($file, $content);
        }

        if (is_array($data)) {
            foreach ($data as $key => $langs) {
                if (!is_array($langs)) {
                    continue;
                }
                foreach ($langs as $locale => $value) {
                    $value = trim((string)$value);
                    if ($value === '') {
                        unset($content[$key][$locale]);
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

    public function onInlineSave(): void
    {
        $grav = $this->grav;
        $request = $grav['request'] ?? null;
        $user = $grav['user'] ?? null;

        if (!$request || !$user || !$user->authenticated) {
            $this->json(['status' => 'error', 'message' => 'Not authorized']);
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
            $this->json(['status' => 'error', 'message' => 'Forbidden']);
            return;
        }

        $nonce = $request->getPost()['nonce'] ?? null;
        if (!$grav['utils']->verifyNonce($nonce, 'jazykolam-inline')) {
            $this->json(['status' => 'error', 'message' => 'Invalid nonce']);
            return;
        }

        $key = trim((string)($request->getPost()['key'] ?? ''));
        $locale = trim((string)($request->getPost()['locale'] ?? ''));
        $value = (string)($request->getPost()['value'] ?? '');

        if ($key === '' || $locale === '') {
            $this->json(['status' => 'error', 'message' => 'Missing key/locale']);
            return;
        }

        $file = $this->getLangFile();
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

        $this->json(['status' => 'ok']);
    }

    protected function getLangFile(): CompiledYamlFile
    {
        $locator = $this->grav['locator'];
        $path = $locator->findResource('user://languages.jazykolam.yaml', true, true);
        return CompiledYamlFile::instance($path);
    }

    protected function backupLangFile(CompiledYamlFile $file, array $content): void
    {
        $path = $file->filename();
        $dir = dirname($path);
        $name = basename($path, '.yaml');
        $ts = date('Ymd_His');
        $backup = $dir . '/' . $name . '.' . $ts . '.bak.yaml';
        @file_put_contents($backup, Yaml::dump($content));
    }

    protected function json(array $data): void
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
        if ($uri) {
            $v = (string)$uri->query('jazykolam_inline') ?: (string)$uri->param('jazykolam_inline');
            if ($v === '1' || $v === 'inspect') {
                return true;
            }
        }

        return false;
    }

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
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                if (substr($file->getFilename(), -5) !== '.twig') continue;
                $code = @file_get_contents($file->getPathname());
                if ($code === false) continue;
                if (preg_match_all($pattern, $code, $m)) {
                    foreach ($m[1] as $k) {
                        $keys[$k] = true;
                    }
                }
            }
        }

        return array_keys($keys);
    }
}
