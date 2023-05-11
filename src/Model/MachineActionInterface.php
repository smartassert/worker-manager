<?php

namespace App\Model;

interface MachineActionInterface
{
    public const ACTION_CREATE = 'create';
    public const ACTION_GET = 'get';
    public const ACTION_DELETE = 'delete';
    public const ACTION_EXISTS = 'exists';
    public const ACTION_FIND = 'find';
    public const ACTION_CHECK_IS_ACTIVE = 'check_is_active';
}
