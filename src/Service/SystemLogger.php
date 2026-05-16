<?php
namespace App\Service;

use App\Entity\SystemLog;
use Doctrine\ORM\EntityManagerInterface;

class SystemLogger
{
    public function __construct(private EntityManagerInterface $em) {}

    public function info(string $channel, string $message, array $context = [], ?int $refId = null): void
    {
        $this->write(SystemLog::LEVEL_INFO, $channel, $message, $context, $refId);
    }

    public function warning(string $channel, string $message, array $context = [], ?int $refId = null): void
    {
        $this->write(SystemLog::LEVEL_WARNING, $channel, $message, $context, $refId);
    }

    public function error(string $channel, string $message, array $context = [], ?int $refId = null): void
    {
        $this->write(SystemLog::LEVEL_ERROR, $channel, $message, $context, $refId);
    }

    public function critical(string $channel, string $message, array $context = [], ?int $refId = null): void
    {
        $this->write(SystemLog::LEVEL_CRITICAL, $channel, $message, $context, $refId);
    }

    public function exception(\Throwable $e, string $channel = SystemLog::CHANNEL_SYSTEM, array $extra = []): void
    {
        $this->write(SystemLog::LEVEL_ERROR, $channel, $e->getMessage(), array_merge([
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => substr($e->getTraceAsString(), 0, 1000),
        ], $extra));
    }

    private function write(string $level, string $channel, string $message, array $context, ?int $refId = null): void
    {
        $log = new SystemLog();
        $log->setLevel($level)
            ->setChannel($channel)
            ->setMessage($message)
            ->setContext($context)
            ->setReferenceId($refId);

        $this->em->persist($log);
        $this->em->flush();
    }
}