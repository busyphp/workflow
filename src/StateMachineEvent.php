<?php
declare(strict_types = 1);

namespace BusyPHP\workflow;

use BusyPHP\model\Entity;
use Symfony\Component\Workflow\Event\AnnounceEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Workflow;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use think\facade\Event;

/**
 * 状态机事件调度类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/19 17:28 StateMachineEvent.php $
 */
class StateMachineEvent implements EventDispatcherInterface
{
    /**
     * 获取状态后或设置状态前触发该事件，通过该事件可以阻止状态转换，
     *
     * 事件参数：
     * - {@see GuardEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.guard
     * - busy.workflow.[workflow name].guard
     * - busy.workflow.[workflow name].guard.[transition name]
     * @see GuardEvent
     */
    public const EVENT_GUARD = 'guard';
    
    /**
     * 状态改变前触发该事件，此时非常适合处理上一个状态的相关逻辑
     *
     * 事件参数：
     * - {@see LeaveEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.leave
     * - busy.workflow.[workflow name].leave
     * - busy.workflow.[workflow name].leave.[place name]
     */
    public const EVENT_LEAVE = 'leave';
    
    /**
     * 状态正要改变前触发该事件，通过该事件可以设置 {@see Workflow::apply()} 中的 $context 参数
     *
     * 事件参数：
     * - {@see TransitionEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.transition
     * - busy.workflow.[workflow name].transition
     * - busy.workflow.[workflow name].transition.[transition name]
     */
    public const EVENT_TRANSITION = 'transition';
    
    /**
     * 状态确定改变前触发该事件
     *
     * 事件参数：
     * - {@see EnterEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.enter
     * - busy.workflow.[workflow name].enter
     * - busy.workflow.[workflow name].enter.[place name]
     */
    public const EVENT_ENTER = 'enter';
    
    /**
     * 状态已经在数据库中更新完成触发该事件
     *
     * 事件参数：
     * - {@see EnteredEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.entered
     * - busy.workflow.[workflow name].entered
     * - busy.workflow.[workflow name].entered.[place name]
     */
    public const EVENT_ENTERED = 'entered';
    
    /**
     * 状态改变完成触发该事件
     *
     * 事件参数：
     * - {@see CompletedEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.completed
     * - busy.workflow.[workflow name].completed
     * - busy.workflow.[workflow name].completed.[transition name]
     */
    public const EVENT_COMPLETED = 'completed';
    
    /**
     * 状态设置完成触发该事件
     *
     * 事件参数：
     * - {@see AnnounceEvent} $event
     *
     * 触发3个事件：
     * - busy.workflow.announce
     * - busy.workflow.[workflow name].announce
     * - busy.workflow.[workflow name].announce.[transition name]
     */
    public const EVENT_ANNOUNCE = 'announce';
    
    
    /**
     * @inheritdoc
     */
    public function dispatch(object $event, string $eventName = null) : object
    {
        trace($eventName);
        Event::trigger(sprintf('busy.%s', $eventName), $event);
        
        return $event;
    }
    
    
    /**
     * 监听事件
     * @param string             $event 事件名称
     * @param callable           $callback 事件回调
     * @param bool               $first 是否优先执行
     * @param string|object|null $model 模型
     * @param Entity|string|null $field 状态字段
     * @param string|int|null    $name 转换的状态名称(transition name)或状态值(place name)
     * @return void
     */
    public static function listen(string $event, callable $callback, bool $first = false, string|object $model = null, Entity|string $field = null, string|int $name = null) : void
    {
        if (is_object($model)) {
            $model = get_class($model);
        }
        $field = Entity::parse($field);
        
        if ($field && $name) {
            $event = sprintf('busy.workflow.%s@%s.%s.%s', $model, $field, $event, $name);
        } elseif ($field) {
            $event = sprintf('busy.workflow.%s@%s.%s', $model, $field, $event);
        } else {
            $event = sprintf('busy.workflow.%s', $event);
        }
        
        Event::listen($event, $callback, $first);
    }
    
    
    /**
     * 监听状态字段事件
     * @param string        $event 事件名称
     * @param string|object $model 模型
     * @param Entity|string $field 字段
     * @param callable      $callback 事件回调
     * @param bool          $first 是否优先执行
     * @return void
     */
    public static function listenField(string $event, string|object $model, Entity|string $field, callable $callback, bool $first = false) : void
    {
        static::listen($event, $callback, $first, $model, $field);
    }
    
    
    /**
     * 监听转换的状态名称(transition name)或状态值(place name)事件
     * @param string        $event 事件名称
     * @param string|object $model 模型
     * @param Entity|string $field 状态字段
     * @param string        $name 转换的状态名称(transition name)或状态值(place name)
     * @param callable      $callback 事件回调
     * @param bool          $first 是否优先执行
     * @return void
     */
    public static function listenName(string $event, string|object $model, Entity|string $field, string $name, callable $callback, bool $first = false) : void
    {
        static::listen($event, $callback, $first, $model, $field, $name);
    }
}