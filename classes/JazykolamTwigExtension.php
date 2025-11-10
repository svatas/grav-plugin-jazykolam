
<?php
use Grav\Common\Grav;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class JazykolamTwigExtension extends AbstractExtension
{
    protected $grav; protected $language; protected $config;
    protected static $localeOverride = null; // per-render override
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
            new TwigFilter('jazykolam_plural', [$this, 'pluralFilter']),
            new TwigFilter('jazykolam_month', [$this, 'monthFilter']),
            new TwigFilter('jazykolam_time', [$this, 'timeFilter'])
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('jazykolam_set_locale', [$this, 'setLocaleFunction']),
            new TwigFunction('jazykolam_debug', [$this, 'debugFunction'], ['is_safe' => ['html']]),
            new TwigFunction('jazykolam_debug_panel', [$this, 'debugPanelFunction'], ['is_safe' => ['html']]),
            new TwigFunction('jazykolam_debug_console', [$this, 'debugConsoleFunction'], ['is_safe' => ['html']])
        ];
    }

    public function setLocaleFunction(?string $locale = null): string
    { self::$localeOverride = $locale ? (string)$locale : null; return ''; }

    // -------------------- Filters --------------------
    public function pluralFilter($count, $forms, ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $category = $this->pluralCategory($locale, (float)$count);
        $source = 'plural'; $keyUsed = null; $out = null;

        if (is_string($forms)) {
            $keyUsed = $forms . '.' . $category;
            $translated = $this->translate($keyUsed, [$count], $locale);
            if ($translated !== $keyUsed) { $out = $this->interpolate($translated, $count); }
            else {
                foreach (['other','many','few','one'] as $fallback) {
                    $fk = $forms . '.' . $fallback;
                    $t = $this->translate($fk, [$count], $locale);
                    if ($t !== $fk) { $keyUsed = $fk; $out = $this->interpolate($t, $count); break; }
                }
            }
            if ($out === null) $out = (string)$forms;
        } elseif (is_array($forms) && array_keys($forms) !== range(0, count($forms) - 1)) {
            $keyUsed = '{map}';
            if (isset($forms[$category])) $out = $this->interpolate($forms[$category], $count);
            else { foreach (['other','many','few','one'] as $fallback) { if (isset($forms[$fallback])) { $out = $this->interpolate($forms[$fallback], $count); break; } } }
        } elseif (is_array($forms)) {
            $order = $this->pluralOrder($locale); $idx = array_search($category, $order, true);
            $out = $this->interpolate(($idx!==false && isset($forms[$idx]))? $forms[$idx] : end($forms), $count); $keyUsed = '{array}';
        } else { $out = (string)$forms; $keyUsed = '{string}'; }

        return $this->maybeDebugWrap($out, [ 'source'=>$source, 'key'=>$keyUsed, 'locale'=>$locale, 'meta'=>['category'=>$category,'count'=>$count] ]);
    }

    public function monthFilter($value, string $form = 'long', ?string $locale = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $month = $this->normalizeMonth($value); if ($month<1||$month>12) return '';
        $source='month'; $keyUsed = sprintf('JAZYKOLAM.MONTH.%s.%d', strtoupper($form), $month);
        $translated = $this->translate($keyUsed, [], $locale);
        if ($translated !== $keyUsed) $out = (string)$translated;
        else {
            $months = (array)($this->config['locales'][$locale]['months'] ?? []);
            if (!$months && strpos($locale, '-')!==false) { $base=substr($locale,0,2); $months=(array)($this->config['locales'][$base]['months']??[]); }
            $form=strtolower($form);
            if (isset($months[$form][$month])) $out=(string)$months[$form][$month];
            else { $months=(array)($this->config['locales']['en']['months']??[]); $out=isset($months[$form][$month])?(string)$months[$form][$month]:''; $keyUsed='{config}'; }
        }
        return $this->maybeDebugWrap($out, ['source'=>$source,'key'=>$keyUsed,'locale'=>$locale,'meta'=>['form'=>$form,'month'=>$month]]);
    }

    public function timeFilter($value, ?string $locale = null, $now = null): string
    {
        $locale = $locale ?: $this->getActiveLocale();
        $nowTs = $this->toTimestamp($now ?? 'now'); $delta=$this->valueToDeltaSeconds($value,$nowTs); $abs=abs($delta); $source='time';
        if ($abs < 45) {
            $key='JAZYKOLAM.RELATIVE.NOW'; $t=$this->translate($key, [], $locale);
            $out = ($t !== $key) ? $t : (string)($this->getRelativeConfig($locale)['now'] ?? ($this->getRelativeConfig('en')['now'] ?? 'just now'));
            return $this->maybeDebugWrap($out, ['source'=>$source,'key'=>$key,'locale'=>$locale,'meta'=>['delta'=>$delta]]);
        }
        $direction = ($delta < 0) ? 'past' : 'future';
        $units = [ ['year',31536000], ['month',2592000], ['week',604800], ['day',86400], ['hour',3600], ['minute',60], ['second',1] ];
        $unit='second'; $count=1; foreach($units as [$u,$sec]){ if($abs>=$sec){ $unit=$u; $count=(int)floor($abs/$sec); break; } }
        $category=$this->pluralCategory($locale,(float)$count);
        $key=sprintf('JAZYKOLAM.RELATIVE.%s.%s.%s',strtoupper($direction),strtoupper($unit),$category);
        $translated=$this->translate($key,[$count],$locale);
        if($translated!==$key) $out=$this->interpolate($translated,$count);
        else { $keyOther=sprintf('JAZYKOLAM.RELATIVE.%s.%s.other',strtoupper($direction),strtoupper($unit)); $translated=$this->translate($keyOther,[$count],$locale); if($translated!==$keyOther) $out=$this->interpolate($translated,$count); else { $rel=$this->getRelativeConfig($locale); $forms=(array)($rel[$direction][$unit]??[]); if(isset($forms[$category])) $out=$this->interpolate($forms[$category],$count); elseif(isset($forms['other'])) $out=$this->interpolate($forms['other'], $count); else { $relEn=$this->getRelativeConfig('en'); $forms=(array)($relEn[$direction][$unit]??[]); $out=isset($forms['other'])?$this->interpolate($forms['other'],$count):''; $key='{config}'; } } }
        return $this->maybeDebugWrap($out, ['source'=>$source,'key'=>$key,'locale'=>$locale,'meta'=>['direction'=>$direction,'unit'=>$unit,'category'=>$category,'count'=>$count,'delta'=>$delta]]);
    }

    // -------------------- Auto overrides --------------------
    public function autoT($value, ...$params)
    {
        $locale = $this->getActiveLocale(); $args = $params[0] ?? []; if(!is_array($args)) $args=[]; $count=$args['count']??($args[0]??null); $key=(string)$value; $source='autoT'; $out=null; $keyUsed=$key;
        $raw=$this->translateRaw($key,$locale);
        if(is_string($raw) && strpos($raw,'{count, plural,')!==false && $count!==null){ $selected=$this->parseIcuPlural($raw,(float)$count,$locale); $out=$this->interpolate($selected,$count); }
        elseif(is_array($raw) && $count!==null){ $category=$this->pluralCategory($locale,(float)$count); if(isset($raw[$category])) $out=$this->interpolate($raw[$category],$count); elseif(isset($raw['other'])) $out=$this->interpolate($raw['other'],$count); }
        if($out===null){ $out=$this->language->translate([$key],$args); }
        return $this->maybeDebugWrap($out, ['source'=>$source,'key'=>$keyUsed,'locale'=>$locale,'meta'=>['count'=>$count]]);
    }

    public function autoNicetime($value){ return $this->timeFilter($value,$this->getActiveLocale()); }

    // -------------------- Debug helpers --------------------
    public function debugFunction($value, array $meta = [])
    { $meta += ['source'=>'manual','key':'{manual}','locale'=>$this->getActiveLocale()]; return $this->maybeDebugWrap((string)$value, $meta); }

    public function debugPanelFunction(): string
    {
        if (!$this->isDebugEnabled()) return '';
        $items = self::$debugLog; if (!$items) return '';
        $rows=''; foreach($items as $i){ $rows.=sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>', htmlspecialchars($i['source']??'',ENT_QUOTES), htmlspecialchars($i['locale']??'',ENT_QUOTES), htmlspecialchars($i['key']??'',ENT_QUOTES), htmlspecialchars(mb_strimwidth($i['out']??'',0,120,'…'),ENT_QUOTES)); }
        $html = '<div id="jl-debug-panel" style="position:fixed;bottom:10px;right:10px;z-index:99999;background:#111;color:#eee;border:1px solid #444;border-radius:6px;font:12px/1.4 monospace;box-shadow:0 2px 10px rgba(0,0,0,.4);">'
              . '<div style="padding:6px 10px;border-bottom:1px solid #444;display:flex;align-items:center;gap:8px;">'
              . '<strong>Jazykolam DEBUG</strong>'
              . '<button onclick="var b=document.getElementById('jl-debug-body');b.style.display=(b.style.display=='none' ? 'block' : 'none');" style="margin-left:auto;background:#444;color:#eee;border:0;padding:4px 8px;border-radius:4px;cursor:pointer;">toggle</button>'
              . '</div>'
              . '<div id="jl-debug-body" style="max-height:260px;overflow:auto;display:block;">'
              . '<table style="border-collapse:collapse;width:100%">'
              . '<thead><tr style="background:#222"><th style="text-align:left;padding:6px;border-bottom:1px solid #333;">source</th><th style="text-align:left;padding:6px;border-bottom:1px solid #333;">locale</th><th style="text-align:left;padding:6px;border-bottom:1px solid #333;">key</th><th style="text-align:left;padding:6px;border-bottom:1px solid #333;">output</th></tr></thead>'
              . '<tbody>' . $rows . '</tbody></table></div></div>';
        if (!empty($this->config['debug']['console'])) { $html .= $this->debugConsoleFunction(); }
        return $html;
    }

    public function debugConsoleFunction(): string
    {
        if (!$this->isDebugEnabled() && empty($this->grav['uri']->query('jazykolam_debug'))) return '';
        $log = json_encode(self::$debugLog, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return '<script>(function(){try{var d='+$log+';if(Array.isArray(d)&&d.length){console.group("Jazykolam DEBUG");console.table(d.map(function(x){return {source:x.source,locale:x.locale,key:x.key,out:x.out};}));console.groupEnd();}}catch(e){}})();</script>';
    }

    protected function maybeDebugWrap(string $out, array $info): string
    {
        if (!$this->isDebugEnabled() && empty($this->grav['uri']->query('jazykolam_debug'))) return $out;
        $max = (int)($this->config['debug']['max_entries'] ?? 200);
        $entry = [ 'source'=>$info['source']??'', 'key'=>$info['key']??'', 'locale'=>$info['locale']??'', 'meta'=>$info['meta']??[], 'out'=>$out ];
        self::$debugLog[] = $entry; if (count(self::$debugLog) > $max) array_shift(self::$debugLog);
        $prefix = (string)($this->config['debug']['marker_prefix'] ?? '‹JL:');
        $suffix = (string)($this->config['debug']['marker_suffix'] ?? '›');
        $badge  = (string)($this->config['debug']['badge'] ?? 'JL');
        $attrs = sprintf('data-jl-source="%s" data-jl-key="%s" data-jl-locale="%s"', htmlspecialchars($entry['source'], ENT_QUOTES), htmlspecialchars($entry['key'], ENT_QUOTES), htmlspecialchars($entry['locale'], ENT_QUOTES));
        $mode = (string)($this->config['debug']['mode'] ?? 'inline');
        if ($mode === 'inline') {
            return sprintf('<span class="jl-debug" %s>%s%s%s<sup class="jl-badge" style="font-size:9px;background:#f39c12;color:#000;padding:1px 3px;border-radius:3px;margin-left:2px;">%s</sup></span>', $attrs, htmlspecialchars($prefix, ENT_QUOTES), $out, htmlspecialchars($suffix, ENT_QUOTES), htmlspecialchars($badge, ENT_QUOTES));
        }
        return sprintf('<span class="jl-debug" %s>%s<sup class="jl-badge" style="font-size:9px;background:#f39c12;color:#000;padding:1px 3px;border-radius:3px;margin-left:2px;">%s</sup></span>', $attrs, $out, htmlspecialchars($badge, ENT_QUOTES));
    }

    protected function isDebugEnabled(): bool
    { $enabled = (bool)($this->config['debug']['enabled'] ?? false); return $enabled; }

    // -------------------- Core helpers --------------------
    protected function getRelativeConfig(string $locale): array
    { $base=strtolower(substr($locale,0,2)); return (array)($this->config['locales'][$locale]['relative'] ?? $this->config['locales'][$base]['relative'] ?? []); }

    protected function toTimestamp($value): int
    { if ($value instanceof \DateTimeInterface) return $value->getTimestamp(); if (is_int($value)) return $value; if (is_numeric($value)) return (int)$value; $ts=strtotime((string)$value); return $ts!==false?$ts:time(); }

    protected function valueToDeltaSeconds($value, int $nowTs): int
    { if ($value instanceof \DateTimeInterface) return $value->getTimestamp()-$nowTs; if (is_int($value)||is_float($value)) return (int)$value; $ts=strtotime((string)$value); return $ts!==false?$ts-$nowTs:0; }

    protected function pluralCategory(string $locale, float $n): string
    { $b=strtolower(substr($locale,0,2)); switch($b){ case 'cs': case 'sk': if((int)$n==1) return 'one'; $i=(int)$n; if($i>=2&&$i<=4) return 'few'; return 'other'; case 'pl': $i=(int)$n; $v=$n-$i!=0.0; if($i==1&&!$v) return 'one'; $n10=$i%10; $n100=$i%100; if(!$v&&in_array($n10,[2,3,4],true)&&!in_array($n100,[12,13,14],true)) return 'few'; if(!$v&&($i!=1)&&(in_array($n10,[0,1,5,6,7,8,9],true)||in_array($n100,[12,13,14],true))) return 'many'; return 'other'; case 'ru': $i=(int)$n; $n10=$i%10; $n100=$i%100; if($n10==1&&$n100!=11) return 'one'; if(in_array($n10,[2,3,4],true)&&!in_array($n100,[12,13,14],true)) return 'few'; return 'many'; case 'fr': if($n>=0&&$n<2) return 'one'; return 'other'; case 'en': default: return ((int)$n==1)?'one':'other'; } }

    protected function pluralOrder(string $locale): array
    { $b=strtolower(substr($locale,0,2)); switch($b){ case 'cs': case 'sk': return ['one','few','other']; case 'pl': return ['one','few','many','other']; case 'fr': return ['one','other']; case 'en': default: return ['one','other']; } }

    protected function getActiveLocale(): string
    { if(self::$localeOverride) return self::$localeOverride; $cfg=(string)($this->config['default_locale']??''); if($cfg) return $cfg; $lang=$this->language->getLanguage(); return $lang?:'en'; }

    protected function translate(string $key, array $params = [], ?string $locale = null)
    { return $this->language->translate([$key], $params); }

    protected function translateRaw(string $key, string $locale)
    { try{ $lang=$this->language; $data=$lang->getTranslation($locale); if(is_array($data)){ $parts=explode('.', $key); $val=$data; foreach($parts as $p){ if(!is_array($val)||!array_key_exists($p,$val)){ $val=null; break; } $val=$val[$p]; } if($val!==null) return $val; } }catch(\Throwable $e){} return $key; }

    protected function normalizeMonth($value): int
    { if ($value instanceof \DateTimeInterface) return (int)$value->format('n'); if (is_numeric($value)) return (int)$value; $s=trim((string)$value); if($s==='') return (int)date('n'); if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~',$s,$m)) return (int)$m[2]; return (int)$s; }

    protected function interpolate($text, $count): string
    { $out=(string)$text; if(strpos($out,'{{count}}')!==false) $out=str_replace('{{count}}',(string)$count,$out); elseif(strpos($out,'%d')!==false) $out=sprintf($out,$count); return $out; }

    protected function parseIcuPlural(string $pattern, float $count, string $locale): string
    { $cat=$this->pluralCategory($locale,$count); $map=[]; if(preg_match_all('~(one|few|many|other)\s*\{([^}]*)\}~u',$pattern,$m,PREG_SET_ORDER)){ foreach($m as $mm){ $map[$mm[1]]=$mm[2]; } } if(isset($map[$cat])) return $map[$cat]; if(isset($map['other'])) return $map['other']; return (string)$pattern; }
}
