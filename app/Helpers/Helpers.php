<?php 


use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Client\RequestException;

if (!function_exists('exception_info')) {
    function exception_info(\Throwable $th, array $where_thrown = [], array $include = [], array $exclude = ['trace', 'file', 'line', 'trace_as_string', 'previous'])
    {
        $info = [ 
            'where_thrown' => $where_thrown,
            'exception_type' => class_basename($th),
            'message' => $th->getMessage(),
            'code' => $th->getCode(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'trace' => $th->getTrace(),
            'trace_as_string' => $th->getTraceAsString(),
            'previous' => $th->getPrevious(),
        ];

        Log::error(
            'Exception Info: ' . $info['message'],
            array_filter($info, fn(string $key) => !in_array($key, array_diff($exclude, $include)), ARRAY_FILTER_USE_KEY)
        );
    }
}

if (!function_exists('log_this')) {
    /**
     * Helper to log to any channel of your choosing, by default logs to the normal default file
     *
     * @param mixed $values what will be logged
     * @param array $context provides extra information
     * @param string|null $level choose between `emergency`, `alert`, `critical` , `error`, `warning` , `notice`, `info`, `debug`, this list is a heirachal order in which messages are logged
     * @param string|null $channel if you have another channel defined in config you can put in here, will always choose the default channel
     *
     * @return void
     */
    function log_this(mixed $values, array $context = [], ?string $level = null, ?string $channel = null): void
    {
        $level ??= 'info';

        empty($channel) ? Log::$level($values, $context) : Log::channel($channel)->$level($values, $context);
    }
}


if(! function_exists('capture_request_vars')) {
    function capture_request_vars(Request $request, mixed ...$messages) {

        $request_vars = new class($request) {
            public mixed $request;
            public mixed $query;
            public $attributes;
            public $cookies;
            public $files;
            public $server;
            public $headers;

            public $client_ips;
            public $client_ip;
            public $session;
            public $content;
            public $languages;
            public $charsets;
            public $encodings;
            public $acceptableContentTypes;
            public $pathInfo;
            public $requestUri;
            public $url;
            public $fullUrl;
            public $baseUrl;
            public $basePath;
            public $method;
            public $format;
            public $port;
            public $scheme;
            public $http_host;
            public $scheme_and_http_host;
            public bool $is_secure;
            public $user_agent;
            public $is_ajax_or_xmlHttpRequest;
            public $user;
            public $route;

            public function __construct(public Request $current_request) {

                $this->request =  collect($current_request->request);
                $this->query =  collect($current_request->query);
                $this->attributes =  collect($current_request->attributes);
                $this->cookies =  collect($current_request->cookies);
                $this->files =  collect($current_request->files);
                $this->server =  collect($current_request->server);
                $this->headers =  collect($current_request->headers);


                $this->client_ip =  $current_request->getClientIp();
                $this->client_ips =  $current_request->getClientIps();
                $this->session = collect((fn() => $this->session)->call($current_request) );
                $this->content = $current_request->getContent();
                $this->encodings = (fn() => $this->encodings)->call($current_request);
                $this->languages =  $current_request->getLanguages();
                $this->acceptableContentTypes =  $current_request->getAcceptableContentTypes();
                $this->pathInfo =  $current_request->getPathInfo();
                $this->requestUri =  $current_request->getRequestUri();
                $this->url = $current_request->url();
                $this->fullUrl = $current_request->fullUrl();
                $this->baseUrl =  $current_request->getBaseUrl();
                $this->basePath =  (fn() => $this->basePath)->call($current_request);
                $this->method =  $current_request->getMethod();
                $this->format =  $current_request->getContentTypeFormat();
                $this->port =  $current_request->getPort();
                $this->scheme =  $current_request->getScheme();
                $this->http_host =  $current_request->getHttpHost();
                $this->scheme_and_http_host =  $current_request->getSchemeAndHttpHost();
                $this->is_secure =  $current_request->isSecure();
                $this->user_agent=  $current_request->userAgent();
                $this->is_ajax_or_xmlHttpRequest = $current_request->ajax();
                $this->user = $current_request->user();
                $this->route = $current_request->route();

            }

            // public function __toString()
            // {
            //     return json_encode($this);
            // }
        };

        log_this(collect($request_vars), ['Logged Request', ...$messages]);

        return $request_vars;
    }
}


