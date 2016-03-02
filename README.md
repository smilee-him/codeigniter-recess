#Codeigniter-recess
코드이그나이터 드라이버 기능을 사용했다.  
최대한 가볍고 코드이그나이터에서 제공하는 것을 많이 담으려고 노력했다.  
특징으로는 Hook기능을 제공한다.

##1. Requirement
1. PHP 5.4+
2. Codeigniter 3.x

##2. Installation
인스톨과정은 그렇게 어렵지않다.  
코드이그나이터에서 제공하는 드라이버 인스톨방법을 참고해보자.  

* <http://www.codeigniter.com/user_guide/general/drivers.html>
* <http://www.codeigniter.com/user_guide/general/creating_drivers.html>

``` bash
$cd application/libraries
$mkdir Recess && $_
$git clone ...
```


##3. How?

코드이그나이터에서 제공되는 _remap을 알고있는가?  
링크를 따라가서 학습 후 사용해 보자.  

###3.1 Codeigniter [_remap(string $method [, mixed $argument = NULL])](http://www.codeigniter.com/user_guide/general/controllers.html?highlight=remap#remapping-method-calls)

```php
<?php
function _remap( $method, $arguments = NULL )
{
	// any..
	$this->recess->remap( $method, $arguments );
}
```

###3.2 Codeigniter [hooks](http://www.codeigniter.com/user_guide/general/hooks.html)
또한 코드이그나이터에서 제공되는 hook을 지원한다.

* recess_authorized
* recess_overried\_display
* recess_destruct


###3.3 Methods
* recess->remap(string $method [, mixed $argument = NULL])
* recess->response(mixed $output [, int $http\_status\_code = 200, bool $continue = FALSE])
* recess->get_instance()
* recess->header(string $index [, bool $xss_clean = NULL])
* recess->input(string $index [, bool $xss_clean = NULL])
* recess->input_method()
* recess->array_search(array &$array, string $index = NULL [, $xss_clean = NULL])
* recess->assign->get()
* recess->assign->put()

##4. API Documentation

###4.1 recess->remap(string $method [, mixed $argument = NULL])

아래 예제에서 보다시피 **반드시** _remap() 함수부분에서 선언해야 정상적으로 작동한다.  
이 부분은 주의하길 바란다.

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Any_Classname extends CI_Controller
{
	function __construct()
	{
		$this->load->driver('recess');
	}

	public function _remap( $method, $arguments = NULL )
	{
		// any..
		$this->recess->remap( $method, $arguments );
	}

	// @method	/GET
	// @uri		/Any_Classname/...
	public function GET_index([mixed $segments...]) {
		$this->recess->response('Hello! World');
	}

	// @method	/GET
	// @uri		/Any_Classname/test
	public function GET_test() {
		$this->recess->response('Success /example/test');
	}
}

```

###4.2 recess->response(mixed $output [, int $http\_status\_code = 200, bool $continue = FALSE])

\- Possible formats  **(JSON, JSONP, XML)**

```javascript
{
  "request_body": {
    "method": "GET|POST|PUT|PATCH|DELETE|",
    "uri": "/...",
    "segments": [...],
    "params": [...],
    "headers": {...}
  },
  "response_body": [...],
  "duration": 0.00...
}
```

...
