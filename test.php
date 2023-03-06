<?php 


$jsonString = '111{"name":"Lucy","age":[18]}';
$jsonString = 'data: {"id":"chatcmpl-6r65032eDFhNWD9EQagjKldWwojvg","object":"chat.completion.chunk","created":1678112774,"model":"gpt-3.5-turbo-0301","choices":[{"delta":{"content":" today"},"index":0,"finish_reason":null}]}';
$pattern = '/(\{.*\}|\[.*\])$/s';
$result = preg_match($pattern, $jsonString, $matches);
if ($result === 1) {
    $jsonData = $matches[0];
    var_dump($jsonData, json_decode($jsonData, true));
} else {
    echo "匹配失败";
}