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

    /** @var string|null */
    protected static $localeOverride = null;

    /** @var array */
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
            new TwigFilter('jazykolam_plural', [$this, 'jazykolamPlural'], ['is_safe' => ['html']]),
            new TwigFilter('jazykolam_month', [$this, 'jazykolamMonth'], ['is_safe' => ['html']]),
            new TwigFilter('jazykolam_time', [$this, 'jazykolamTime'], ['is_safe' => ['html']]),
            new TwigFilter('jazykolam_debug', [$this, 'debugFilter'], ['is_safe' => ['html']]),
            // These are used when auto_override is enabled
            new TwigFilter('jazykolam_t', [$this, 'autoT'], ['is_variadic' => true, 'is_safe' => ['html']]),
            new TwigFilter('jazykolam_nicetime', [$this, 'autoNicetime'], ['is_variadic' => true, 'is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('jazykolam_set_locale', [$this, 'setLocaleFunction']),
            new TwigFunction('jazykolam_debug', [$this, 'debugFunction'], ['is_safe' => ['html']]),
            new TwigFunction('jazykolam_debug_panel', [$this, 'debugPanelFunction'], ['is_safe' => ['html']]),
            new TwigFunction('jazykolam_debug_console', [$this, 'debugConsoleFunction'], ['is_safe' => ['html']]),
        ];
    }

    /* ========= Core translation wrappers ========= */

    /**
     * Replacement for |t, |trans, etc. when auto_override.t is enabled.
     * Accepts (key, [params]) style arguments.
     */
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
        $locale = $locale ?: $this->getActiveLocale();

        $text = $this->language->translate([$key], $params);
        $text = is_array($text) ? json_encode($text) : (string)$text;

        self::$debugLog[] = sprintf('t(%s,%s)', $key, $locale);

        return $this->wrapInline($key, $locale, $text);
    }

    /**
     * Replacement for |nicetime when auto_override.nicetime is enabled.
     */
    public function autoNicetime(...$args): string
    {
        $value = $args[0] ?? null;
        $locale = $this->getActiveLocale();

        $out = $this->jazykolamTime($value, $locale);

        self::$debugLog[] = sprintf('nicetime(%s,%s)', (string)$value, $locale);

        return $out;
    }

    /* ========= Explicit Jazykolam filters ========= */

    public function jazykolamPlural($count, array $forms, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $category = $this->pluralCategory($locale, (float)$count);

        $order = $this->pluralOrder($locale);
        $text = null;

        // Forms as ['one' => '', 'few' => '', 'other' => '']
        if (isset($forms[$category])) {
            $text = $forms[$category];
        } else {
            // fallback in defined order
            foreach ($order as $cat) {
                if (isset($forms[$cat])) {
                    $text = $forms[$cat];
                    break;
                }
            }
        }

        if ($text === null) {
            $text = (string)reset($forms);
        }

        $text = $this->interpolate($text, $count);

        self::$debugLog[] = sprintf('plural(%s,%s,%s)', json_encode($forms), $count, $locale);

        // No inline wrapper here because this is usually direct literal usage
        return $text;
    }

    public function jazykolamMonth($value, string $form = 'long', ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $month = $this->normalizeMonth($value);
        if ($month < 1 || $month > 12) {
            return '';
        }

        $names = $this->getMonthNames($locale);
        $index = $month - 1;

        $result = $names[$form][$index] ?? $names['long'][$index] ?? '';

        self::$debugLog[] = sprintf('month(%d,%s,%s)', $month, $form, $locale);

        return $result;
    }

    public function jazykolamTime($value, ?string $locale = null, $now = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $nowTs = $this->toTimestamp($now ?: time());
        $diff = $this->valueToDeltaSeconds($value, $nowTs);

        $dir = $diff >= 0 ? 'future' : 'past';
        $sec = abs($diff);

        if ($sec < 60) {
            $n = $sec;
            $unit = 'second';
        } elseif ($sec < 3600) {
            $n = floor($sec / 60);
            $unit = 'minute';
        } elseif ($sec < 86400) {
            $n = floor($sec / 3600);
            $unit = 'hour';
        } elseif ($sec < 86400 * 30) {
            $n = floor($sec / 86400);
            $unit = 'day';
        } elseif ($sec < 86400 * 365) {
            $n = floor($sec / (86400 * 30));
            $unit = 'month';
        } else {
            $n = floor($sec / (86400 * 365));
            $unit = 'year';
        }

        $key = sprintf('JZK.NICETIME.%s.%s', strtoupper($dir), strtoupper($unit));
        $forms = $this->language->translate([$key], ['%count%' => $n]);
        if (is_array($forms)) {
            // Support ICU-lite like structure
            $text = $this->jazykolamPlural($n, $forms, $locale);
        } else {
            $text = strtr((string)$forms, ['%count%' => $n, '{count}' => $n, '#'=> $n]);
        }

        self::$debugLog[] = sprintf('time(%s,%s) => %s', (string)$value, $locale, $text);

        return $text;
    }

    public function debugFilter($value): string
    {
        if (!$this->isDebugEnabled()) {
            return (string)$value;
        }
        return '<span class="jazykolam-debug">' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }

    /* ========= Twig functions ========= */

    public function setLocaleFunction(?string $locale = null): string
    {
        self::$localeOverride = $locale ? (string)$locale : null;
        return '';
    }

    public function debugFunction($value): string
    {
        return $this->debugFilter($value);
    }

    public function debugPanelFunction(): string
    {
        if (!$this->isDebugEnabled()) {
            return '';
        }
        $out = '<div class="jazykolam-debug-panel">';
        foreach (self::$debugLog as $row) {
            $out .= htmlspecialchars((string)$row, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
        }
        $out .= '</div>';
        return $out;
    }

    public function debugConsoleFunction(): string
    {
        if (!$this->isDebugEnabled() || empty(self::$debugLog)) {
            return '';
        }
        $json = json_encode(array_values(self::$debugLog));
        return "<script>(function(){try{console.group && console.group('Jazykolam');var d={$json};for(var i=0;i<d.length;i++){console.log(d[i]);}console.groupEnd && console.groupEnd();}catch(e){}})();</script>";
    }

    /* ========= Inline edit integration ========= */

    public static function getDebugLog(): array
    {
        return self::$debugLog;
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
        $cfg = (array)$this->config;
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
        if ($uri && ($uri->query('jazykolam_inline') == 1 || $uri->param('jazykolam_inline') == 1)) {
            return true;
        }

        return false;
    }

    /* ========= Helpers ========= */

    protected function isDebugEnabled(): bool
    {
        return (bool)($this->config['debug']['enabled'] ?? false);
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

    protected function valueToDeltaSeconds($v, int $now): int
    {
        if ($v instanceof \DateTimeInterface) {
            return (int)$v->format('U') - $now;
        }
        if (is_numeric($v)) {
            return (int)$v - $now;
        }
        $ts = strtotime((string)$v);
        return $ts !== false ? $ts - $now : 0;
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
        // Minimal built-in set; can be overridden by user translations
        $cs = [
            'long'  => ['leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec'],
            'short' => ['led','úno','bře','dub','kvě','čer','čvc','srp','zář','říj','lis','pro'],
            'gen'   => ['ledna','února','března','dubna','května','června','července','srpna','září','října','listopadu','prosince'],
        ];
        $en = [
            'long'  => ['January','February','March','April','May','June','July','August','September','October','November','December'],
            'short' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            'gen'   => ['January','February','March','April','May','June','July','August','September','October','November','December'],
        ];

        switch (substr($locale, 0, 2)) {
            case 'cs': return $cs;
            case 'en': return $en;
            default:   return $en;
        }
    }

    protected function interpolate(string $text, $count): string
    {
        $text = str_replace(['{count}', '%count%', '#'], (string)$count, $text);
        return $text;
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
                if ($i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14)) return 'few';
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
                return ['one', 'few', 'other'];
            default:
                return ['one', 'other'];
        }
    }
}
