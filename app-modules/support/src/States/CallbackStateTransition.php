<?php

namespace CorvMC\Support\States;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\DefaultTransition;

class CallbackStateTransition extends DefaultTransition
{
    private array $arguments;

    public function __construct(Model $model, string $field, State $toState, array ...$arguments)
    {
        parent::__construct($model, $field, $toState);
        $this->arguments = $arguments;
    }

    /**
     * @return  \Illuminate\Database\Eloquent\Model
     */
    public function handle()
    {
        $originalState = $this->model->{$this->field} ? clone $this->model->{$this->field} : null;

        // Call onExit hook if the old state has one
        if ($originalState instanceof CallbackStateContract) {
            $originalState->exiting(...$this->arguments);
        }

        // Call onEntry hook if the new state has one
        if ($this->newState instanceof CallbackStateContract) {
            $this->newState->entering(...$this->arguments);
        }

        // Perform the actual transition
        $this->model->{$this->field} = $this->newState;

        if ($originalState instanceof CallbackStateContract) {
            $originalState->exited(...$this->arguments);
        }

        if ($this->newState instanceof CallbackStateContract) {
            $this->newState->entered(...$this->arguments);
        }


        $this->model->saveOrFail();

        return $this->model;
    }
}
