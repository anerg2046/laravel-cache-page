<?php

namespace anerg\Laravel\Http\Middleware;

use Cache;
use Carbon\Carbon;
use Closure;

class CacheResponse
{
    const CACHE_TAG = 'pagecache';
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;
    /**
     * @var \Closure
     */
    protected $next;
    /**
     * 缓存分钟
     *
     * @var int|null
     */
    protected $minutes;
    /**
     * 缓存数据
     *
     * @var array
     */
    protected $responseCache;
    /**
     * 缓存命中状态，1为命中，0为未命中
     *
     * @var int
     */
    protected $cacheHit = 1;
    /**
     * 缓存Key
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Handle an incoming request
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param int|null                 $minutes
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $minutes = NULL)
    {
        //非GET请求不做页面缓存
        if (config('pagecache.skip') || (config('pagecache.allowSkip') && $request->query('skipcache') == 1) || !$request->isMethod('get')) {
            return $next($request);
        }

        if (config('pagecache.allowFlush') && $request->query('flushcache') == 1) {
            if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
                Cache::tags(self::CACHE_TAG)->flush();
            }
        }

        $this->prepare($request, $next, $minutes);

        $this->responseCache();

        $response = response($this->responseCache['content'])->withHeaders($this->responseCache['headers']);

        return $this->addHeaders($response);
    }

    /**
     * 预备
     *
     * @return mixed
     */
    protected function prepare($request, Closure $next, $minutes = NULL)
    {
        $this->request = $request;
        $this->next    = $next;
        // 初始化值
        $this->cacheKey = $this->resolveKey();
        $this->minutes  = $this->resolveMinutes($minutes);
    }

    /**
     * 生成或读取Response-Cache
     *
     * @return array
     */
    protected function responseCache()
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            if (config('pagecache.allowClear') && $this->request->query('clearcache') == 1) {
                Cache::tags(self::CACHE_TAG)->forget($this->cacheKey);
            }
            $this->responseCache = Cache::tags(self::CACHE_TAG)->remember(
                $this->cacheKey,
                $this->minutes * 60,
                function () {
                    $this->cacheMissed();
                    $response = ($this->next)($this->request);
                    return $this->resolveResponseCache($response) + [
                        'cacheExpireAt' => Carbon::now()->addMinutes($this->minutes)->format('Y-m-d H:i:s T'),
                    ];
                }
            );
        } else {
            if (config('pagecache.allowClear') && $this->request->query('clearcache') == 1) {
                Cache::forget($this->cacheKey);
            }
            $this->responseCache = Cache::remember(
                $this->cacheKey,
                $this->minutes * 60,
                function () {
                    $this->cacheMissed();
                    $response = ($this->next)($this->request);
                    return $this->resolveResponseCache($response) + [
                        'cacheExpireAt' => Carbon::now()->addMinutes($this->minutes)->format('Y-m-d H:i:s T'),
                    ];
                }
            );
        }
        return $this->responseCache;
    }

    /**
     * 确定需要缓存Response的数据
     *
     * @param \Illuminate\Http\Response $response
     *
     * @return array
     */
    protected function resolveResponseCache($response)
    {
        return [
            'headers' => $response->headers->allPreserveCaseWithoutCookies(),
            'content' => $response->getContent()
        ];
    }

    /**
     * 追加Headers
     *
     * @param mixed
     */
    protected function addHeaders($response)
    {
        $response->headers->add(
            $this->getHeaders()
        );
        return $response;
    }

    /**
     * 返回Headers
     *
     * @return array
     */
    protected function getHeaders()
    {
        $headers = [
            'X-Cache'         => $this->cacheHit ? 'Hit from cache' : 'Missed',
            'X-Cache-Key'     => $this->cacheKey,
            'X-Cache-Expires' => $this->responseCache['cacheExpireAt'],
        ];
        return $headers;
    }

    /**
     * 根据请求获取指定的Key
     *
     * @return string
     */
    protected function resolveKey()
    {
        $queryKeys = ['skipcache', 'flushcache', 'clearcache'];
        $querys    = $this->request->query();
        foreach ($queryKeys as $querykey) {
            if (array_key_exists($querykey, $querys)) {
                unset($querys[$querykey]);
            }
        }
        //有时候请求地址一致，但可能是ajax，也可能是页面
        return md5($this->request->url() . json_encode($querys) . $this->request->server('HTTP_X_REQUESTED_WITH'));
    }

    /**
     * 获取缓存的分钟
     *
     * @param int|null $minutes
     *
     * @return int
     */
    protected function resolveMinutes($minutes = NULL)
    {
        return is_null($minutes)
            ? $this->getDefaultMinutes()
            : max($this->getDefaultMinutes(), intval($minutes));
    }

    /**
     * 返回默认的缓存时间（分钟）
     *
     * @return int
     */
    protected function getDefaultMinutes()
    {
        return 10;
    }

    /**
     * 缓存未命中
     *
     * @return mixed
     */
    protected function cacheMissed()
    {
        $this->cacheHit = 0;
    }
}
