<?php
// TEMPORARY capability test page for skeletonbelly.com. Delete after testing.
$m = $_GET['m'] ?? 'ui';

function nobuf() {
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-store');
    while (ob_get_level() > 0) { @ob_end_flush(); }
    ob_implicit_flush(true);
}

if ($m === 'sleep') {                       // does a long request survive?
    header('Content-Type: text/plain; charset=utf-8');
    $n = max(1, min(100, (int)($_GET['n'] ?? 40)));
    $t0 = microtime(true);
    sleep($n);
    printf("slept %d s (wall %.1f s), max_execution_time=%s\n", $n, microtime(true) - $t0, ini_get('max_execution_time'));
    exit;
}

if ($m === 'stream') {                      // does plain streaming reach the browser in pieces?
    header('Content-Type: text/plain; charset=utf-8');
    nobuf();
    $pad = max(0, min(65536, (int)($_GET['pad'] ?? 2048)));
    echo str_repeat(' ', $pad) . "\n";      // push past proxy buffers (size set by ?pad=)
    for ($i = 1; $i <= 8; $i++) {
        echo "chunk $i\n";
        flush();
        sleep(1);
    }
    echo "end\n";
    exit;
}

if ($m === 'claude') {                      // stream a real model response through PHP
    header('Content-Type: text/plain; charset=utf-8');
    nobuf();
    $stamp = sys_get_temp_dir() . '/capstest.stamp';
    if (is_file($stamp) && time() - filemtime($stamp) < 30) { http_response_code(429); exit("throttled: wait 30 s\n"); }
    touch($stamp);
    $keyFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/anthropic.key';
    $key = is_readable($keyFile) ? trim((string)file_get_contents($keyFile)) : '';
    if ($key === '') exit("no-key\n");
    $pad = max(0, min(65536, (int)($_GET['pad'] ?? 2048)));
    echo str_repeat(' ', $pad) . "\n";
    flush();
    $t0 = microtime(true); $first = null; $deltas = 0; $buf = '';
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-haiku-5-5', 'max_tokens' => 400, 'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'Tell a spooky 150-word story about a skeleton who lost his stomach.']],
        ]),
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'anthropic-version: 2023-06-01', 'x-api-key: ' . $key],
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60,
        CURLOPT_WRITEFUNCTION => function ($c, $data) use (&$buf, &$first, &$deltas, $t0) {
            $buf .= $data;
            while (($p = strpos($buf, "\n")) !== false) {
                $line = trim(substr($buf, 0, $p)); $buf = substr($buf, $p + 1);
                if (strpos($line, 'data:') !== 0) continue;
                $j = json_decode(trim(substr($line, 5)), true);
                if (($j['type'] ?? '') === 'content_block_delta' && isset($j['delta']['text'])) {
                    if ($first === null) { $first = microtime(true) - $t0; }
                    $deltas++;
                    echo $j['delta']['text']; flush();
                } elseif (($j['type'] ?? '') === 'error') {
                    echo "\n[api error: " . ($j['error']['type'] ?? '?') . "]\n"; flush();
                }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    printf("\n\n-- http %d, first text after %.2f s, total %.2f s, %d deltas%s\n",
        $code, $first ?? -1, microtime(true) - $t0, $deltas, $err ? ", curl: $err" : '');
    exit;
}

if ($m === 'info') {                        // limits, extensions, speed
    header('Content-Type: text/plain; charset=utf-8');
    $dis = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    echo "php " . PHP_VERSION . " / " . PHP_SAPI . "\n";
    foreach (['memory_limit','max_execution_time','max_input_time','post_max_size','upload_max_filesize','max_file_uploads','allow_url_fopen','open_basedir','file_uploads'] as $k)
        echo str_pad($k, 22) . ini_get($k) . "\n";
    echo "disable_functions: " . ($dis ? implode(' ', $dis) : '(none)') . "\n";
    echo "\nextensions: ";
    foreach (['gd','imagick','curl','mysqli','pdo_mysql','mbstring','zip','intl','sodium','openssl','sockets','exif','fileinfo','opcache','apcu','redis','memcached'] as $e)
        echo $e . '=' . (extension_loaded($e) ? 'yes' : 'no') . ' ';
    echo "\n";
    if (function_exists('gd_info')) {
        $g = gd_info();
        echo "gd: " . $g['GD Version'] . " jpeg=" . (int)!empty($g['JPEG Support']) . " png=" . (int)!empty($g['PNG Support'])
           . " webp=" . (int)!empty($g['WebP Support']) . " avif=" . (int)!empty($g['AVIF Support']) . " freetype=" . (int)!empty($g['FreeType Support']) . "\n";
        $t = microtime(true);
        $im = imagecreatetruecolor(2000, 2000);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 80, 40));
        $sm = imagescale($im, 200);
        printf("image: made 2000x2000 and scaled to 200px in %.0f ms\n", (microtime(true) - $t) * 1000);
    }
    $t = microtime(true); $h = 'x';
    for ($i = 0; $i < 1000000; $i++) { $h = md5($h); }
    printf("cpu: 1,000,000 md5 in %.0f ms\n", (microtime(true) - $t) * 1000);
    $before = memory_get_usage();
    $a = str_repeat('x', 100 * 1024 * 1024);
    printf("memory: allocated 100 MB ok, peak %.0f MB\n", memory_get_peak_usage() / 1048576);
    $canExec = function_exists('shell_exec') && !in_array('shell_exec', $dis, true);
    echo "shell_exec: " . ($canExec ? 'allowed' : 'disabled') . "\n";
    if ($canExec) {
        foreach (['ffmpeg','convert','magick','python3','node','git'] as $b)
            echo "  $b: " . (trim((string)shell_exec('command -v ' . $b . ' 2>/dev/null')) ?: 'not found') . "\n";
    }
    exit;
}

// default: small test UI
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><meta charset="utf-8"><title>caps test</title>
<style>body{font:15px system-ui;margin:1.5rem;max-width:46rem}button{margin:.2rem .3rem .2rem 0;padding:.4rem .8rem}pre{background:#111;color:#ddd;padding:1rem;white-space:pre-wrap;min-height:6rem}</style>
<h1>Capability tests</h1>
<button data-m="info">Info + speed</button>
<button data-m="sleep" data-n="40">Sleep 40 s</button>
<button data-m="sleep" data-n="75">Sleep 75 s</button>
<button data-m="stream">Stream timing (pad 2K)</button>
<button data-m="stream" data-pad="16384">Stream (pad 16K)</button>
<button data-m="stream" data-pad="65536">Stream (pad 64K)</button>
<button data-m="claude">Claude stream</button>
<button data-m="claude" data-pad="32768">Claude stream (pad 32K)</button>
<pre id="out">Pick a test.</pre>
<script>
const out = document.getElementById('out');
document.querySelectorAll('button').forEach(b => b.onclick = async () => {
  const m = b.dataset.m, n = b.dataset.n, pad = b.dataset.pad;
  out.textContent = 'running ' + m + (n ? ' ' + n : '') + '...\n';
  const t0 = performance.now(), stamp = () => ((performance.now() - t0) / 1000).toFixed(2) + 's';
  try {
    const r = await fetch('?m=' + m + (n ? '&n=' + n : '') + (pad ? '&pad=' + pad : ''), {cache: 'no-store'});
    out.textContent = 'HTTP ' + r.status + ' after ' + stamp() + '\n';
    const rd = r.body.getReader(), dec = new TextDecoder();
    for (;;) {
      const {done, value} = await rd.read();
      if (done) break;
      const txt = dec.decode(value, {stream: true});
      if (m === 'stream' || m === 'claude') out.textContent += '[' + stamp() + '] ' + (m === 'stream' ? txt.trim() + '\n' : txt);
      else out.textContent += txt;
    }
    out.textContent += '\nfinished after ' + stamp();
  } catch (e) { out.textContent += '\nFAILED after ' + stamp() + ': ' + e; }
});
</script>
