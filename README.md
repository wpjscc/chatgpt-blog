# 基于reactphp的chagpt-blog

使用chatgpt写博客，持久化返回的内容

> 注意第一个prompt会作为文章的标题

## 特性

* SSE--实时返回结果（类似于官网）
* 支持markwon高亮
* 支持复制markwon代码
* 支持生成的html代码预览(tailwindcss)
* 可自定义token（会优先使用url上带着的token）
* 支持代理
* 支持保存数据库
```
CREATE TABLE `blog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` text,
  `slug` char(11) DEFAULT NULL,
  `is_self` tinyint(2) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## install


```
composer create-project wpjscc/chatgpt-blog chatgpt-blog dev-master
```

## run 

```
cd chatgpt-blog

php app.php --prot=8080 --token=xxx --database=user:pass@localhost/bookstore
```

## visit

http://127.0.0.1:8080



## docker

```
docker run -p 8080:8080 --rm -it wpjscc/chatgpt-blog php app.php --prot=8080 --token=xxx
```

```
docker build -t wpjscc/chatgpt-blog . -f Dockerfile
docker push wpjscc/chatgpt-blogy
```

## proxy

```
php app.php --prot=8080 --token=xxx --proxy=127.0.0.1:7890
```

## custome token

http://127.0.0.1:8080?token=xxxxxx



## example

<img width="1755" alt="image" src="https://user-images.githubusercontent.com/76907477/223346470-e49cdf41-0dbd-4ab2-b38c-2c2eec007d21.png">
<img width="1499" alt="image" src="https://user-images.githubusercontent.com/76907477/223346528-41663c8c-b660-4cc0-858a-806fe9d0210e.png">


## 其他

如果不需要保存数据库，请移步[https://github.com/wpjscc/chatgpt](https://github.com/wpjscc/chatgpt)

