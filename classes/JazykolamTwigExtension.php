
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
            new TwigFilter('jazykolam_month', [$this, 'monthFilter'])
        ];
    }

    /**
     * Twig filter: selects proper plural form based on locale rules.
     *
     * Usage examples:
     *   {{ count|jazykolam_plural(['soubor','soubory','souborů']) }}
     *   {{ count|jazykolam_plural({'one':'file','other':'files'}, 'en') }}
     *   {{ count|jazykolam_plural('JAZYKOLAM.FILE') }}  {# will lookup JAZYKOLAM.FILE.<category> in languages.yaml #}
     *
     * @param int|float $count
     * @param array|string $forms  Either [..] in locale order, or {category:form}, or translation key prefix
     * @param string|null $locale  Optional override locale (e.g., 'cs'). Defaults to active language.
     * @return string
     */
    public function pluralFilter($count, $forms, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $category = $this->pluralCategory($locale, (float)$count);

        // If forms provided as translation key, fetch via languages.yaml
        if (is_string($forms)) {
            $key = $forms . '.' . $category;
            $translated = $this->translate($key, [$count]);
            if ($translated !== $key) {
                return $translated;
            }
            // Fallback chain through common categories
            foreach (['other','many','few','one'] as $fallback) {
                $fk = $forms . '.' . $fallback;
                $t = $this->translate($fk, [$count]);
                if ($t !== $fk) { return $t; }
            }
            // Could not resolve key – return raw key as last resort
            return (string)$forms;
        }

        // If associative forms map
        if (is_array($forms) && array_keys($forms) !== range(0, count($forms) - 1)) {
            if (isset($forms[$category])) {
                return $this->interpolate($forms[$category], $count);
            }
            // try fallbacks
            foreach (['other','many','few','one'] as $fallback) {
                if (isset($forms[$fallback])) {
                    return $this->interpolate($forms[$fallback], $count);
                }
            }
        }

        // If indexed forms array, map by locale-specific order
        if (is_array($forms)) {
            $order = $this->pluralOrder($locale);
            $idx = array_search($category, $order, true);
            if ($idx !== false && isset($forms[$idx])) {
                return $this->interpolate($forms[$idx], $count);
            }
            // fallback to last provided form
            return $this->interpolate(end($forms), $count);
        }

        // Unknown forms type
        return (string)$forms;
    }

    /**
     * Twig filter: returns localized month name.
     *
     * Usage examples:
     *   {{ 3|jazykolam_month }}                     => March (default long)
     *   {{ 3|jazykolam_month('short') }}           => Mar
     *   {{ '2025-11-07'|date('n')|jazykolam_month('genitive','cs') }} => listopadu
     *
     * @param int|string|\DateTimeInterface $value  Month number (1-12) or any string/int castable to month number
     * @param string $form  One of: long, short, genitive (if supported)
     * @param string|null $locale
     * @return string
     */
    public function monthFilter($value, string $form = 'long', ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $month = $this->normalizeMonth($value);
        if ($month < 1 || $month > 12) {
            return '';
        }

        // 1) Try languages.yaml overrides: JAZYKOLAM.MONTH.<FORM>.<N>
        $key = sprintf('JAZYKOLAM.MONTH.%s.%d', strtoupper($form), $month);
        $translated = $this->translate($key);
        if ($translated !== $key) {
            return $translated;
        }

        // 2) Try plugin config tables
        $months = (array)($this->config['locales'][$locale]['months'] ?? []);
        if (!$months && strpos($locale, '-') !== false) {
            $base = substr($locale, 0, 2);
            $months = (array)($this->config['locales'][$base]['months'] ?? []);
        }
        $form = strtolower($form);
        if (isset($months[$form][$month])) {
            return (string)$months[$form][$month];
        }

        // 3) Fallback to English
        $months = (array)($this->config['locales']['en']['months'] ?? []);
        if (isset($months[$form][$month])) {
            return (string)$months[$form][$month];
        }

        return '';
    }

    /**
     * Determine plural category for given locale and count, following simplified CLDR-like rules.
     */
    protected function pluralCategory(string $locale, float $n): string
    {
        $base = strtolower(substr($locale, 0, 2));
        switch ($base) {
            case 'cs': // Czech
            case 'sk': // Slovak
                if ((int)$n == 1) return 'one';
                $i = (int)$n;
                if ($i >= 2 && $i <= 4) return 'few';
                return 'other';
            case 'pl': // Polish
                $i = (int)$n; $v = $n - $i != 0.0; // decimals ignored for simplicity
                if ($i == 1 && !$v) return 'one';
                $n10 = $i % 10; $n100 = $i % 100;
                if (!$v && in_array($n10, [2,3,4], true) && !in_array($n100, [12,13,14], true)) return 'few';
                if (!$v && ($i != 1) and (in_array($n10, [0,1,5,6,7,8,9], true) or in_array($n100, [12,13,14], true))) return 'many';
                return 'other';
            case 'ru': // Russian (simplified)
                $i = (int)$n; $n10 = $i % 10; $n100 = $i % 100;
                if ($n10 == 1 && $n100 != 11) return 'one';
                if (in_array($n10, [2,3,4], true) && !in_array($n100, [12,13,14], true)) return 'few';
                return 'many';
            case 'fr': // French (CLDR: one if 0 or 1)
                if ($n >= 0 && $n < 2) return 'one';
                return 'other';
            case 'en': // English
            default:
                return ((int)$n == 1) ? 'one' : 'other';
        }
    }

    /**
     * Returns the order of plural forms for indexed arrays per locale
     * e.g., cs => ['one','few','other'], en => ['one','other']
     */
    protected function pluralOrder(string $locale): array
    {
        $base = strtolower(substr($locale, 0, 2));
        $order = $this->config['locales'][$base]['plural']['order'] ?? null;
        if (is_array($order)) return $order;
        switch ($base) {
            case 'cs':
            case 'sk':
                return ['one','few','other'];
            case 'pl':
                return ['one','few','many','other'];
            case 'fr':
                return ['one','other'];
            case 'en':
            default:
                return ['one','other'];
        }
    }

    protected function getActiveLocale(): string
    {
        $cfg = (string)($this->config['default_locale'] ?? '');
        if ($cfg) return $cfg;
        $lang = $this->language->getLanguage();
        if ($lang) return $lang;
        return 'en';
    }

    protected function translate(string $key, array $params = [])
    {
        // Leverage Grav language translations; returns original key if not found
        return $this->language->translate([$key], $params);
    }

    protected function normalizeMonth($value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return (int)$value->format('n');
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        $str = trim((string)$value);
        if ($str === '') return (int)date('n');
        // Try parse common formats YYYY-MM-DD
        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $str, $m)) {
            return (int)$m[2];
        }
        return (int)$str; // best effort
    }

    protected function interpolate($text, $count): string
    {
        // Replace simple placeholders like %d or {{count}}
        $out = (string)$text;
        if (strpos($out, '{{count}}') !== false) {
            $out = str_replace('{{count}}', (string)$count, $out);
        } elseif (strpos($out, '%d') !== false) {
            $out = sprintf($out, $count);
        }
        return $out;
    }
}
