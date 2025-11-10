
<?php
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JazykolamTwigExtension extends AbstractExtension
{
    /** @var Grav */
    protected $grav;

    /** @var Language */
    protected $language;

    /** @var array */
    protected $config;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->language = $grav['language'];
        $this->config = (array)$grav['config']->get('plugins.jazykolam');
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('jazykolam_plural', [$this, 'pluralFilter']),
            new TwigFilter('jazykolam_month', [$this, 'monthFilter']),
            new TwigFilter('jazykolam_time', [$this, 'timeFilter'])
        ];
    }

    /*** Explicit filters ***/

    public function pluralFilter($count, $forms, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $category = $this->pluralCategory($locale, (float)$count);

        if (is_string($forms)) {
            $key = $forms . '.' . $category;
            $translated = $this->translate($key, [$count]);
            if ($translated !== $key) {
                return $this->interpolate($translated, $count);
            }
            foreach (['other','many','few','one'] as $fallback) {
                $fk = $forms . '.' . $fallback;
                $t = $this->translate($fk, [$count]);
                if ($t !== $fk) { return $this->interpolate($t, $count); }
            }
            return (string)$forms;
        }

        if (is_array($forms) && array_keys($forms) !== range(0, count($forms) - 1)) {
            if (isset($forms[$category])) {
                return $this->interpolate($forms[$category], $count);
            }
            foreach (['other','many','few','one'] as $fallback) {
                if (isset($forms[$fallback])) {
                    return $this->interpolate($forms[$fallback], $count);
                }
            }
        }

        if (is_array($forms)) {
            $order = $this->pluralOrder($locale);
            $idx = array_search($category, $order, true);
            if ($idx !== false && isset($forms[$idx])) {
                return $this->interpolate($forms[$idx], $count);
            }
            return $this->interpolate(end($forms), $count);
        }

        return (string)$forms;
    }

    public function monthFilter($value, string $form = 'long', ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $month = $this->normalizeMonth($value);
        if ($month < 1 || $month > 12) {
            return '';
        }

        $key = sprintf('JAZYKOLAM.MONTH.%s.%d', strtoupper($form), $month);
        $translated = $this->translate($key);
        if ($translated !== $key) {
            return $translated;
        }

        $months = (array)($this->config['locales'][$locale]['months'] ?? []);
        if (!$months && strpos($locale, '-') !== false) {
            $base = substr($locale, 0, 2);
            $months = (array)($this->config['locales'][$base]['months'] ?? []);
        }
        $form = strtolower($form);
        if (isset($months[$form][$month])) {
            return (string)$months[$form][$month];
        }

        $months = (array)($this->config['locales']['en']['months'] ?? []);
        if (isset($months[$form][$month])) {
            return (string)$months[$form][$month];
        }

        return '';
    }

    public function timeFilter($value, ?string $locale = null, $now = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $nowTs = $this->toTimestamp($now ?? 'now');
        $delta = $this->valueToDeltaSeconds($value, $nowTs);

        $abs = abs($delta);
        if ($abs < 45) {
            $key = 'JAZYKOLAM.RELATIVE.NOW';
            $t = $this->translate($key);
            if ($t !== $key) return $t;
            $rel = $this->getRelativeConfig($locale);
            return (string)($rel['now'] ?? ($this->getRelativeConfig('en')['now'] ?? 'just now'));
        }

        $direction = ($delta < 0) ? 'past' : 'future';
        $units = [
            ['year', 365*24*3600],
            ['month', 30*24*3600],
            ['week', 7*24*3600],
            ['day', 24*3600],
            ['hour', 3600],
            ['minute', 60],
            ['second', 1]
        ];

        $unit = 'second';
        $count = 1;
        foreach ($units as [$u, $sec]) {
            if ($abs >= $sec) { $unit = $u; $count = (int)floor($abs/$sec); break; }
        }

        $category = $this->pluralCategory($locale, (float)$count);
        $key = sprintf('JAZYKOLAM.RELATIVE.%s.%s.%s', strtoupper($direction), strtoupper($unit), $category);
        $translated = $this->translate($key, [$count]);
        if ($translated !== $key) return $this->interpolate($translated, $count);
        $keyOther = sprintf('JAZYKOLAM.RELATIVE.%s.%s.other', strtoupper($direction), strtoupper($unit));
        $translated = $this->translate($keyOther, [$count]);
        if ($translated !== $keyOther) return $this->interpolate($translated, $count);

        $rel = $this->getRelativeConfig($locale);
        $forms = (array)($rel[$direction][$unit] ?? []);
        if (isset($forms[$category])) return $this->interpolate($forms[$category], $count);
        if (isset($forms['other'])) return $this->interpolate($forms['other'], $count);
        $relEn = $this->getRelativeConfig('en');
        $forms = (array)($relEn[$direction][$unit] ?? []);
        if (isset($forms[$category])) return $this->interpolate($forms[$category], $count);
        if (isset($forms['other'])) return $this->interpolate($forms['other'], $count);
        return '';
    }

    /*** Automatic overrides (no template changes) ***/

    /**
     * Replacement for Grav's |t filter: adds plural logic & ICU-lite parsing if possible,
     * then falls back to Grav translation.
     * Signature: {{ 'KEY'|t(params) }} or {{ 'KEY'|t }}
     */
    public function autoT($value, ...$params)
    {
        $locale = $this->getActiveLocale();
        $args = $params[0] ?? [];
        if (!is_array($args)) { $args = []; }
        $count = $args['count'] ?? ($args[0] ?? null);
        $key = (string)$value;

        // 1) Get the raw translation (could be string or array if provided by site languages)
        $raw = $this->language->translate([$key]);

        // 2) If ICU-like plural pattern is present, select form
        if (is_string($raw) && strpos($raw, '{count, plural,') !== false && $count !== null) {
            $selected = $this->parseIcuPlural($raw, (float)$count, $locale);
            return $this->interpolate($selected, $count);
        }

        // 3) If translation is an array of categories (one/few/many/other)
        if (is_array($raw) && $count !== null) {
            $category = $this->pluralCategory($locale, (float)$count);
            if (isset($raw[$category])) return $this->interpolate($raw[$category], $count);
            if (isset($raw['other'])) return $this->interpolate($raw['other'], $count);
        }

        // 4) Fallback to standard translate with params
        return $this->language->translate([$key], $args);
    }

    /**
     * Replacement for |nicetime → delegates to jazykolam_time using active locale.
     */
    public function autoNicetime($value)
    {
        return $this->timeFilter($value, $this->getActiveLocale());
    }

    /** Utility helpers **/

    protected function getRelativeConfig(string $locale): array
    {
        $base = strtolower(substr($locale, 0, 2));
        $rel = (array)($this->config['locales'][$locale]['relative'] ?? $this->config['locales'][$base]['relative'] ?? []);
        return $rel;
    }

    protected function toTimestamp($value): int
    {
        if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
        if (is_int($value)) return $value;
        if (is_numeric($value)) return (int)$value;
        $ts = strtotime((string)$value);
        return $ts !== false ? $ts : time();
    }

    protected function valueToDeltaSeconds($value, int $nowTs): int
    {
        if ($value instanceof \DateTimeInterface) return $value->getTimestamp() - $nowTs;
        if (is_int($value) || is_float($value)) return (int)$value;
        $ts = strtotime((string)$value);
        return $ts !== false ? $ts - $nowTs : 0;
    }

    protected function pluralCategory(string $locale, float $n): string
    {
        $base = strtolower(substr($locale, 0, 2));
        switch ($base) {
            case 'cs':
            case 'sk':
                if ((int)$n == 1) return 'one';
                $i = (int)$n; if ($i >= 2 && $i <= 4) return 'few';
                return 'other';
            case 'pl':
                $i = (int)$n; $v = $n - $i != 0.0;
                if ($i == 1 && !$v) return 'one';
                $n10 = $i % 10; $n100 = $i % 100;
                if (!$v && in_array($n10, [2,3,4], true) && !in_array($n100, [12,13,14], true)) return 'few';
                if (!$v && ($i != 1) && (in_array($n10, [0,1,5,6,7,8,9], true) || in_array($n100, [12,13,14], true))) return 'many';
                return 'other';
            case 'ru':
                $i = (int)$n; $n10 = $i % 10; $n100 = $i % 100;
                if ($n10 == 1 && $n100 != 11) return 'one';
                if (in_array($n10, [2,3,4], true) && !in_array($n100, [12,13,14], true)) return 'few';
                return 'many';
            case 'fr':
                if ($n >= 0 && $n < 2) return 'one';
                return 'other';
            case 'en':
            default:
                return ((int)$n == 1) ? 'one' : 'other';
        }
    }

    protected function pluralOrder(string $locale): array
    {
        $base = strtolower(substr($locale, 0, 2));
        $order = $this->config['locales'][$base]['plural']['order'] ?? null;
        if (is_array($order)) return $order;
        switch ($base) {
            case 'cs':
            case 'sk': return ['one','few','other'];
            case 'pl': return ['one','few','many','other'];
            case 'fr': return ['one','other'];
            case 'en':
            default: return ['one','other'];
        }
    }

    protected function getActiveLocale(): string
    {
        $cfg = (string)($this->config['default_locale'] ?? '');
        if ($cfg) return $cfg;
        $lang = $this->language->getLanguage();
        return $lang ?: 'en';
    }

    protected function translate(string $key, array $params = [])
    {
        return $this->language->translate([$key], $params);
    }

    protected function normalizeMonth($value): int
    {
        if ($value instanceof \DateTimeInterface) return (int)$value->format('n');
        if (is_numeric($value)) return (int)$value;
        $str = trim((string)$value);
        if ($str === '') return (int)date('n');
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $str, $m)) return (int)$m[2];
        return (int)$str;
    }

    protected function interpolate($text, $count): string
    {
        $out = (string)$text;
        if (strpos($out, '{{count}}') !== false) {
            $out = str_replace('{{count}}', (string)$count, $out);
        } elseif (strpos($out, '%d') !== false) {
            $out = sprintf($out, $count);
        }
        return $out;
    }

    /** Very small ICU-like plural parser for patterns like:
     * "{count, plural, one {File} other {Files}}"
     */
    protected function parseIcuPlural(string $pattern, float $count, string $locale): string
    {
        $category = $this->pluralCategory($locale, $count);
        $map = [];
        if (preg_match_all('~(one|few|many|other)\s*\{([^}]*)\}~u', $pattern, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) { $map[$mm[1]] = $mm[2]; }
        }
        if (isset($map[$category])) return $map[$category];
        if (isset($map['other'])) return $map['other'];
        return (string)$pattern;
    }
}
