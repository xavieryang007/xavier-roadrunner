<?php

namespace xavier\roadrunner;

use Psr\Http\Message\ServerRequestInterface;
use think\swoole\coroutine\Context;

class App extends \think\App
{
    public function clearInstances()
    {
        $this->instances = [];
    }

    public function run(ServerRequestInterface $req)
    {
        $header = $req->getHeaders() ?: [];
        $server = $req->getServerParams() ?: [];

        $url = $server['REQUEST_URI'] ?? '';

        $path_info = '/';
        if (preg_match('#^(http|https)\:\/\/.*?\/((.*?)\?|(.*))#',
            $url, $matches)) {
            $path_info .= isset($matches[4]) ? $matches[4] : ($matches[3] ?? '');
        }

        $query_string = '';
        if (preg_match('#^(http|https)\:\/\/.*?(\/\?|\?)(.*)#',
            $url, $matches)) {
            $query_string = $matches[3];
        }
        $server['QUERY_STRING'] = $query_string;
        // 重新实例化请求对象 处理swoole请求数据
        /** @var \think\Request $request */
        $request = $this->make('request', [], true);

        $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->getQueryParams() ?: [])
            ->withPost($req->getParsedBody() ?: [])
            ->withCookie($req->getCookieParams() ?: [])
            ->withFiles($req->getUploadedFiles() ?: [])
            ->withInput((string)$req->getBody())
            ->setBaseUrl($url)
            ->setUrl($url)
            ->setPathinfo(ltrim($path_info, '/'));

        $http = $this->make(\think\Http::class);
        return $http->run($request);
    }
}