<?php

use Grav\Common\Grav;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class JazykolamTwigExtension extends AbstractExtension
{
    /** @var Grav */
    protected $grav;
    protected $language;
    protected $config;

    protected static $localeOverride = null;
    protected static $debugLog = [];

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->language = $grav['language'];
        $this->config = (array)$grav['config']->get('plugins.jazykolam');
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('jazykolam_t', [$this, 'autoT'], ['is_variadic' => true, 'is_safe' => ['html']]),
            new TwigFilter('jazykolam_plural', [$this, 'jazykolamPlural'], ['is_safe' => ['html']]),
            new TwigFilter('jazykolam_month', [$this, 'jazykolamMonth'], ['is_safe' => ['html']]),
            new TwigFilter('jazykolam_time', [$this, 'jazykolamTime'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('jazykolam_set_locale', [$this, 'setLocale']),
            new TwigFunction('jazykolam_debug_panel', [self::class, 'getDebugPanel'], ['is_safe' => ['html']]),
        ];
    }

    /** Auto translation wrapper for keys */
    public function autoT(...$args): string
    {
        $key = $args[0] ?? '';
        $params = $args[1] ?? [];
        if (!is_array($params)) {
            $params = (array)$params;
        }

        $locale = $params['lang'] ?? null;
        if (isset($params['lang'])) {
            unset($params['lang']);
        }

        $text = $this->language->translate([$key], $params);
        $text = is_array($text) ? json_encode($text) : (string)$text;

        $locale = $locale ?: $this->getActiveLocale();

        self::$debugLog[] = sprintf('t(%s,%s)', $key, $locale);

        return $this->wrapInline($key, $locale, $text);
    }

    /** Human friendly time wrapper */
    public function jazykolamTime($value, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();

        $now = time();
        $ts = $this->toTimestamp($value);
        $diff = $ts - $now;

        $dir = $diff >= 0 ? 'future' : 'past';
        $sec = abs($diff);

        if ($sec < 60) { $n = $sec; $unit = 'second'; }
        elseif ($sec < 3600) { $n = floor($sec / 60); $unit = 'minute'; }
        elseif ($sec < 86400) { $n = floor($sec / 3600); $unit = 'hour'; }
        elseif ($sec < 86400 * 30) { $n = floor($sec / 86400); $unit = 'day'; }
        elseif ($sec < 86400 * 365) { $n = floor($sec / (86400 * 30)); $unit = 'month'; }
        else { $n = floor($sec / (86400 * 365)); $unit = 'year'; }

        $key = sprintf('JZK.NICETIME.%s.%s', strtoupper($dir), strtoupper($unit));
        $forms = $this->language->translate([$key]);

        if (is_array($forms)) {
            $text = $this->jazykolamPlural($n, $forms, $locale);
        } else {
            $text = strtr((string)$forms, [
                '%count%' => $n,
                '{count}' => $n,
                '#'       => $n,
            ]);
        }

        self::$debugLog[] = sprintf('time(%s,%s) => %s', (string)$value, $locale, $text);

        return $text;
    }

    /** Pluralization helper */
    public function jazykolamPlural($count, array $forms, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $cat = $this->pluralCategory($locale, (float)$count);
        $order = $this->pluralOrder($locale);

        if (isset($forms[$cat])) {
            $text = $forms[$cat];
        } else {
            $text = null;
            foreach ($order as $c) {
                if (isset($forms[$c])) { $text = $forms[$c]; break; }
            }
            if ($text === null) {
                $text = reset($forms);
            }
        }

        $text = strtr((string)$text, [
            '%count%' => $count,
            '{count}' => $count,
            '#'       => $count,
        ]);

        return $text;
    }

    /** Month names helper */
    public function jazykolamMonth($value, string $form = 'long', ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $month = $this->normalizeMonth($value);
        if ($month < 1 || $month > 12) {
            return '';
        }

        $names = $this->getMonthNames($locale);
        return $names[$form][$month - 1] ?? $names['long'][$month - 1] ?? '';
    }

    public function setLocale(?string $locale = null): string
    {
        self::$localeOverride = $locale ?: null;
        return '';
    }

    public static function getDebugLog(): array
    {
        return self::$debugLog;
    }

    public static function getDebugPanel(): string
    {
        if (empty(self::$debugLog)) {
            return '';
        }
        $out = '<div class="jazykolam-debug-panel">';
        foreach (self::$debugLog as $row) {
            $out .= htmlspecialchars((string)$row, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
        }
        $out .= '</div>';
        return $out;
    }

    protected function wrapInline(string $key, string $locale, string $output): string
    {
        if (!$this->isInlineEditActive()) {
            return $output;
        }

        $attrs = sprintf(
            'class="jazykolam-inline" data-jazykolam-key="%s" data-jazykolam-locale="%s"',
            htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($locale, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        return sprintf('<span %s>%s</span>', $attrs, htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    protected function isInlineEditActive(): bool
    {
        $cfg = $this->config;
        if (empty($cfg['inline_edit']['enabled'])) {
            return false;
        }

        $user = $this->grav['user'] ?? null;
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

        $uri = $this->grav['uri'] ?? null;
        if ($uri) {
            $v = (string)$uri->query('jazykolam_inline') ?: (string)$uri->param('jazykolam_inline');
            if ($v === '1' || $v === 'inspect') {
                return true;
            }
        }

        return false;
    }

    protected function getActiveLocale(): string
    {
        if (self::$localeOverride !== null) {
            return self::$localeOverride;
        }

        $lang = $this->language->getLanguage();
        if (!$lang) {
            $lang = $this->language->getDefault() ?: 'en';
        }

        return $lang;
    }

    protected function toTimestamp($v): int
    {
        if ($v instanceof \DateTimeInterface) {
            return (int)$v->format('U');
        }
        if (is_numeric($v)) {
            return (int)$v;
        }
        $ts = strtotime((string)$v);
        return $ts !== false ? $ts : time();
    }

    protected function normalizeMonth($v): int
    {
        if (is_numeric($v)) {
            return (int)$v;
        }
        $s = (string)$v;
        if (preg_match('~^\d{4}-(\d{2})-\d{2}$~', $s, $m)) {
            return (int)$m[1];
        }
        return (int)$s;
    }

    protected function getMonthNames(string $locale): array
    {
        $cs = [
            'long'  => ['leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec'],
            'short' => ['led','úno','bře','dub','kvě','čer','čvc','srp','zář','říj','lis','pro'],
        ];
        $en = [
            'long'  => ['January','February','March','April','May','June','July','August','September','October','November','December'],
            'short' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ];

        switch (substr($locale, 0, 2)) {
            case 'cs': return $cs;
            case 'en': return $en;
            default:   return $en;
        }
    }

    protected function pluralCategory(string $locale, float $n): string
    {
        $lang = substr($locale, 0, 2);
        $i = (int)$n;

        switch ($lang) {
            case 'cs':
            case 'sk':
                if ($i == 1) return 'one';
                if ($i >= 2 && $i <= 4) return 'few';
                return 'other';
            case 'pl':
                if ($i == 1) return 'one';
                if ($i % 10 >= 2 && $i % 10 <= 4 and ($i % 100 < 12 or $i % 100 > 14)) return 'few';
                return 'other';
            case 'en':
            default:
                return ($i == 1) ? 'one' : 'other';
        }
    }

    protected function pluralOrder(string $locale): array
    {
        $lang = substr($locale, 0, 2);
        switch ($lang) {
            case 'cs':
            case 'sk':
            case 'pl':
                return ['one','few','other'];
            default:
                return ['one','other'];
        }
    }
}
