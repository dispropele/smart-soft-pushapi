<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\PledgedItem;
use App\Entity\PledgedItemImage;
use App\Entity\Metal;
use App\Entity\MetalStandard;
use App\Entity\SystemLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmartLombardHandler
{
    private string $uploadDir;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface    $httpClient,
        private KernelInterface        $kernel,
        private LoggerInterface        $logger,
        private SystemLogger           $systemLogger,
        #[Autowire('%env(SMARTLOMBARD_API_SECRET)%')]
        private string $secret
    ) {
        $this->uploadDir = $this->kernel->getProjectDir() . '/public/uploads/sl_images/';
    }

    private function downloadAndSaveImage(?string $url, string $prefix, int $id, string $entityType): ?string
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) return null;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout'       => 30,
                'verify_peer'   => false,
                'verify_host'   => false,
                'max_redirects' => 5,
                'headers'       => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0 Safari/537.36',
                    'Accept'     => 'image/*, */*',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('HTTP ' . $response->getStatusCode());
            }

            $filesystem = new Filesystem();
            if (!$filesystem->exists($this->uploadDir)) {
                $filesystem->mkdir($this->uploadDir, 0775);
            }

            $ext      = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "{$prefix}_{$id}_" . bin2hex(random_bytes(4)) . ".{$ext}";
            $filesystem->dumpFile($this->uploadDir . $filename, $response->getContent());

            return '/uploads/sl_images/' . $filename;

        } catch (\Exception $e) {
            $this->logger->error('SmartLombard Image Error: ' . $e->getMessage(), [
                'url' => $url, 'entity' => $entityType, 'id' => $id,
            ]);
            $this->writeLog($entityType, 'image_download_fail', $id, ['url' => $url], true, false, $e->getMessage());
            return null;
        }
    }

    public function isValidSignature(string $rawData, ?string $receivedHash): bool
    {
        if (!$receivedHash) return false;
        return hash_equals($receivedHash, sha1(sha1($rawData) . $this->secret));
    }

    public function handleWebhook(array $payload): array
    {
        $responses = [];

        foreach ($payload as $batch) {
            $data = $batch['data'] ?? [];
            foreach ($data['goods'] ?? [] as $action) {
                $responses[] = $this->processGood($action);
            }
        }

        $this->entityManager->flush();
        return $responses;
    }

    private function processGood(array $action): array
    {
        $type      = $action['type'];
        $articleId = (int) ($action['article'] ?? $action['data']['article'] ?? 0);
        $data      = $action['data'] ?? [];

        try {
            $item = $this->entityManager->getRepository(PledgedItem::class)->find($articleId);

            if ($type === 'remove') {
                if ($item) $this->entityManager->remove($item);
                $this->writeLog('pledged_item', 'remove', $articleId, $action, true, true);
                return ['status' => true, 'type' => 'pledged_item-remove', 'unique' => $articleId];
            }

            if (!$item) {
                $item = new PledgedItem();
                (new \ReflectionProperty(PledgedItem::class, 'id'))->setValue($item, $articleId);
            }

            $item->setName($data['name'] ?? $item->getName());
            $item->setSoldPrice($data['price'] ?? '0');
            $item->setSize($data['size'] ?? null);
            $item->setDescription($data['features'] ?? null);
            $item->setSpecification($data['specifications'] ?? null);

            $status = match (true) {
                (bool) ($data['sold']      ?? false) => PledgedItem::STATUS_SOLD,
                (bool) ($data['withdrawn'] ?? false) => PledgedItem::STATUS_WITHDRAWN,
                (bool) ($data['hidden']    ?? false) => PledgedItem::STATUS_HIDDEN,
                default                              => PledgedItem::STATUS_FOR_SALE,
            };
            $item->setStatus($status);

            if (!empty($data['category'])) {
                $item->setCategory($this->findOrCreateCategory($data['category']));
            }

            if (!empty($data['currency'])) {
                $item->setCurrency($this->findOrCreateCurrency($data['currency']));
            }

            if (!empty($data['metal_name']) && !empty($data['metal_standart_name'])) {
                $item->setMetalStandard(
                    $this->findOrCreateMetalStandard($data['metal_name'], $data['metal_standart_name'])
                );
            }

            if (array_key_exists('images', $data)) {
                foreach ($item->getImages() as $old) {
                    $this->entityManager->remove($old);
                }
                foreach ($data['images'] as $imgData) {
                    $src  = $this->downloadAndSaveImage($imgData['src']     ?? null, 'item_full', $articleId, 'pledged_item');
                    $prev = $this->downloadAndSaveImage($imgData['preview'] ?? null, 'item_prev', $articleId, 'pledged_item');
                    if ($src) {
                        $img = new PledgedItemImage();
                        $img->setPledgedItem($item);
                        $img->setSrc($src);
                        $img->setPreview($prev ?? $src);
                        $img->setIsCover((bool) ($imgData['cover'] ?? false));
                        $this->entityManager->persist($img);
                    }
                }
            }

            $this->entityManager->persist($item);
            $this->writeLog('pledged_item', $type, $articleId, $action, true, true);

            return ['status' => true, 'type' => 'pledged_item-' . $type, 'unique' => $articleId, 'message' => 'Saved'];

        } catch (\Exception $e) {
            $this->writeLog('pledged_item', $type, $articleId, $action, true, false, $e->getMessage());
            throw $e;
        }
    }

    private function findOrCreateCategory(string $name): Category
    {
        $cat = $this->entityManager->getRepository(Category::class)->findOneBy(['name' => $name]);

        if (!$cat) {
            $cat = new Category();
            $cat->setName($name);
            $this->entityManager->persist($cat);
        }

        return $cat;
    }

    private function findOrCreateCurrency(string $currencyStr): Currency
    {
        $map  = ['руб.' => 'RUB', 'руб' => 'RUB', '₽' => 'RUB', 'usd' => 'USD', '$' => 'USD', '€' => 'EUR'];
        $code = $map[mb_strtolower(trim($currencyStr))] ?? mb_strtoupper(trim($currencyStr));

        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['currency' => $code]);
        if (!$currency) {
            $currency = new Currency();
            $currency->setCurrency($code);
            $currency->setName($currencyStr);
            $this->entityManager->persist($currency);
        }
        return $currency;
    }

    private function findOrCreateMetalStandard(string $metalName, string $standardName): MetalStandard
    {
        $ms = $this->entityManager->createQueryBuilder()
            ->select('ms')->from(MetalStandard::class, 'ms')->join('ms.metal', 'm')
            ->where('ms.name = :sname')->setParameter('sname', $standardName)
            ->andWhere('m.name = :mname')->setParameter('mname', $metalName)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if ($ms) return $ms;

        $metal = $this->entityManager->getRepository(Metal::class)->findOneBy(['name' => $metalName]);
        if (!$metal) {
            $metal = new Metal();
            $metal->setName($metalName);
            $this->entityManager->persist($metal);
        }

        $ms = new MetalStandard();
        $ms->setMetal($metal);
        $ms->setName($standardName);
        $this->entityManager->persist($ms);

        return $ms;
    }

    public function writeLog(
        string  $entityType,
        string  $eventType,
        ?int    $entityId,
        array   $payload,
        bool    $auth,
        bool    $process,
        ?string $error = null
    ): void {
        $level   = $process ? SystemLog::LEVEL_INFO : SystemLog::LEVEL_WARNING;
        $message = sprintf('[%s] %s #%s', $entityType, $eventType, $entityId ?? '?');
        if ($error) {
            $message .= ': ' . $error;
            $level = SystemLog::LEVEL_ERROR;
        }

        $this->systemLogger->{'info' === $level ? 'info' : ('warning' === $level ? 'warning' : 'error')}(
            SystemLog::CHANNEL_SYSTEM,
            $message,
            ['payload_keys' => array_keys($payload), 'auth' => $auth],
            $entityId
        );
    }

    /** @deprecated Use writeLog() */
    public function logRequest(string $entity, string $event, ?int $id, array $payload, bool $auth, bool $process, ?string $error = null): void
    {
        $this->writeLog($entity, $event, $id, $payload, $auth, $process, $error);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
