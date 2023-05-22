<?php
declare(strict_types = 1);

namespace BusyPHP\workflow;

use BusyPHP\helper\ClassHelper;
use BusyPHP\helper\StringHelper;
use BusyPHP\ide\generator\Argument;
use BusyPHP\Model;
use BusyPHP\model\Field;
use BusyPHP\ide\model\FieldGenerator;
use BusyPHP\ide\model\ModelGenerator;
use BusyPHP\workflow\annotation\StateMachine;
use BusyPHP\workflow\exception\ErrorTransitionException;
use BusyPHP\workflow\store\ModelMarkingStore;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Dumper\DumperInterface;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

/**
 * Service
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/19 12:57 Service.php $
 * @link https://symfony.com/doc/4.2/workflow.html
 */
class Service extends \think\Service
{
    private Registry          $registry;
    
    private StateMachineEvent $event;
    
    private array             $stateMachineMap = [];
    
    
    /**
     * 分析Model的状态机注解
     * @param string $modelClassName 模型类名
     * @return array<int,array{instance: StateMachine, marking: ModelMarkingStore, definition: DefinitionBuilder}>
     */
    protected function parseStateMachine(string $modelClassName) : array
    {
        if (!isset($this->stateMachineMap[$modelClassName])) {
            $class      = ClassHelper::getReflectionClass($modelClassName);
            $attributes = $class->getAttributes(StateMachine::class);
            if (!$attributes) {
                $this->stateMachineMap[$modelClassName] = [];
            } else {
                foreach ($attributes as $attribute) {
                    /** @var StateMachine $instance */
                    $instance = $attribute->newInstance();
                    
                    // MetadataStore
                    $metadata = new InMemoryMetadataStore(
                        $instance->getMetadata(),
                        $instance->getPlacesMetadata(),
                        $instance->getTransitionsMetadata()
                    );
                    
                    // MarkingStore
                    $marking = new ModelMarkingStore($instance->getPlaces(), $instance->getField());
                    
                    // Definition
                    $builder = new DefinitionBuilder();
                    $builder->addPlaces($instance->getPlaces());
                    $builder->addTransitions($instance->getTransitions());
                    $builder->setInitialPlaces($instance->getInitial());
                    $builder->setMetadataStore($metadata);
                    $definition = $builder->build();
                    
                    $this->stateMachineMap[$modelClassName][] = [
                        'instance'   => $instance,
                        'marking'    => $marking,
                        'definition' => $definition
                    ];
                }
            }
        }
        
        return $this->stateMachineMap[$modelClassName];
    }
    
    
    public function boot()
    {
        $this->registry = $this->app->make(Registry::class);
        $this->event    = $this->app->make(StateMachineEvent::class);
        
        Model::maker(function(Model $model) {
            $className     = get_class($model);
            $stateMachines = $this->parseStateMachine($className);
            if (!$stateMachines) {
                return;
            }
            
            foreach ($stateMachines as $machine) {
                $instance     = $machine['instance'];
                $markingStore = $machine['marking'];
                $definition   = $machine['definition'];
                $statusField  = $instance->getField();
                
                // Workflow
                $workflow = new Workflow($definition, $markingStore, $this->event, $className . "@" . $statusField);
                $this->registry->addWorkflow($workflow, new InstanceOfSupportStrategy($className));
                
                // 注入 $model->getFieldWorkflow() 方法
                $field = StringHelper::studly($statusField);
                $model::macro('get' . $field . 'Workflow', function() use ($workflow) : Workflow {
                    return $workflow;
                });
                
                // 注入 $model->dumpFieldDiagram() 方法
                $model::macro('dump' . $field . 'Diagram', function(DumperInterface $dumper, Marking $marking = null, array $options = []) use ($workflow) : string {
                    return $dumper->dump($workflow->getDefinition(), $marking, $options);
                });
                
                // 注入 $model->getFieldMarking() 方法
                $model::macro('get' . $field . 'Marking', function(int|string $id, array $context = []) use ($workflow, $markingStore) : Marking {
                    try {
                        $markingStore->setId($id);
                        
                        return $workflow->getMarking($this, $context);
                    } finally {
                        $markingStore->clear();
                    }
                });
                
                // 注入 $model->getFieldEnabledTransitions() 方法
                $model::macro('get' . $field . 'EnabledTransitions', function(int|string $id) use ($workflow, $markingStore) : array {
                    try {
                        $markingStore->setId($id);
                        
                        return $workflow->getEnabledTransitions($this);
                    } finally {
                        $markingStore->clear();
                    }
                });
                
                // 注入 $model->getFieldEnabledTransition() 方法
                $model::macro('get' . $field . 'EnabledTransition', function(string $name, int|string $id) use ($workflow, $markingStore) : ?Transition {
                    try {
                        $markingStore->setId($id);
                        
                        return $workflow->getEnabledTransition($this, $name);
                    } finally {
                        $markingStore->clear();
                    }
                });
                
                // 注入 $model->applyFieldTo() 方法
                $model::macro('apply' . $field . 'To', function(string $name, int|string $id, array $context = []) use ($workflow, $markingStore) : Marking {
                    try {
                        $markingStore->setId($id);
                        
                        return $workflow->apply($this, $name, $context);
                    } catch (NotEnabledTransitionException $e) {
                        throw new ErrorTransitionException($e);
                    } finally {
                        $markingStore->clear();
                    }
                });
                
                // 注入 $model->canFieldTo() 方法
                $model::macro('can' . $field . 'To', function(string $name, int|string $id) use ($workflow, $markingStore) : bool {
                    try {
                        $markingStore->setId($id);
                        
                        return $workflow->can($this, $name);
                    } finally {
                        $markingStore->clear();
                    }
                });
                
                // 注入 TransitionName 方法
                foreach ($instance->getTransitions() as $transition) {
                    $method = StringHelper::studly($transition->getName());
                    
                    // 注入 $model->canTransitionName() 方法
                    $model::macro('can' . $method, function(int|string $id) use ($transition, $workflow, $markingStore) : bool {
                        try {
                            $markingStore->setId($id);
                            
                            return $workflow->can($this, $transition->getName());
                        } finally {
                            $markingStore->clear();
                        }
                    });
                    
                    // 注入 $model->applyTransitionName() 方法
                    $model::macro('apply' . $method, function(int|string $id, array $context = []) use ($transition, $workflow, $markingStore) : Marking {
                        try {
                            $markingStore->setId($id);
                            
                            return $workflow->apply($this, $transition->getName(), $context);
                        } catch (NotEnabledTransitionException $e) {
                            throw new ErrorTransitionException($e);
                        } finally {
                            $markingStore->clear();
                        }
                    });
                }
            }
        });
        
        $this->app->event->listen(ModelGenerator::class, function(ModelGenerator $generator) {
            $stateMachines = $this->parseStateMachine(get_class($generator->getModel()));
            if (!$stateMachines) {
                return;
            }
            
            foreach ($stateMachines as $machine) {
                $instance = $machine['instance'];
                $field    = $instance->getField();
                $studly   = StringHelper::studly($field);
                
                $generator->addDocMethod(
                    'get' . $studly . 'Workflow',
                    [],
                    Workflow::class
                );
                
                $generator->addDocMethod(
                    'dump' . $studly . 'Diagram',
                    [
                        new Argument('dumper', DumperInterface::class),
                        new Argument('marking', [Marking::class, 'null'], 'null'),
                        new Argument('options', 'array', '[]'),
                    ],
                    'string'
                );
                $generator->addDocMethod(
                    'get' . $studly . 'Marking',
                    [
                        new Argument($generator->getPk(), $generator->getPkType()),
                        new Argument('context', 'array', '[]'),
                    ],
                    'string'
                );
                $generator->addDocMethod(
                    'get' . $studly . 'EnabledTransitions',
                    [
                        new Argument($generator->getPk(), $generator->getPkType()),
                    ],
                    Transition::class . '[]'
                );
                
                $generator->addDocMethod(
                    'get' . $studly . 'EnabledTransition',
                    [
                        new Argument('name', 'string'),
                        new Argument($generator->getPk(), $generator->getPkType()),
                    ],
                    [
                        Transition::class,
                        'null'
                    ]
                );
                
                $generator->addDocMethod(
                    'apply' . $studly . 'To',
                    [
                        new Argument('name', 'string'),
                        new Argument($generator->getPk(), $generator->getPkType()),
                        new Argument('context', 'array', '[]'),
                    ],
                    Marking::class
                );
                
                $generator->addDocMethod(
                    'can' . $studly . 'To',
                    [
                        new Argument('name', 'string'),
                        new Argument($generator->getPk(), $generator->getPkType()),
                    ],
                    'bool'
                );
                
                foreach ($instance->getTransitions() as $transition) {
                    $method = StringHelper::studly($transition->getName());
                    
                    $generator->addDocMethod(
                        'can' . $method,
                        [
                            new Argument($generator->getPk(), $generator->getPkType()),
                        ],
                        'bool'
                    );
                    
                    $generator->addDocMethod(
                        'apply' . $method,
                        [
                            new Argument($generator->getPk(), $generator->getPkType()),
                            new Argument('context', 'array', '[]'),
                        ],
                        Marking::class
                    );
                }
            }
        });
        
        
        Field::maker(function(Field $field) {
            // 获取绑定的model类
            $modelClass = $field::getModelClass(false);
            if (!$modelClass) {
                return;
            }
            
            if (!$stateMachines = $this->parseStateMachine($modelClass)) {
                return;
            }
            $fieldClassName = get_class($field);
            foreach ($stateMachines as $machine) {
                $instance     = $machine['instance'];
                $definition   = $machine['definition'];
                $markingStore = $machine['marking'];
                
                $workflow = new Workflow($definition, $markingStore, null, $fieldClassName . '@' . $instance->getField(), []);
                $this->registry->addWorkflow($workflow, new InstanceOfSupportStrategy($fieldClassName));
                
                foreach ($instance->getTransitions() as $transition) {
                    $name = 'can' . StringHelper::studly($transition->getName());
                    
                    $field->{$name} = $workflow->can($field, $transition->getName());
                }
            }
        });
        
        $this->app->event->listen(FieldGenerator::class, function(FieldGenerator $generator) {
            $stateMachines = $this->parseStateMachine(get_class($generator->getModel()));
            if (!$stateMachines) {
                return;
            }
            
            foreach ($stateMachines as $machine) {
                foreach ($machine['instance']->getTransitions() as $transition) {
                    $generator->addDocProperty('can' . StringHelper::studly($transition->getName()), 'bool', true);
                }
            }
        });
    }
}