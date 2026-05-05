<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\City;
use App\Entity\Currency;
use App\Entity\Good;
use App\Entity\GoodImage;
use App\Entity\Metal;
use App\Entity\Merchant;
use App\Entity\MetalStandard;
use App\Entity\PushApiLog;
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
            foreach ($data['merchants'] ?? [] as $action) {
                $responses[] = $this->processMerchant($action);
            }
            foreach ($data['goods'] ?? [] as $action) {
                $responses[] = $this->processGood($action);
            }
        }

        $this->entityManager->flush();
        return $responses;
    }

    private function processMerchant(array $action): array
    {
        $type        = $action['type'];
        $data        = $action['data'];
        $workplaceId = (int) $data['workplace'];

        try {
            $merchant = $this->entityManager->getRepository(Merchant::class)->find($workplaceId);

            if ($type === 'remove') {
                if ($merchant) $this->entityManager->remove($merchant);
                $this->writeLog('merchant', 'remove', $workplaceId, $action, true, true);
                return ['status' => true, 'type' => 'merchant-remove', 'unique' => $workplaceId];
            }

            if (!$merchant) {
                $merchant = new Merchant();
                (new \ReflectionProperty(Merchant::class, 'id'))->setValue($merchant, $workplaceId);
            }

            if (!empty($data['city'])) {
                $city = $this->entityManager->getRepository(City::class)->findOneBy(['name' => $data['city']]);
                if (!$city) {
                    $city = new City();
                    $city->setName($data['city']);
                    $this->entityManager->persist($city);
                }
                $merchant->setCity($city);
            }

            $merchant->setName($data['name'] ?? $merchant->getName());
            $merchant->setAddress($data['address'] ?? null);
            $merchant->setPhone($data['phone'] ?? null);
            $merchant->setShortlink($data['shortlink'] ?? null);
            $merchant->setDescription($data['description'] ?? null);

            if (!empty($data['image'])) {
                $img = $data['image'];
                if (isset($img['src'])) {
                    $merchant->setImageSrc($this->downloadAndSaveImage($img['src'], 'merchant_full', $workplaceId, 'merchant'));
                }
                if (isset($img['preview'])) {
                    $merchant->setImagePreview($this->downloadAndSaveImage($img['preview'], 'merchant_prev', $workplaceId, 'merchant'));
                }
            }

            $this->entityManager->persist($merchant);
            $this->writeLog('merchant', $type, $workplaceId, $action, true, true);

            return ['status' => true, 'type' => 'merchant-' . $type, 'unique' => $workplaceId, 'message' => 'OK'];

        } catch (\Exception $e) {
            $this->writeLog('merchant', $type, $workplaceId, $action, true, false, $e->getMessage());
            throw $e;
        }
    }

    private function processGood(array $action): array
    {
        $type      = $action['type'];
        $articleId = (int) ($action['article'] ?? $action['data']['article'] ?? 0);
        $data      = $action['data'] ?? [];

        try {
            $good = $this->entityManager->getRepository(Good::class)->find($articleId);

            if ($type === 'remove') {
                if ($good) $this->entityManager->remove($good);
                $this->writeLog('good', 'remove', $articleId, $action, true, true);
                return ['status' => true, 'type' => 'good-remove', 'unique' => $articleId];
            }

            if (!$good) {
                $good = new Good();
                (new \ReflectionProperty(Good::class, 'id'))->setValue($good, $articleId);
            }

            if (!empty($data['workplace'])) {
                $merchant = $this->entityManager->getRepository(Merchant::class)->find((int) $data['workplace']);
                if ($merchant) $good->setMerchant($merchant);
            }

            $good->setName($data['name'] ?? $good->getName());
            $good->setSoldPrice($data['price'] ?? '0');
            $good->setSize($data['size'] ?? null);
            $good->setDescription($data['features'] ?? null);
            $good->setSpecification($data['specifications'] ?? null);
            $good->setHiddenReason(isset($data['hidden_reason']) ? (int) $data['hidden_reason'] : null);

            $status = match (true) {
                (bool) ($data['sold']      ?? false) => Good::STATUS_SOLD,
                (bool) ($data['withdrawn'] ?? false) => Good::STATUS_WITHDRAWN,
                (bool) ($data['hidden']    ?? false) => Good::STATUS_HIDDEN,
                default                              => Good::STATUS_ACTIVE,
            };
            $good->setStatus($status);

            $parentCategory = null;
            if (!empty($data['category'])) {
                $parentCategory = $this->findOrCreateCategory($data['category'], null);
                $good->setCategory($parentCategory);
            }
            // подкатегорий больше нет — игнорируем поле subcategory

            // валюта
            if (!empty($data['currency'])) {
                $good->setCurrency($this->findOrCreateCurrency($data['currency']));
            }

            // металл / проба
            if (!empty($data['metal_name']) && !empty($data['metal_standart_name'])) {
                $good->setMetalStandard(
                    $this->findOrCreateMetalStandard($data['metal_name'], $data['metal_standart_name'])
                );
            }

            // изображения
            if (array_key_exists('images', $data)) {
                foreach ($good->getImages() as $old) {
                    $this->entityManager->remove($old);
                }
                foreach ($data['images'] as $imgData) {
                    $src  = $this->downloadAndSaveImage($imgData['src']     ?? null, 'good_full', $articleId, 'good');
                    $prev = $this->downloadAndSaveImage($imgData['preview'] ?? null, 'good_prev', $articleId, 'good');
                    if ($src) {
                        $img = new GoodImage();
                        $img->setGood($good);
                        $img->setSrc($src);
                        $img->setPreview($prev ?? $src);
                        $img->setIsCover((bool) ($imgData['cover'] ?? false));
                        $this->entityManager->persist($img);
                    }
                }
            }

            $this->entityManager->persist($good);
            $this->writeLog('good', $type, $articleId, $action, true, true);

            return ['status' => true, 'type' => 'good-' . $type, 'unique' => $articleId, 'message' => 'Saved'];

        } catch (\Exception $e) {
            $this->writeLog('good', $type, $articleId, $action, true, false, $e->getMessage());
            throw $e;
        }
    }

    private function findOrCreateCategory(string $name, ?Category $parent): Category
    {
        $cat = $this->entityManager->getRepository(Category::class)
            ->findOneBy(['name' => $name, 'parent' => $parent]);

        if (!$cat) {
            $cat = new Category();
            $cat->setName($name);
            $cat->setParent($parent);
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
        $log = new PushApiLog();
        $log->setEntityType($entityType);
        $log->setEventType($eventType);
        $log->setEntityId($entityId);
        $log->setPayload($payload);
        $log->setAuthStatus($auth);
        $log->setProcessStatus($process);
        $log->setErrorMessage($error);
        $this->entityManager->persist($log);
    }

    public function logRequest(string $entity, string $event, ?int $id, array $payload, bool $auth, bool $process, ?string $error = null): void
    {
        $this->writeLog($entity, $event, $id, $payload, $auth, $process, $error);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
