<?php
declare(strict_types = 1);

namespace BusyPHP\workflow\annotation;

use Attribute;
use BusyPHP\model\Entity;
use SplObjectStorage;
use Symfony\Component\Workflow\Transition;

/**
 * 状态机注解
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/19 13:34 StateMachine.php $
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class StateMachine
{
    /**
     * @var string[]
     */
    private array $places;
    
    /**
     * @var Transition[]
     */
    private array $transitions;
    
    /**
     * @var string|string[]|null
     */
    private string|array|null $initial;
    
    /**
     * @var string
     */
    private string $field;
    
    /**
     * @var SplObjectStorage
     */
    private SplObjectStorage $transitionsMetadata;
    
    /**
     * @var array
     */
    private array $placesMetadata;
    
    /**
     * @var array
     */
    private array $metadata;
    
    
    /**
     * 构造函数
     * @param string|callable                                                                                                   $field 状态字段
     * @param string[]|int[]                                                                                                    $places 状态集合
     * @param array<string, array{from: string,int,array, to: string,int,array, error: string, label: string, metadata: array}> $transitions 状态转换过程配置
     * @param string|int|array|null                                                                                             $initial 初始状态
     * @param array                                                                                                             $metadata
     */
    public function __construct(string|callable $field, array $places, array $transitions, string|int|array $initial = null, array $metadata = [])
    {
        if ($obj = Entity::tryCallable($field)) {
            $field = (string) $obj;
        }
        
        $flatTransitions     = [];
        $transitionsMetadata = new SplObjectStorage();
        foreach ($transitions as $key => $item) {
            $froms = $item['from'];
            $tos   = $item['to'];
            unset($item['from'], $item['to']);
            
            $meta = $item;
            $name = $this->parse($key);
            foreach ($this->parse($froms, true) as $from) {
                foreach ($this->parse($tos, true) as $to) {
                    $transition                       = new Transition($name, $from, $to);
                    $flatTransitions[]                = $transition;
                    $transitionsMetadata[$transition] = $meta;
                }
            }
        }
        
        $placesMetadata = [];
        if (isset($places[0])) {
            $places = $this->parse($places);
        } else {
            foreach ($places as $key => $place) {
                if (is_array($place)) {
                    $placesMetadata[$key] = $place;
                }
            }
            $places = array_keys($places);
        }
        
        if (null !== $initial) {
            $initial = $this->parse($initial);
        }
        
        $this->field               = $field;
        $this->places              = $this->parse($places);
        $this->transitions         = $flatTransitions;
        $this->initial             = $initial;
        $this->transitionsMetadata = $transitionsMetadata;
        $this->placesMetadata      = $placesMetadata;
        $this->metadata            = $metadata;
    }
    
    
    /**
     * @return string
     */
    public function getField() : string
    {
        return $this->field;
    }
    
    
    /**
     * 解析值
     * @param array|int|string $value
     * @param bool             $array
     * @return array|string
     */
    protected function parse(array|int|string $value, bool $array = false) : array|string
    {
        if (is_array($value)) {
            return array_map(function($item) {
                return (string) $item;
            }, $value);
        }
        
        $value = (string) $value;
        if ($array) {
            return [$value];
        }
        
        return $value;
    }
    
    
    /**
     * @return string|string[]|null
     */
    public function getInitial() : string|array|null
    {
        return $this->initial;
    }
    
    
    /**
     * @return string[]
     */
    public function getPlaces() : array
    {
        return $this->places;
    }
    
    
    /**
     * @return Transition[]
     */
    public function getTransitions() : array
    {
        return $this->transitions;
    }
    
    
    /**
     * @return SplObjectStorage
     */
    public function getTransitionsMetadata() : SplObjectStorage
    {
        return $this->transitionsMetadata;
    }
    
    
    /**
     * @return array
     */
    public function getPlacesMetadata() : array
    {
        return $this->placesMetadata;
    }
    
    
    /**
     * @return array
     */
    public function getMetadata() : array
    {
        return $this->metadata;
    }
}