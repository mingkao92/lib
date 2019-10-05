# php_lib

my test php lib<br /><br />


#### Simple Http Request
Usage:
```php
include 'Http/Curl.php';


$http = new Curl();

$res = $http->debug(true)
    ->query(['wd' => 'hello world'])
    ->get('http://www.baidu.com/s');

echo $res;
```
