<?php

namespace App\Controller;

use App\Service\SmartLombardHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PushApiController extends AbstractController
{
    #[Route('/api/push', name: 'app_push_api', methods: ['POST'])]
    public function index(Request $request, SmartLombardHandler $handler): JsonResponse
    {
        $rawData = $request->request->get('data') ?? $request->getContent();
        $hash    = $request->headers->get('AuthorizationSL');

        if (!$handler->isValidSignature($rawData, $hash)) {
            $handler->writeLog(
                entityType: 'system',
                eventType:  'auth_fail',
                entityId:   null,
                payload:    ['raw' => mb_substr($rawData, 0, 500)], // не храним весь payload при неавторизованном запросе
                auth:       false,
                process:    false,
                error:      'Invalid signature'
            );
            $handler->flush();

            return new JsonResponse([
                'status'  => false,
                'type'    => 'auth',
                'message' => 'Authorization failed',
            ]);
        }

        $payload = json_decode($rawData, true);

        if (!$payload) {
            $handler->writeLog(
                entityType: 'system',
                eventType:  'parse_error',
                entityId:   null,
                payload:    [],
                auth:       true,
                process:    false,
                error:      'Invalid JSON: ' . json_last_error_msg()
            );
            $handler->flush();

            return new JsonResponse([
                'status'  => false,
                'type'    => 'error',
                'message' => 'Invalid JSON',
            ]);
        }

        try {
            $results = $handler->handleWebhook($payload);

            return new JsonResponse($results);

        } catch (\Exception $e) {
            $handler->writeLog(
                entityType: 'system',
                eventType:  'exception',
                entityId:   null,
                payload:    $payload,
                auth:       true,
                process:    false,
                error:      $e->getMessage()
            );
            $handler->flush();

            return new JsonResponse([
                'status'  => false,
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
