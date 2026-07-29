<?php
/**
 * This file is part of bigperson/laravel-exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\LaravelExchange1C\Controller;

use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Bigperson\Exchange1C\Services\CatalogService;
use Bigperson\Exchange1C\Services\SaleService;
use Bigperson\LaravelExchange1C\Jobs\CatalogServiceJob;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Class ImportController.
 */
class ImportController extends Controller
{
    /**
     * @var Log
     */
    private $logger;

    /**
     * ImportController constructor.
     */
    public function __construct()
    {
        if (config('exchange1c.log_channel', false)) {
            $this->logger = Log::channel(config('exchange1c.log_channel'));
        }
    }

    /**
     * @param Request        $request
     * @param CatalogService $service
     * @param SaleService    $saleService
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function request(Request $request, CatalogService $service, SaleService $saleService)
    {
        $mode = (string) $request->get('mode');
        $type = $request->get('type');
        $this->log('request: '.print_r($request->all(), true));
        $this->log('headers: '.print_r($request->header(), true));

        try {
            if ($type == 'catalog') {
                return $this->handleCatalog($request, $service, $mode, $type);
            } elseif ($type === 'sale') {
                return $this->handleSale($request, $service, $saleService, $mode, $type);
            } else {
                $message = sprintf('Logic for method %s not released', $type);
                $this->log($message, 'error');

                throw new \LogicException($message);
            }
        } catch (Exchange1CException $e) {
            $this->log(
                "exchange_1c: failure \n".$e->getMessage()."\n".$e->getFile()."\n".$e->getLine()."\n",
                'error'
            );

            $response = "failure\n";
            $response .= $e->getMessage()."\n";
            $response .= $e->getFile()."\n";
            $response .= $e->getLine()."\n";

            return response($response, 500, ['Content-Type', 'text/plain']);
        }
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleCatalog(Request $request, CatalogService $service, string $mode, string $type)
    {
        if ($mode === 'init' || $mode === 'checkauth' || $mode === 'file') {
            $response = $mode === 'file'
                ? $service->file($request)
                : $service->$mode($request);
            $this->log(sprintf(
                'New init request, type: %s, mode: %s, response: %s',
                $type,
                $mode,
                $response
            ));

            return response($response, 200, ['Content-Type', 'text/plain']);
        }

        if (!in_array($mode, ['import'], true)) {
            throw new Exchange1CException('not correct request, class ExchangeCML not found');
        }

        CatalogServiceJob::dispatch(
            $request->all(),
            $request->session()->all()
        )
            ->delay(now()->addSeconds(10))
            ->onQueue(config('exchange1c.queue'));
        $response = "success\n";

        $this->log(sprintf(
            'New request, type: %s, mode: %s, response: %s. CatalogServiceJob is started',
            $type,
            $mode,
            $response
        ));

        return response($response, 200, ['Content-Type', 'text/plain']);
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleSale(Request $request, CatalogService $service, SaleService $saleService, string $mode, string $type)
    {
        $response = match ($mode) {
            'checkauth' => $service->checkauth($request),
            'query' => $saleService->query($request, (int) $request->get('orderIDfrom', 0)),
            'success' => $saleService->success($request),
            default => throw new Exchange1CException("Logic for type={$type}, mode={$mode} not released"),
        };

        $this->log(sprintf(
            'New sale request, type: %s, mode: %s, response length: %d',
            $type,
            $mode,
            strlen($response)
        ));

        return response($response, 200, ['Content-Type', 'text/plain']);
    }

    private function log(string $message, string $type = 'info'): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->$type($message);
    }
}
