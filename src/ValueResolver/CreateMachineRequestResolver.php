<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Request\CreateMachineRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class CreateMachineRequestResolver implements ValueResolverInterface
{
    /**
     * @return CreateMachineRequest[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ('POST' !== $request->getMethod() || CreateMachineRequest::class !== $argument->getType()) {
            return [];
        }

        $id = $request->attributes->get(CreateMachineRequest::KEY_ID);
        $id = is_string($id) ? trim($id) : null;
        $id = '' === $id ? null : $id;

        return [new CreateMachineRequest($id)];
    }
}
