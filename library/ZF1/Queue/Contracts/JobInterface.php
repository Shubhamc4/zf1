<?php

declare(strict_types=1);

namespace ZF1\Queue\Contracts;

interface JobInterface
{
    /**
     * Execute the queued job.
     *
     * @param  array<string, mixed>  $payload
     * @return mixed Return false to indicate failure; true, null, or void indicates success.
     */
    public function handle(array $payload);
}
