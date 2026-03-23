<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

use App\Services\MachineManager\DigitalOcean\Entity\Error;
use App\Services\MachineManager\DigitalOcean\Request\CreateDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\RequestInterface;

class ImageNoLongerAvailableException extends ErrorException
{
    public const string MESSAGE_IDENTIFIER = 'image you selected is no longer available';

    public function __construct(Error $error, RequestInterface $request)
    {
        parent::__construct($error, $request);
    }

    public function getImageId(): string
    {
        return $this->request instanceof CreateDropletRequest
            ? $this->request->getConfiguration()->getImage()
            : '';
    }
}
