<?php

namespace App\Exception;

interface StackedExceptionInterface
{
    public function getExceptionStack(): Stack;
}
