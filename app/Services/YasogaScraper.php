<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Process\Process;

class YasogaScraper
{
    const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    /**
     * Scrape reviews for a Google Maps place URL (short links are resolved first).
     *
     * @param  string  $url
     * @param  int  $pages  Pages of 10 reviews each
     * @param  string  $sort  relevant|newest|highest_rating|lowest_rating
     * @return array  {url, count, reviews}
     */
    public function run($url, $pages = 1, $sort = 'newest')
    {
        $url = $this->resolveUrl($url);

        $script = dirname(config('scraper.yasoga.path')) . DIRECTORY_SEPARATOR . 'yasoga-run.mjs';
        if (!is_file($script) || !is_file(config('scraper.yasoga.path') . '/dist/index.js')) {
            throw new RuntimeException('Yasoga scraper belum terpasang/ter-build di ' . config('scraper.yasoga.path'));
        }

        $command = [
            config('scraper.yasoga.node'), $script,
            '--url', $url,
            '--pages', (string) $pages,
            '--sort', $sort,
            '--lib', config('scraper.yasoga.path'),
        ];
        if (config('scraper.yasoga.proxy')) {
            array_push($command, '--proxy', config('scraper.yasoga.proxy'));
        }

        $process = new Process($command, null, null, null, config('scraper.yasoga.timeout'));
        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            throw new RuntimeException('Scrape melebihi batas waktu ' . config('scraper.yasoga.timeout') . ' detik.');
        }

        $result = json_decode($process->getOutput(), true);
        if (!is_array($result)) {
            $detail = trim($process->getErrorOutput()) ?: 'Node tidak menghasilkan output. Pastikan Node >= 22.12 terpasang.';
            throw new RuntimeException(mb_substr($detail, 0, 300));
        }
        if (empty($result['ok'])) {
            throw new RuntimeException($result['error'] ?? 'Scrape gagal.');
        }

        return [
            'url' => $url,
            'count' => $result['count'],
            'reviews' => $result['reviews'],
        ];
    }

    /**
     * Return a full google.com Maps URL, following short links
     * (the library rejects maps.app.goo.gl).
     *
     * @param  string  $url
     * @return string
     */
    public function resolveUrl($url)
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('URL harus diawali https://');
        }

        if (in_array($host, self::SHORT_HOSTS, true)) {
            $url = $this->followRedirects($url);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        }

        if ($host !== 'google.com' && substr($host, -11) !== '.google.com') {
            throw new RuntimeException('URL harus berasal dari google.com / maps.app.goo.gl');
        }

        return $url;
    }

    protected function followRedirects($url)
    {
        try {
            $response = (new Client(['timeout' => 20]))->get($url, [
                'allow_redirects' => ['max' => 8, 'track_redirects' => true],
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'],
            ]);
        } catch (\Exception $e) {
            throw new RuntimeException('Gagal membuka short link: ' . $e->getMessage());
        }

        $history = $response->getHeader('X-Guzzle-Redirect-History');
        $final = $history ? end($history) : $url;

        // Google may bounce through a consent page that carries the target in "continue".
        if (strtolower((string) parse_url($final, PHP_URL_HOST)) === 'consent.google.com') {
            parse_str((string) parse_url($final, PHP_URL_QUERY), $query);
            $final = $query['continue'] ?? $final;
        }

        return $final;
    }
}
