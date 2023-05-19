<?php

namespace Spark\Connexions\MailChimp\Vendor\GuzzleHttp;

use Spark\Connexions\MailChimp\Vendor\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
