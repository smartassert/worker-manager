<?php

declare(strict_types=1);

namespace App\ValueResolver;

use App\Request\MachineRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class MachineRequestResolver implements ValueResolverInterface
{
    /**
     * @return MachineRequest[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (MachineRequest::class !== $argument->getType()) {
            return [];
        }

        $id = $request->attributes->get(MachineRequest::KEY_ID);
        $id = is_string($id) ? trim($id) : null;
        $id = '' === $id ? null : $id;

        if (null === $id) {
            return [];
        }

        return [new MachineRequest($id)];
    }
}
