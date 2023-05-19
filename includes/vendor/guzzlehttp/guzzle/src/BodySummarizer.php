<?php

namespace Spark\Connexions\MailChimp\Vendor\GuzzleHttp;

use Spark\Connexions\MailChimp\Vendor\Psr\Http\Message\MessageInterface;

final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;

    public function __construct(int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }

    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string
    {
        return $this->truncateAt === null
            ? \Spark\Connexions\MailChimp\Vendor\GuzzleHttp\Psr7\Message::bodySummary($message)
            : \Spark\Connexions\MailChimp\Vendor\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}
