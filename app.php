<?php

require __DIR__.'/vendor/autoload.php';

use React\Stream\ThroughStream;
use React\EventLoop\Loop;
use React\Http\Message\Response;

require './blog.php';

$blog = new Blog(getParam('--database'));

$http = new React\Http\HttpServer(
    new React\Http\Middleware\LimitConcurrentRequestsMiddleware(1), // 100 concurrent buffering handlers
    new React\Http\Middleware\RequestBodyBufferMiddleware(0.5 * 1024 * 1024), // 2 MiB per request
function (Psr\Http\Message\ServerRequestInterface $request) use ($blog) {
    $connector = null;

    if (getParam('--proxy')) {
        $proxy = new \Clue\React\HttpProxy\ProxyConnector(getParam('--proxy'));
        $connector = new React\Socket\Connector(array(
            'tcp' => $proxy,
            'dns' => false
        ));
    }

    $client = (new \React\Http\Browser($connector))->withTimeout(getParam('--timeout', 10.0));
    $params = $request->getQueryParams();
    $query = $params['query'] ?? '';
    $token = $params['token'] ?? (getParam('--token') ?: '');
    $slug = $params['slug'] ?? '';
    $path = $request->getUri()->getPath();
    var_dump($path);

    if ($path == '/') {
        // 列表
        return $blog->getLists()->then(function($command){
            // print_r($command->resultFields);
            // print_r($command->resultRows);
            $str = '';
            $contents = file_get_contents(__DIR__.'/index.html');
            foreach($command->resultRows as $resultRow){
                $content = $resultRow['content'];
                $createdAt = $resultRow['created_at'];
                $description = $resultRow['description'];
                $slug = $resultRow['slug'];
                $str .= <<<EOF
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-2"><a href="/chat-blog?slug=$slug" class="text-blue-500 hover:text-blue-600">$content</a></h2>
                    <p class="text-gray-500 text-sm mb-2">By ChatGPT | $createdAt</p>
                    <p class="text-sm overflow-hidden overflow-ellipsis whitespace-no-wrap">$description</p>
                </div>
            EOF;
            }
            return Response::html(str_replace('{{template}}', $str, $contents));;
        }, function (Exception $error) {
            echo 'Error: ' . $error->getMessage() . PHP_EOL;
        });
    }

    if ($path == '/chat-blog') {
        $slug = $params['slug'] ?? '';

        if (!$slug) {
            $params['slug'] = str_random(10);
            var_dump(http_build_query($params));
            return new Response(301, [
                'Location' => '/chat-blog?'.http_build_query($params),
                'Cache-Control' => 'no-cache'
            ]);
        }


        // return Response::html(str_replace('{{template}}', '', file_get_contents(__DIR__.'/chat.html'))));

        return $blog->show($slug)->then(function($command){
            $str = '';
            foreach($command->resultRows as &$resultRow){
                $resultRow['is_self'] = $resultRow['is_self'] ? true : false;
                $content = $resultRow['content'];
                $createdAt = $resultRow['created_at'];
                $description = $resultRow['description'];
                if ($resultRow['is_self']) {
                    $str .= <<<EOF
                    <div class="w-full">
                        <div class="flex w-full mt-2 space-x-3 ml-auto justify-end ">
                            <div>
                                <div class="bg-blue-600 text-white p-3 rounded-l-lg rounded-br-lg ">
                                    <p class="text-sm whitespace-pre-line">$content</p>
                                </div>
                                <span class="text-xs text-gray-500 leading-none">$createdAt</span>
                            </div>
                        </div>
                    </div>
                EOF;
                } else {
                    $originContent = $content;
                    $content = (new Parsedown())->text($content);
                    $str .= <<<EOF
                    <div class="w-full">
                        <div class="flex w-full mt-2 space-x-3 overflow-auto">
                            <div class="">
                                <div class="p-3 rounded-r-lg rounded-bl-lg relative" x-data="{}">
                                    <div class="hidden" x-ref="ui">$originContent</div>
                                    <div class="prose md:max-w-4xl">$content</div>
                                    <div class="flex justify-center items-center mx-auto py-6 absolute -right-20 -top-6" @click="\$store.edit.edit(\$refs.ui.innerHTML)">
                                        <button class="bg-blue-200 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-full flex items-center">
                                            <i class="material-icons">code</i>
                                        </button>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 leading-none">$createdAt</span>
                            </div>
                        </div>
                    </div>
                EOF;
                }
            }
            $contents = str_replace('{{template}}', $str, file_get_contents(__DIR__.'/chat.html'));
            $str = '';
            return Response::html($contents);
            
        },function (Exception $error) {
            echo 'Error: ' . $error->getMessage() . PHP_EOL;
        });
    }

    if ($path == '/health') {
        return Response::json([
            'is_healthy' => true,
        ]);
    }

    if (in_array($path, ['/assets/tailwindcss.js', '/assets/alpinejs.js', '/assets/markdown-it.min.js' ,'/assets/prism.js', '/assets/prism-treeview.js'])) {
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => 'application/javascript',
                'Cache-Control' => 'max-age=3600'
            ),
            file_get_contents(__DIR__.$path)
        );
    }
    
    if (in_array($path, ['/assets/prism.css', '/assets/prism-light.css'])) {
        var_dump('exist:css:'. $path);
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => 'text/css',
                'Cache-Control' => 'max-age=3600'
            ),
            file_get_contents(__DIR__.$path)
        );
    }

    //详情
    if ($path == '/api/blogs') {
        if (!$slug) {
            return React\Http\Message\Response::json([
                'code' => 0,
                'msg' => '',
                'data' => []
            ]);
        } else {
            // 详情
            return $blog->show($slug)->then(function($command){
                foreach($command->resultRows as &$resultRow){
                    $resultRow['is_self'] = $resultRow['is_self'] ? true : false;
                }
                return React\Http\Message\Response::json([
                    'code' => 0,
                    'msg' => '',
                    'data' => $command->resultRows
                ]);
            },function (Exception $error) {
                echo 'Error: ' . $error->getMessage() . PHP_EOL;
            });
        }

    }

    $stream = new ThroughStream();


    if ($path != '/chatgpt' || !$query || !$token) {
        Loop::get()->addTimer(1, function () use ($stream) {
            endStream($stream, 'not token or 404');
        });
    }

    var_dump($query);

    if ($token && $query && $slug) {
        $blog->create($slug, $query, 1);
        $client->withRejectErrorResponse(false)->requestStreaming(
            'POST',
            'https://api.openai.com/v1/chat/completions',
            array(
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ), json_encode([
            'model' => 'gpt-3.5-turbo-0301',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $query
                ]
            ],
            'stream' => true
        ]))->then(function (Psr\Http\Message\ResponseInterface $response) use ($stream, $blog, $slug) {
            echo "response";
            echo json_encode($response->getHeaders())."\n";
            $body = $response->getBody();
            // echo strval($body);
            assert($body instanceof \Psr\Http\Message\StreamInterface);
            assert($body instanceof \React\Stream\ReadableStreamInterface);
            
            if (strpos($response->getHeaderLine('Content-Type'), 'application/json') !== false) {
                
                $body->on('data', function ($chunk) use ($stream, $blog, $slug) {
                    echo $chunk;
                    $blog->create($slug, addslashes($chunk), 0);
                    $stream->write(getData(addslashes($chunk)));
                });
            
                $body->on('error', function (Exception $e) {
                    echo 'Error: ' . $e->getMessage() . PHP_EOL;
                });
            
                $body->on('close', function () use ($stream) {
                    echo '[DONE]' . PHP_EOL;
                    endStream($stream, '[/?token=xxxxx](?token=xxxxx)');
                });
    
            } else {
                $content = '';
                $body->on('data', function ($chunk) use ($stream, &$content) {
                    $pattern = '/\{.*(?=\})\}/'; // 匹配以 { 开头、以 } 结尾的字符串
                    preg_match($pattern, $chunk, $matches);
                    $jsonData = $matches[0] ?? '{}';
                    var_dump($jsonData, $matches);
                    $content .= (json_decode($jsonData, true)['choices'][0]['delta']['content'] ?? '');
                    echo $chunk;
                    $stream->write($chunk);
                });
            
                $body->on('error', function (Exception $e) use(&$content) {
                    echo 'Error: ' . $e->getMessage() . PHP_EOL;
                    $content = '';
                });
            
                $body->on('close', function () use ($stream, $slug, $blog, &$content) {
                    echo '[DONE]' . PHP_EOL;
                    $blog->create($slug, $content, 0);
                    $stream->end();
                    $content = '';
                });
            }
    
           
        
        }, function (Exception $e) use ($stream) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            endStream($stream, $e->getMessage());
        });
    }


    
    // stop timer if stream is closed (such as when connection is closed)
    $stream->on('close', function () {
       
    });

    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        array(
            'Content-Type' => 'text/event-stream',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache'
        ),
        $stream
    );
});

function endStream($stream, $msg){
    $stream->write(getData($msg));
    $stream->write('data: [DONE]');
    $stream->end();
}

function getData($msg){
    return 'data: '.json_encode(['choices' => [
        [
            'delta' => [
                'content' => $msg
            ]
        ]
    ]])."\n\n";  
}

function str_random($length = 10)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; // 包含的字符集
    $string = '';

    for ($i = 0; $i < $length; $i++) {
        $string .= $chars[rand(0, strlen($chars) - 1)];
    }

    return $string;
}

function getParam($key, $default = null){
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key) !==false){
            return explode('=', $arg)[1];
        }
    }
    return $default;
}

$socket = new React\Socket\SocketServer('0.0.0.0:'.(getParam('--port') ?: '8000'));
$http->listen($socket);


