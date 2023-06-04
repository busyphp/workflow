<?php
declare(strict_types = 1);

namespace BusyPHP\workflow\store;

use BusyPHP\helper\StringHelper;
use BusyPHP\Model;
use BusyPHP\model\Field;
use LogicException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;

/**
 * 模型MarkingStore
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/19 14:36 ModelMarkingStore.php $
 */
class ModelMarkingStore implements MarkingStoreInterface
{
    /**
     * @var string
     */
    protected string $field;
    
    /**
     * @var array
     */
    protected array $places;
    
    /**
     * @var string|int|null
     */
    protected string|int|null $id;
    
    
    /**
     * 构造函数
     * @param string[] $places 允许的状态集合
     * @param string   $field 状态字段名
     */
    public function __construct(array $places, string $field = 'status')
    {
        $this->field  = $field ?: 'status';
        $this->places = $places;
    }
    
    
    /**
     * 设置ID
     * @param int|string $id
     */
    public function setId(int|string $id) : void
    {
        $this->id = $id;
    }
    
    
    /**
     * 清理
     * @return void
     */
    public function clear() : void
    {
        $this->id = null;
    }
    
    
    /**
     * 生成模型字段名称，不覆盖原有的字段
     * @return string
     */
    protected function privateField() : string
    {
        return '__private_workflow_field_' . $this->field;
    }
    
    
    /**
     * @inheritdoc
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function getMarking(object $subject) : Marking
    {
        if ($subject instanceof Model) {
            $subject->parsePkWhere($this->id);
            $value = $subject->field($this->field)->failException()->find();
            $value = (string) $value[$this->field];
        } elseif ($subject instanceof Field) {
            if (isset($subject[$this->field])) {
                $subject[$this->privateField()] = $subject[$this->field];
            }
            
            if (!isset($subject[$this->privateField()])) {
                return new Marking();
            }
            
            $value = (string) $subject[$this->privateField()];
        } else {
            return new Marking();
        }
        
        if (!in_array($value, $this->places, true)) {
            return new Marking();
        }
        
        return new Marking([$value => 1]);
    }
    
    
    /**
     * @inheritdoc
     * @throws DbException
     */
    public function setMarking(object $subject, Marking $marking, array $context = [])
    {
        // 模型
        // Model
        if ($subject instanceof Model) {
            if (isset($context[0]) && is_a($context[0], $subject->getFieldClass(), true)) {
                $context = $context[0];
            }
            
            $context[$this->field] = key($marking->getPlaces());
            $subject->parsePkWhere($this->id);
            $subject->update($context);
        }
        
        // 模型字段
        // Field
        elseif ($subject instanceof Field) {
            $value = key($marking->getPlaces());
            
            $subject[$this->privateField()] = $value;
        } else {
            throw new LogicException(sprintf('Class "%s" not support setMarking()', get_class($subject)));
        }
    }
}