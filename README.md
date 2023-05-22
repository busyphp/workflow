BusyPHP状态机
===============

## 说明

开发人员只需要简单配置即可实现复杂的状态流程转换

## 安装
```
composer require busyphp/workflow
```

## 通过注解配置状态机

```php
namespace core\model\test;

#[\BusyPHP\workflow\annotation\StateMachine(
    // 状态字段名称
    field: 'status',
    
    // 模型支持的状态集合
    places: ['待审核', '待发布', '已发布', '已下架', '已取消'],
    
    // 状态转换过程配置
    transitions: [
        // 审核操作
        'examine' => [
            'from' => '待审核',
            'to' => '待发布'
        ],
        
        // 发布操作
        'publish' => [
            'from' => '待发布',
            'to' => '已发布'
        ],
        
        // 下架操作
        'revoke' => [
            'from' => '已发布',
            'to' => '已下架'
        ],
        
        // 取消操作
        'cancel' => [
            'form' => ['待审核', '待发布', '已发布', '已下架'],
            'to' => '已取消'
        ]
    ],
    
    // 初始状态
    initial: '待审核'
)]
class Test extends \BusyPHP\Model {
    // 绑定模型字段类即可自动为模型字段类添加虚拟属性
    // 指示是否可以进行对应的操作：
    // $field->canExamine bool
    // $field->canPublish bool
    // $field->canRevoke bool
    // $field->canCancel boll
    protected string $fieldClass = TestField::class;
}

class TestField extends \BusyPHP\model\Field {
    
}
```

## 通过命令行 `bp:ide-model` 生成状态机的相关方法
```shell
php think bp:ide-model core.model.test.Test
```