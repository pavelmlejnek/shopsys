<?php

namespace SS6\ShopBundle\DataFixtures\Performance;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use SS6\ShopBundle\Component\DataFixture\AbstractReferenceFixture;
use SS6\ShopBundle\DataFixtures\Base\AvailabilityDataFixture;
use SS6\ShopBundle\DataFixtures\Base\FlagDataFixture;
use SS6\ShopBundle\DataFixtures\Base\VatDataFixture;
use SS6\ShopBundle\DataFixtures\Demo\BrandDataFixture;
use SS6\ShopBundle\DataFixtures\Demo\CategoryDataFixture;
use SS6\ShopBundle\Model\Product\Product;
use SS6\ShopBundle\Model\Product\ProductEditData;

class ProductDataFixture extends AbstractReferenceFixture {

	const PRODUCTS = 40000;
	const BATCH_SIZE = 1000;

	/**
	 * @var int
	 */
	private $randomImportIndex;

	/**
	 * @var int
	 */
	private $countImported;

	/**
	 * @var int
	 */
	private $demoDataIterationCounter;

	/**
	 * @var float
	 */
	private $batchStartMicrotime;

	/**
	 * @var \Doctrine\DBAL\Logging\SQLLogger|null
	 */
	private $sqlLogger;

	/**
	 * @var \SS6\ShopBundle\Model\Product\Product[catnum]
	 */
	private $productsByCatnum;

	public function __construct() {
		$this->randomImportIndex = rand(1, 10000) * 1000000;
		$this->countImported = 0;
		$this->demoDataIterationCounter = 0;
	}

	/**
	 * @param \Doctrine\Common\Persistence\ObjectManager $objectManager
	 */
	public function load(ObjectManager $objectManager) {
		$em = $this->get('doctrine.orm.entity_manager');
		/* @var $em \Doctrine\ORM\EntityManager */
		$productEditFacade = $this->get('ss6.shop.product.product_edit_facade');
		/* @var $productEditFacade \SS6\ShopBundle\Model\Product\ProductEditFacade */
		$loaderService = $this->get('ss6.shop.data_fixtures.product_data_fixture_loader');
		/* @var $loaderService \SS6\ShopBundle\DataFixtures\Demo\ProductDataFixtureLoader */

		// Sql logging during mass data import makes memory leak
		$this->temporailyDisableLogging($em);
		$productsEditData = $this->cleanAndWarmUp($em);
		$variantCatnumsByMainVariantCatnum = $loaderService->getVariantCatnumsIndexedByMainVariantCatnum();

		while ($this->countImported < self::PRODUCTS) {
			$productEditData = next($productsEditData);
			if ($productEditData === false) {
				$this->createVariants($variantCatnumsByMainVariantCatnum);
				$productEditData = reset($productsEditData);
				$this->demoDataIterationCounter++;
			}
			$this->makeProductEditDataUnique($productEditData);
			$product = $productEditFacade->create($productEditData);

			if ($product->getCatnum() !== null) {
				$this->productsByCatnum[$product->getCatnum()] = $product;
			}

			$this->printProgress();
			if ($this->countImported % self::BATCH_SIZE === 0) {
				$productsEditData = $this->cleanAndWarmUp($em);
			}

			$this->countImported++;
		}
		$this->createVariants($variantCatnumsByMainVariantCatnum);
		$this->runRecalculators(true);
		$em->clear();
		$this->reenableLogging($em);
	}

	private function printProgress() {
		$spentMicrotime = microtime(true) - $this->batchStartMicrotime;
		$batchNumber = ceil($this->countImported / self::BATCH_SIZE);
		$totalBatches = ceil(self::PRODUCTS / self::BATCH_SIZE);
		$batchImported = $this->countImported % self::BATCH_SIZE;
		$batchImported = $batchImported ?: self::BATCH_SIZE;
		echo sprintf(
			'Batch %2d / %2d - %3d%% - %4.1f s / %2.3f s' . "\r",
			$batchNumber,
			$totalBatches,
			100 * $this->countImported / self::PRODUCTS,
			$spentMicrotime,
			$spentMicrotime / $batchImported
		);
	}

	/**
	 * @param string[catnum][] $variantCatnumsByMainVariantCatnum
	 */
	private function createVariants(array $variantCatnumsByMainVariantCatnum) {
		$uniqueIndex = $this->getUniqueIndex();
		$variantsToFlush = [];

		foreach ($variantCatnumsByMainVariantCatnum as $mainVariantCatnum => $variantsCatnums) {
			try {
				$mainVariant = $this->getProductByCatnum($mainVariantCatnum . $uniqueIndex);
				foreach ($variantsCatnums as $variantCatnum) {
					$variant = $this->getProductByCatnum($variantCatnum . $uniqueIndex);
					$mainVariant->addVariant($variant);
					$variantsToFlush[] = $variant;
				}
			} catch (\Doctrine\ORM\NoResultException $e) {
				continue;
			}
		}

		$em = $this->get(EntityManager::class);
		/* @var $em \Doctrine\ORM\EntityManager */
		$em->flush($variantsToFlush);
	}

	/**
	 * @param string $catnum
	 * @return \SS6\ShopBundle\Model\Product\Product
	 */
	private function getProductByCatnum($catnum) {
		if (!array_key_exists($catnum, $this->productsByCatnum)) {
			$em = $this->get(EntityManager::class);
			/* @var $em \Doctrine\ORM\EntityManager */

			$query = $em->createQuery('SELECT p FROM ' . Product::class . ' p WHERE p.catnum = :catnum')
				->setParameter('catnum', $catnum);
			$this->productsByCatnum[$catnum] = $query->getSingleResult();
		}

		return $this->productsByCatnum[$catnum];
	}

	/**
	 * @param \Doctrine\ORM\EntityManager $em
	 */
	private function temporailyDisableLogging(EntityManager $em) {
		$this->sqlLogger = $em->getConnection()->getConfiguration()->getSQLLogger();
		$em->getConnection()->getConfiguration()->setSQLLogger(null);
	}

	/**
	 * @param \Doctrine\ORM\EntityManager $em
	 */
	private function reenableLogging(EntityManager $em) {
		$em->getConnection()->getConfiguration()->setSQLLogger($this->sqlLogger);
		$this->sqlLogger = null;
	}

	/**
	 * @param \SS6\ShopBundle\Model\Product\ProductEditData $productEditData
	 */
	private function makeProductEditDataUnique(ProductEditData $productEditData) {
		$matches = [];
		$uniqueIndex = $this->getUniqueIndex();

		if (preg_match('/^(.*) #\d+$/', $productEditData->productData->catnum, $matches)) {
			$productEditData->productData->catnum = $matches[1] . $uniqueIndex;
		} else {
			$productEditData->productData->catnum .= $uniqueIndex;
		}

		foreach ($productEditData->productData->name as $locale => $name) {
			if (preg_match('/^(.*) #\d+$/', $name, $matches)) {
				$productEditData->productData->name[$locale] = $matches[1] . $uniqueIndex;
			} else {
				$productEditData->productData->name[$locale] .= $uniqueIndex;
			}
		}
	}

	/**
	 * @return string
	 */
	private function getUniqueIndex() {
		return ' #' . ($this->randomImportIndex + $this->demoDataIterationCounter);
	}

	/**
	 * @param bool $runGlobalRecalculators
	 */
	private function runRecalculators($runGlobalRecalculators = false) {
		$productAvailabilityRecalculator = $this->get('ss6.shop.product.availability.product_availability_recalculator');
		/* @var $productAvailabilityRecalculator \SS6\ShopBundle\Model\Product\Availability\ProductAvailabilityRecalculator */
		$productVisibilityFacade = $this->get('ss6.shop.product.product_visibility_facade');
		/* @var $productVisibilityFacade \SS6\ShopBundle\Model\Product\ProductVisibilityFacade */
		$productPriceRecalculator = $this->get('ss6.shop.product.pricing.product_price_recalculator');
		/* @var $productPriceRecalculator \SS6\ShopBundle\Model\Product\Pricing\ProductPriceRecalculator */

		$productAvailabilityRecalculator->runImmediateRecalculations();
		$productPriceRecalculator->runImmediateRecalculations();
		if ($runGlobalRecalculators) {
			$productVisibilityFacade->refreshProductsVisibility();
		}
	}

	/**
	 * @param \Doctrine\ORM\EntityManager $em
	 */
	private function clearResources(EntityManager $em) {
		$this->runRecalculators();
		$em->clear();
		gc_collect_cycles();
		echo "\nMemory usage: " . round(memory_get_usage() / 1024 / 1024, 1) . "MB\n";
	}

	/**
	 * @param \Doctrine\ORM\EntityManager $em
	 * @return \SS6\ShopBundle\Model\Product\ProductEditData[]
	 */
	private function cleanAndWarmUp(EntityManager $em) {
		$this->clearResources($em);
		$this->batchStartMicrotime = microtime(true);
		$this->productsByCatnum = [];

		$loaderService = $this->get('ss6.shop.data_fixtures.product_data_fixture_loader');
		/* @var $loaderService \SS6\ShopBundle\DataFixtures\Demo\ProductDataFixtureLoader */
		$persistentReferenceService = $this->get('ss6.shop.data_fixture.persistent_reference_service');
		/* @var $persistentReferenceService \SS6\ShopBundle\Component\DataFixture\PersistentReferenceService */

		$vats = [
			'high' => $persistentReferenceService->getReference(VatDataFixture::VAT_HIGH),
			'low' => $persistentReferenceService->getReference(VatDataFixture::VAT_LOW),
			'zero' => $persistentReferenceService->getReference(VatDataFixture::VAT_ZERO),
		];
		$availabilities = [
			'in-stock' => $persistentReferenceService->getReference(AvailabilityDataFixture::IN_STOCK),
			'out-of-stock' => $persistentReferenceService->getReference(AvailabilityDataFixture::OUT_OF_STOCK),
			'on-request' => $persistentReferenceService->getReference(AvailabilityDataFixture::ON_REQUEST),
		];
		$categories = [
			'1' => $persistentReferenceService->getReference(CategoryDataFixture::TV),
			'2' => $persistentReferenceService->getReference(CategoryDataFixture::PHOTO),
			'3' => $persistentReferenceService->getReference(CategoryDataFixture::PRINTERS),
			'4' => $persistentReferenceService->getReference(CategoryDataFixture::PC),
			'5' => $persistentReferenceService->getReference(CategoryDataFixture::PHONES),
			'6' => $persistentReferenceService->getReference(CategoryDataFixture::COFFEE),
			'7' => $persistentReferenceService->getReference(CategoryDataFixture::BOOKS),
			'8' => $persistentReferenceService->getReference(CategoryDataFixture::TOYS),
		];

		$flags = [
			'action' => $persistentReferenceService->getReference(FlagDataFixture::ACTION_PRODUCT),
			'new' => $persistentReferenceService->getReference(FlagDataFixture::NEW_PRODUCT),
			'top' => $persistentReferenceService->getReference(FlagDataFixture::TOP_PRODUCT),
		];

		$brands = [
			'apple' => $persistentReferenceService->getReference(BrandDataFixture::APPLE),
			'canon' => $persistentReferenceService->getReference(BrandDataFixture::CANON),
			'lg' => $persistentReferenceService->getReference(BrandDataFixture::LG),
			'philips' => $persistentReferenceService->getReference(BrandDataFixture::PHILIPS),
		];

		$loaderService->injectReferences($vats, $availabilities, $categories, $flags, $brands);

		return $loaderService->getProductsEditData();
	}

}
