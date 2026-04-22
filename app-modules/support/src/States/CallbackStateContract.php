<?php

namespace CorvMC\Support\States;

interface CallbackStateContract
{
    public function entering(): void;
    public function entered(): void;
    public function exiting(): void;
    public function exited(): void;
}
