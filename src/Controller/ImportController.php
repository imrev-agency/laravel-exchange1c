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

            return $this->respond($response, 500);
        }
    }

    /**
     * 1C's exchange client (checkauth/init/CommerceML) still expects Windows-1251
     * by default, so transcode the UTF-8 payload we build internally before
     * sending it, and declare the matching charset in both the XML prolog and
     * the Content-Type header — otherwise 1C misreads the bytes and shows
     * mojibake for Cyrillic fields.
     */
    private function respond(string $response, int $status = 200)
    {
        $response = str_ireplace('utf-8', 'windows-1251', $response);
        $response = iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $response);

        return response($response, $status, ['Content-Type' => 'text/plain; charset=windows-1251']);
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

            return $this->respond($response);
        }

        if ($mode !== 'import') {
            throw new Exchange1CException('not correct request, class ExchangeCML not found');
        }

        // Synchronous, per protocol: 1C polls this exact request until it stops
        // seeing "progress" (http://v8.1c.ru/edi/edi_stnd/90/92.htm). Our files are
        // small (KB, not GB) and the endpoint is meant to be called by 1C, not a
        // browser, so a blocking response here is correct — it used to be dispatched
        // to a queued job that reported "success" immediately regardless of whether
        // (or when) the job actually ran, which both lied to the caller and silently
        // dropped the import if nothing was consuming the queue.
        $service->import($request, (string) $request->get('filename'));
        $response = "success\n";

        $this->log(sprintf(
            'New request, type: %s, mode: %s, response: %s',
            $type,
            $mode,
            $response
        ));

        return $this->respond($response);
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleSale(Request $request, CatalogService $service, SaleService $saleService, string $mode, string $type)
    {
        // type=sale is symmetric with type=catalog: checkauth/init/file are
        // the same generic handshake+upload machinery (CatalogService isn't
        // catalog-specific despite the name), query/success are the
        // site->1C direction (new orders), file+import is 1C->site (status
        // updates uploaded as a file, then processed).
        $response = match ($mode) {
            'checkauth', 'init' => $service->$mode($request),
            'file' => $service->file($request),
            'query' => $saleService->query($request, (int) $request->get('orderIDfrom', 0)),
            'success' => $saleService->success($request),
            'import' => $this->importSaleUpdates($saleService, (string) $request->get('filename')),
            default => throw new Exchange1CException("Logic for type={$type}, mode={$mode} not released"),
        };

        $this->log(sprintf(
            'New sale request, type: %s, mode: %s, response length: %d',
            $type,
            $mode,
            strlen($response)
        ));

        return $this->respond($response);
    }

    private function importSaleUpdates(SaleService $saleService, string $filename): string
    {
        $saleService->importUpdates($filename);

        return "success\n";
    }

    private function log(string $message, string $type = 'info'): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->$type($message);
    }
}
