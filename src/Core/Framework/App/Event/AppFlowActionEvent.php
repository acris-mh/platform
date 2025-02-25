<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
class AppFlowActionEvent extends Event implements Hookable
{
    private string $name;

    /**
     * @var array<int|string, string>
     */
    private array $headers;

    /**
     * @var array<mixed>
     */
    private array $payload;

    /**
     * @param array<int|string, string> $headers
     * @param array<mixed> $payload
     */
    public function __construct(string $name, array $headers, array $payload)
    {
        $this->name = $name;
        $this->headers = $headers;
        $this->payload = $payload;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int|string, string>
     */
    public function getWebhookHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<mixed>
     */
    public function getWebhookPayload(): array
    {
        return $this->payload;
    }

    /**
     * Apps don't need special ACL permissions for action, so this function always return true
     */
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
