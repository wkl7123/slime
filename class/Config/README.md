# Slime Config

### 说明: 配置类
### 特点:
1. 基于 Slime\Container\ContainerObject
2. 通过工厂对象 ConfigureFactory 创建
3. 内置支持 JSON格式 和 PHP原生数组格式
4. 通过实现 ConfigureInterface 可自由扩展各种格式支持

### 使用方式:
```
use Slime\Container\Container as C;
use Slime\Config\ConfigureFactory;

// 创建包含 ConfFactory 的容器
$C = C::createFromConfig(
    [
        'ConfFactory' => function(C $C) {
            return new ConfigureFactory();
        }
    ]
);

// 将 SysConf 注入容器
$C['SysConf'] = function(C $C) {
    return $C->get('ConfFactory')->create(
        'Slime\Config\JsonConfAdaptor', // 可换成其他实现了ConfigureInterface 接口的类
        [
            '/www/data/conf/',
            'main_dev.json', 
            'main.json'
        ]
    );
};

// 取值
$nsValue = $C->get('SysConf')['node_0']['node_1'][0]['field'];
```