# Slime Event

### 说明: 事件类
### 特点:
1. 基于 Slime\Container\ContainerObject
2. 通过工厂对象 ConfigureFactory 创建
3. 通过实现 ConfigureInterface 可自由扩展各种格式知己
4. 内置支持 JSON格式 和 PHP原生数组格式

### 使用方式:
```
use Slime\Container\Container as C;
use Slime\Event\Event;

// 创建包含 Event 的容器
$C = C::createFromConfig(
    [
        'Event' => function(C $C) {
           return new Event();
        }
    ]
);

// 监听
$Ev = $C->get('Event');
$Ev->listen(
    'system::start',     //监听点
    function($a, $b){},  //回调函数
    0,                   //优先级(可选, 默认0)  
                         //同一监听点的多个回调按优先级大到小排序, 事件发生时依次执行.
    'first_start'        //标记名(可选, 默认null)
                         //设置后:
                         //1. 在同一监听点只有一份, 否则抛 \RuntimeException 异常)
                         //2. 可以使用 forget, 清除此监听回调
)

// 事件发生
$var1   = 'example var 1';
$VarObj = new \ArrayObject(); //对象方式传入, 在回调函数中可改变此对象的值

$Ev->fire('system::start', [$var1, $VarObj]); // 触发事件