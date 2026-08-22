<?php

namespace Ehyiah\ApiDocBundle\Tests\Dummy;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class DummyController extends AbstractController
{
    public function getUsers(): JsonResponse
    {
        return new JsonResponse(['users' => []]);
    }

    public function createUser(): JsonResponse
    {
        return new JsonResponse(['status' => 'created'], 201);
    }
}
