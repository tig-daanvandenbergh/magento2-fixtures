<?php

namespace TddWizard\Fixtures\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CategoryBuilderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CategoryFixture[]
     */
    private $categories = [];

    /**
     * @var ProductFixture[]
     */
    private $products = [];

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    protected function setUp()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryRepository = $this->objectManager->create(CategoryRepositoryInterface::class);
        $this->categories = [];
        $this->products = [];
    }

    protected function tearDown()
    {
        if (!empty($this->categories)) {
            foreach ($this->categories as $product) {
                CategoryFixtureRollback::create()->execute($product);
            }
        }
        if (! empty($this->products)) {
            foreach ($this->products as $product) {
                ProductFixtureRollback::create()->execute($product);
            }
        }
    }

    public function testDefaultTopLevelCategory()
    {
        $categoryFixture = new CategoryFixture(
            CategoryBuilder::topLevelCategory()->build()
        );
        $this->categories[] = $categoryFixture;

        /** @var Category $category */
        $category = $this->categoryRepository->get($categoryFixture->getId());
        $this->assertEquals('Top Level Category', $category->getName(), 'Category name');
        $this->assertContains(0, $category->getStoreIds(), 'Assigned admin store id');
        $this->assertContains(1, $category->getStoreIds(), 'Assigned default store id');
        $this->assertEquals('1/2/' . $categoryFixture->getId(), $category->getPath(), 'Category path');
    }

    public function testDefaultChildCategory()
    {
        $parentCategoryFixture = new CategoryFixture(
            CategoryBuilder::topLevelCategory()->build()
        );
        $this->categories[] = $parentCategoryFixture;
        $childCategoryFixture = new CategoryFixture(
            CategoryBuilder::childCategoryOf($parentCategoryFixture)->build()
        );

        /** @var Category $category */
        $category = $this->categoryRepository->get($childCategoryFixture->getId());
        $this->assertEquals('Child Category', $category->getName(), 'Category name');
        $this->assertContains(0, $category->getStoreIds(), 'Assigned admin store id');
        $this->assertContains(1, $category->getStoreIds(), 'Assigned default store id');
        $this->assertEquals(
            '1/2/' . $parentCategoryFixture->getId() . '/' . $childCategoryFixture->getId(),
            $category->getPath(),
            'Category path'
        );
    }

    public function testCategoryWithSpecificAttributes()
    {
        $categoryFixture = new CategoryFixture(
            CategoryBuilder::topLevelCategory()
                ->withName('Custom Name')
                ->withDescription('Custom Description')
                ->withIsActive(false)
                ->withUrlKey('my-url-key')
                ->build()
        );
        $this->categories[] = $categoryFixture;

        /** @var Category $category */
        $category = $this->categoryRepository->get($categoryFixture->getId());
        $this->assertEquals('0', $category->getIsActive(), 'Category should be inactive');
        $this->assertEquals('Custom Name', $category->getName(), 'Category name');
        $this->assertEquals('my-url-key', $category->getUrlKey(), 'Category URL key');
        $this->assertEquals(
            'Custom Description',
            $category->getCustomAttribute('description')->getValue(),
            'Category description'
        );
    }

    public function testCategoryWithProducts()
    {
        $product1 = new ProductFixture(ProductBuilder::aSimpleProduct()->build());
        $product2 = new ProductFixture(ProductBuilder::aSimpleProduct()->build());
        $categoryFixture = new CategoryFixture(
            CategoryBuilder::topLevelCategory()->withProducts([$product1->getSku(), $product2->getSku()])->build()
        );
        $this->products[] = $product1;
        $this->products[] = $product2;
        $this->categories[] = $categoryFixture;

        /** @var Category $category */
        $category = $this->categoryRepository->get($categoryFixture->getId());

        $this->assertEquals(
            [$product1->getId() => 0, $product2->getId() => 1],
            $category->getProductsPosition(),
            'Product positions'
        );
    }

    public function testMultipleCategories()
    {
        $this->categories[0] = new CategoryFixture(
            CategoryBuilder::topLevelCategory()->build()
        );
        $this->categories[1] = new CategoryFixture(
            CategoryBuilder::topLevelCategory()->build()
        );

        /** @var Category $category1 */
        $category1 = $this->categoryRepository->get($this->categories[0]->getId());
        /** @var Category $category2 */
        $category2 = $this->categoryRepository->get($this->categories[1]->getId());
        $this->assertNotEquals(
            $category1->getUrlKey(),
            $category2->getUrlKey(),
            'Categories should be created with different URL keys'
        );
    }
}