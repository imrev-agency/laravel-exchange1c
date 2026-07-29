<?php
/**
 * This file is part of bigperson/laravel-exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\LaravelExchange1C\Jobs;

use Bigperson\Exchange1C\Services\CatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Session\Session;

class CatalogServiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60 * 10;

    private $requestData;
    private $sessionData;

    public function __construct(array $requestData, array $sessionData)
    {
        $this->requestData = $requestData;
        $this->sessionData = $sessionData;
    }

    public function handle(): void
    {
        Log::debug('CatalogServiceJob handle', ['requestData' => $this->requestData, 'sessionData' => $this->sessionData]);
        $mode = $this->requestData['mode'];
        $request = (new Request())->replace($this->requestData);
        $session = app()->make(Session::class);
        $request->setSession($session);
        $request->session()->replace($this->sessionData);

        $service = app()->make(CatalogService::class);
        Log::debug('CatalogServiceJob handle', ['service' => $service, 'mode' => $mode]);

        if ($mode === 'import') {
            $service->import($request, (string) ($this->requestData['filename'] ?? ''));
        } else {
            $service->$mode($request);
        }

        Log::debug('CatalogServiceJob done');
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return ['1cExchange', 'mode: '.$this->requestData['mode'].', file: '.$this->requestData['filename']];
    }
}
