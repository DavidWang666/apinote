## Api Note

一个 [Hyperf](https://github.com/hyperf/hyperf) 框架的 Api 参数校验及 swagger 文档生成组件

1. 根据注解自动进行Api参数的校验, 业务代码更纯粹.
2. 根据注解自动生成Swagger文档, 让接口文档维护更省心.

## 安装

```
composer require DavidWang/apinote
```

## 使用

#### 1. 发布配置文件

```bash
php bin/hyperf.php vendor:publish DavidWang/apinote

# hyperf/validation 的依赖发布

php bin/hyperf.php vendor:publish hyperf/translation

php bin/hyperf.php vendor:publish hyperf/validation

# 视图发布
php bin/hyperf.php vendor:publish hyperf/view-engine

```

### 2. 修改配置文件

根据需求修改 `config/autoload/apinote.php`

### 3. 启用 Api参数校验中间件

```php
// config/autoload/middlewares.php

DavidWang\ApiNote\Middleware\ApiValidationMiddleware::class;
```

### 4. 校验规则的定义

规则列表参见 [hyperf/validation 文档](https://hyperf.wiki/#/zh-cn/validation?id=%e9%aa%8c%e8%af%81%e8%a7%84%e5%88%99)

更详细的规则支持列表可以参考 [laravel/validation 文档](https://learnku.com/docs/laravel/6.x/validation/5144#c58a91)

扩展在原生的基础上进行了封装, 支持方便的进行 `自定义校验` 和 `控制器回调校验`

## 实现思路

api参数的自动校验: 通过中间件拦截 http 请求, 根据注解中的参数定义, 通过 `valiation` 自动验证和过滤, 如果验证失败, 则拦截请求. 其中`valiation` 包含 规则校验, 参数过滤, 自定义校验 三部分.

swagger文档生成: 在`php bin/hyperf.php start` 启动 `http-server` 时, 通过监听 `BootAppConfListener` 事件, 扫码控制器注解, 通过注解中的 访问类型, 参数格式,
返回类型 等, 自动组装 `swagger.json` 结构, 最后输出到 `config/autoload/apinote.php` 定义的文件路径中

## 支持的注解

#### Api类型

`GetApi`, `PostApi`, `PutApi`, `DeleteApi`

### 参数类型

`Header`, `Quyer`, `Body`, `FormData`, `Path`

### 其他

`ApiController`, `ApiResponse`, `ApiVersion`, `ApiServer`, `ApiDefinitions`, `ApiDefinition`

```php
/**
 * @ApiVersion(version="v1")
 * @ApiServer(name="http")
 */
class UserController {} 
```

`ApiServer` 当你在 `config/autoload.php/server.php servers` 中配置了多个 `http` 服务时, 如果想不同服务生成不同的`swagger.json` 可以在控制器中增加此注解.

`ApiVersion` 当你的统一个接口存在不同版本时, 可以使用此注解, 路由注册时会为每个木有增加版本号, 如上方代码注册的实际路由为 `/v1/user/***`

`ApiDefinition` 定义一个 `Definition`，用于Response的复用。 *swagger* 的difinition是以引用的方式来嵌套的，如果需要嵌套另外一个(值为object类型就需要嵌套了)
，可以指定具体 `properties` 中的 `$ref` 属性

`ApiDefinitions` 定义一个组`Definition`

`ApiResponse` 响应体的`schema`支持为key设置简介. `$ref` 属性可以引用 `ApiDefinition` 定义好的结构(该属性优先级最高)

```php
@ApiResponse(code="0", description="删除成功", schema={"id|这里是ID":1})
@ApiResponse(code="0", description="删除成功", schema={"$ref": "ExampleResponse"})
```

具体使用方式参见下方样例

## 样例

```php
<?php
declare(strict_types=1);
namespace App\Controller;

use DavidWang\ApiNote\Annotation\ApiController;
use DavidWang\ApiNote\Annotation\ApiResponse;
use DavidWang\ApiNote\Annotation\ApiVersion;
use DavidWang\ApiNote\Annotation\Body;
use DavidWang\ApiNote\Annotation\DeleteApi;
use DavidWang\ApiNote\Annotation\FormData;
use DavidWang\ApiNote\Annotation\GetApi;
use DavidWang\ApiNote\Annotation\Header;
use DavidWang\ApiNote\Annotation\PostApi;
use DavidWang\ApiNote\Annotation\Query;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;

/**
 * @ApiVersion(version="v1")
 * @ApiController(tag="demo管理", description="demo的新增/修改/删除接口")
 * @ApiDefinitions({
 *  @ApiDefinition(name="DemoOkResponse", properties={
 *     "code|响应码": 200,
 *     "msg|响应信息": "ok",
 *     "data|响应数据": {"$ref": "DemoInfoData"}
 *  }),
 *  @ApiDefinition(name="DemoInfoData", properties={
 *     "userInfo|用户数据": {"$ref": "DemoInfoDetail"}
 *  }),
 *  @ApiDefinition(name="DemoInfoDetail", properties={
 *     "id|用户ID": 1,
 *     "mobile|用户手机号": { "default": "13545321231", "type": "string" },
 *     "nickname|用户昵称": "nickname",
 *     "avatar": { "default": "avatar", "type": "string", "description": "用户头像" },
 *  })
 * })
 */
class DemoController extends AuthController
{

    /**
     * @PostApi(path="/demo", description="添加一个用户")
     * @Header(key="token|接口访问凭证", rule="required")
     * @FormData(key="a.name|名称", rule="required|max:10|cb_checkName")
     * @FormData(key="a.sex|年龄", rule="integer|in:0,1")
     * @FormData(key="aa|aa", rule="required|array")
     * @FormData(key="file|文件", rule="file")
     * @ApiResponse(code="-1", description="参数错误", template="page")
     * @ApiResponse(code="0", description="请求成功", schema={"id":"1"})
     *
     */
    public function add()
    {
        return [
            'code'   => 0,
            'id'     => 1,
            'params' => $this->request->post(),
        ];
    }

    // 自定义的校验方法 rule 中 cb_*** 方式调用
    public function checkName($attribute, $value)
    {
        if ($value === 'a') {
            return "拒绝添加 " . $value;
        }

        return true;
    }

    /**
     * 请注意 body 类型 rules 为数组类型
     * @DeleteApi(path="/demo", description="删除用户")
     * @Body(rules={
     *     "id|用户id":"required|integer|max:10",
     *     "deepAssoc|深层关联":{
     *        "name_1|名称": "required|integer|max:20"
     *     },
     *     "deepUassoc|深层索引":{{
     *         "name_2|名称": "required|integer|max:20"
     *     }},
     *     "a.b.c.*.e|aa":"required|integer|max:10",
     * })
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", description="删除成功", schema={"id":1})
     */
    public function delete()
    {
        $body = $this->request->getBody()->getContents();
        return [
            'code'  => 0,
            'query' => $this->request->getQueryParams(),
            'body'  => json_decode($body, true),
        ];
    }

    /**
     * @GetApi(path="/demo", description="获取用户详情")
     * @Query(key="id", rule="required|integer|max:0")
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", schema={"id":1,"name":"张三","age":1}, template="success")
     */
    public function get()
    {
        return [
            'code' => 0,
            'id'   => 1,
            'name' => '张三',
            'age'  => 1,
        ];
    }

    /**
     * schema中可以指定$ref属性引用定义好的definition
     * @GetApi(path="/demo/info", description="获取用户详情")
     * @Query(key="id", rule="required|integer|max:0")
     * @ApiResponse(code="-1", description="参数错误")
     * @ApiResponse(code="0", schema={"$ref": "DemoOkResponse"})
     */
    public function info()
    {
        return [
            'code' => 0,
            'id'   => 1,
            'name' => '张三',
            'age'  => 1,
        ];
    }

    /**
     * @GetApi(path="/demos", summary="用户列表")
     * @ApiResponse(code="200", description="ok", schema={{
     *     "a|aa": {{
     *          "a|aaa":"b","c|ccc":5.2
     *      }},
     *     "b|ids": {1,2,3},
     *     "c|strings": {"a","b","c"},
     *     "d|dd": {"a":"b","c":"d"},
     *     "e|ee": "f"
     * }})
     */
    public function list()
    {
        return [
            [
                "a" => [
                    ["a" => "b", "c" => "d"],
                ],
                "b" => [1, 2, 3],
                "c" => ["a", "b", "c"],
                "d" => [
                    "a" => "b",
                    "c" => "d",
                ],
                "e" => "f",
            ],
        ];
    }

}
```

## Swagger UI 访问

```
apinote 配置文件配置路由

localhost:9501/swagger
```

## 登录验证 添加AOP 切面

```

/**
 * @Aspect
 * Class AuthAspect
 */
class AuthAspect extends AbstractAspect
{
    // 要切入的注解，具体切入的还是使用了这些注解的类，仅可切入类注解和类方法注解
    public $annotations = [
        ApiController::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if (config('apinote.auth.enable')) {
            /** @var ApiController $class */
            $class = $proceedingJoinPoint->getAnnotationMetadata()->class[ApiController::class];
            $auth = $class->auth;
            $methods = $proceedingJoinPoint->getAnnotationMetadata()->method;
            foreach ($methods as $method) {
                if ($method instanceof Mapping) {
                    $auth = is_bool($method->auth) ? $method->auth : $auth;
                }
            }
            if ($auth) {
                #TODO 登录判断
                echo '需要登录';
            } else {
                echo '无需登录';
            }
        }
        return $proceedingJoinPoint->process();
        // 在调用后进行某些处理
        // TODO: Implement process() method.
    }
}

```
