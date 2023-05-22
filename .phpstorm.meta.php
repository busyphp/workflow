<?php
namespace PHPSTORM_META {
    registerArgumentsSet(
        'workflow_event',
        \BusyPHP\workflow\StateMachineEvent::EVENT_GUARD |
        \BusyPHP\workflow\StateMachineEvent::EVENT_LEAVE |
        \BusyPHP\workflow\StateMachineEvent::EVENT_TRANSITION |
        \BusyPHP\workflow\StateMachineEvent::EVENT_ENTER |
        \BusyPHP\workflow\StateMachineEvent::EVENT_ENTERED |
        \BusyPHP\workflow\StateMachineEvent::EVENT_COMPLETED |
        \BusyPHP\workflow\StateMachineEvent::EVENT_ANNOUNCE
    );
    expectedArguments(\BusyPHP\workflow\StateMachineEvent::listen(), 0, argumentsSet('workflow_event'));
    expectedArguments(\BusyPHP\workflow\StateMachineEvent::listenField(), 0, argumentsSet('workflow_event'));
    expectedArguments(\BusyPHP\workflow\StateMachineEvent::listenName(), 0, argumentsSet('workflow_event'));
}