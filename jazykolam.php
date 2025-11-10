<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils; 
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\Event;

class JazykolamPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions'      => ['onTwigExtensions', 0],
            'onThemeInitialized'    => ['onThemeInitialized', 0],
            'onOutputGenerated'     => ['onOutputGenerated', 0],
            'onAdminMenu'           => ['onAdminMenu', 0],
            'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
            'onTask'                => ['onTask', 0],
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
        if (!class_exists('Gantry\\Framework\\Gantry')) return;
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

    public function onOutputGenerated(Event $e): void
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        $inject = $cfg['debug']['inject'] ?? false; // false|true|smart
        $enabled = (bool)($cfg['debug']['enabled'] ?? false);
        $console = (bool)($cfg['debug']['console'] ?? false);
        $queryFlag = false;
        try { $queryFlag = (bool)$this->grav['uri']->query('jazykolam_debug'); } catch (\Throwable $ex) {}

        if ($this->isAdmin()) {
            if ($inject !== 'smart') { return; }
        }

        if ($inject === 'smart') {
            $user = $this->grav['user'] ?? null;
            $isAdminUser = $user && !empty($user->authenticated) && method_exists($user,'authorize') && $user->authorize('admin.login');
            if (!$isAdminUser) {
                if (!$queryFlag) return;
            }
        } elseif (!$inject) {
            if (!$queryFlag) return;
        }

        if (!$enabled && !$queryFlag) return;
        if ($this->shouldSkipInjection()) return;

        $output = $e['output'] ?? '';
        if (!is_string($output) || stripos($output, '<html') === false) { return; }

        require_once __DIR__ . '/classes/JazykolamTwigExtension.php';
        $ext = new \JazykolamTwigExtension($this->grav);
        $panel = $ext->debugPanelFunction($this->makeCurlCommand());
        $consoleSnippet = $console ? $ext->debugConsoleFunction() : '';
        $injected = $panel + $consoleSnippet;

        if ($injected) {
            if (false !== stripos($output, '</body>')) {
                $output = preg_replace('~</body>~i', $panel . $consoleSnippet . '</body>', $output, 1);
            } else {
                $output .= $panel . $consoleSnippet;
            }
            $e['output'] = $output;
        }
    }

    protected function shouldSkipInjection(): bool
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        $uri = $this->grav['uri'];
        $path = method_exists($uri,'path') ? $uri->path() : '';
        $ex = (array)($cfg['debug']['inject_exclude_routes'] ?? []);
        foreach ($ex as $re) { if ($re && @preg_match('#'.$re.'#', $path)) return true; }
        if (!empty($cfg['debug']['inject_block_bots'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($ua && preg_match('~(bot|crawl|spider|fetch|monitor)~i', $ua)) return true;
        }
        if (!empty($cfg['debug']['inject_skip_json'])) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($accept,'application/json') !== false || stripos($ctype,'application/json') !== false) return true;
        }
        if (!empty($cfg['debug']['inject_skip_xhr'])) {
            $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            if (strcasecmp($xhr,'XMLHttpRequest') === 0) return true;
        }
        return false;
    }

    protected function makeCurlCommand(): string
    {
        $cfg = (array)$this->config->get('plugins.jazykolam');
        if (empty($cfg['debug']['copy_curl'])) return '';
        try {
            $uri = $this->grav['uri'];
            $url = $uri->url(true);
            $q = $uri->query();
            if ($q) {
                if (strpos($q,'jazykolam_debug=1') === false) $url .= (strpos($url,'?')===false?'?':'&').'jazykolam_debug=1';
            } else {
                $url .= (strpos($url,'?')===false?'?':'&').'jazykolam_debug=1';
            }
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'curl/8';
            $accept = $_SERVER['HTTP_ACCEPT'] ?? 'text/html,*/*;q=0.1';
            $parts = [
                'curl',
                '-H '.escapeshellarg('User-Agent: '.$ua),
                '-H '.escapeshellarg('Accept: '.$accept),
                escapeshellarg($url)
            ];
            return implode(' ', $parts);
        } catch (\Throwable $e) { return ''; }
    }

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

    public function onTask(Event $e): void
    {
        $task = $e['task'] ?? null;
        if (!$task || strpos($task,'jazykolam') === false) return;
        $map = [
            'jazykolamEnable' => ['path'=>'plugins.jazykolam.debug.enabled','value'=>true],
            'jazykolamDisable'=> ['path'=>'plugins.jazykolam.debug.enabled','value'=>false],
            'jazykolamInjectOn' => ['path'=>'plugins.jazykolam.debug.inject','value'=>true],
            'jazykolamInjectSmart' => ['path'=>'plugins.jazykolam.debug.inject','value'=>'smart'],
            'jazykolamInjectOff' => ['path'=>'plugins.jazykolam.debug.inject','value'=>false],
            'jazykolamConsoleOn' => ['path'=>'plugins.jazykolam.debug.console','value'=>true],
            'jazykolamConsoleOff' => ['path'=>'plugins.jazykolam.debug.console','value'=>false],
        ];
        if (!isset($map[$task])) return;
        $entry = $map[$task];
        $this->persistConfigValue($entry['path'], $entry['value']);
        if (isset($this->grav['admin'])) { $this->grav['admin']->setMessage('Jazykolam: setting updated ('.$task.')', 'info'); }
        $e->stopPropagation();
    }

    protected function persistConfigValue(string $path, $value): void
    {
        $this->grav['config']->set($path, $value);
        $userFile = CompiledYamlFile::instance($this->grav['locator']->findResource('user://config/plugins/jazykolam.yaml', true, true));
        $data = $userFile->exists() ? $userFile->content() : [];
        \Grav\Common\Utils::setDotNotation($data, str_replace('plugins.jazykolam.','', $path), $value);
        $userFile->save($data);
        $userFile->free();
    }
}
