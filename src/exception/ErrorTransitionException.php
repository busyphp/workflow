<?php
declare(strict_types = 1);

namespace BusyPHP\workflow\exception;

use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\TransitionBlockerList;

/**
 * 无法设置状态异常
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/20 01:16 NotEnabledTransitionException.php $
 */
class ErrorTransitionException extends TransitionException
{
    private TransitionBlockerList $transitionBlockerList;
    
    
    public function __construct(NotEnabledTransitionException $e)
    {
        $currentTransition = null;
        foreach ($e->getWorkflow()->getDefinition()->getTransitions() as $item) {
            if ($item->getName() === $e->getTransitionName()) {
                $currentTransition = $item;
            }
        }
        
        $metadata = $e->getWorkflow()->getMetadataStore()->getTransitionMetadata($currentTransition);
        $message  = $metadata['error'] ?? null;
        $message  = $message ?: sprintf('Can\'t set to "%s"', $e->getTransitionName());
        
        parent::__construct($e->getSubject(), $e->getTransitionName(), $e->getWorkflow(), $message, $e->getContext());
        
        $this->transitionBlockerList = $e->getTransitionBlockerList();
    }
    
    
    public function getTransitionBlockerList() : TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }
}