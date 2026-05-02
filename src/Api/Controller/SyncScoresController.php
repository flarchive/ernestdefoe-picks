<?php

namespace Resofire\Picks\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\Picks\Service\SyncScoresService;

class SyncScoresController implements RequestHandlerInterface
{
    public function __construct(
        protected SyncScoresService $syncScoresService
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertCan('picks.manage');

        try {
            $result = $this->syncScoresService->sync();
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'status'  => 'success',
            'updated' => $result['updated'],
            'scored'  => $result['scored'],
            'skipped' => $result['skipped'],
        ]);
    }
}
