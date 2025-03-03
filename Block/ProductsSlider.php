<?php
declare(strict_types=1);

namespace DaoNguyen\ProductsSlider\Block;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;
use Magento\Framework\Pricing\Render;
use Magento\Catalog\Block\Product\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\DataObject\IdentityInterface;
class ProductsSlider extends AbstractProduct implements BlockInterface, IdentityInterface
{
    protected $_template = 'DaoNguyen_ProductsSlider::products_slider.phtml';

    public function __construct(
        private CollectionFactory $collectionFactory,
        private Visibility $visibility,
        private CategoryRepositoryInterface $categoryRepository,
        private Data $urlHelper,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param int $categoryId
     * @return Collection
     */
    public function getProductCollectionByCategory(int $categoryId)
    {
        $collection = $this->collectionFactory->create();
        $collection->setVisibility($this->visibility->getVisibleInCatalogIds());
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect($this->_catalogConfig->getProductAttributes())
            ->addUrlRewrite();
        $collection->addAttributeToFilter('type_id', 'simple');
        $collection->addAttributeToFilter('status', 1);
        $collection->joinField(
            'stock_status',
            'cataloginventory_stock_status',
            'stock_status',
            'product_id=entity_id',
            '{{table}}.stock_status=1',
            'left'
        );
        /** @var Category $category */
        $category = $this->categoryRepository->get($categoryId);
        $collection->addStoreFilter()->addCategoryFilter(
            $category
        );
        $collection->setPageSize(10);
        return $collection;
    }

    public function getProductPriceHtml(
        Product $product,
        $priceType = null,
        $renderZone = Render::ZONE_ITEM_LIST,
        array $arguments = []
    ) {
        if (!isset($arguments['zone'])) {
            $arguments['zone'] = $renderZone;
        }
        $arguments['zone'] = isset($arguments['zone'])
            ? $arguments['zone']
            : $renderZone;
        $arguments['price_id'] = isset($arguments['price_id'])
            ? $arguments['price_id']
            : 'old-price-' . $product->getId() . '-' . $priceType;
        $arguments['include_container'] = isset($arguments['include_container'])
            ? $arguments['include_container']
            : true;
        $arguments['display_minimal_price'] = isset($arguments['display_minimal_price'])
            ? $arguments['display_minimal_price']
            : true;

        /** @var Render $priceRender */
        $priceRender = $this->getLayout()->getBlock('product.price.render.default');

        $price = '';
        if ($priceRender) {
            $price = $priceRender->render(
                FinalPrice::PRICE_CODE,
                $product,
                $arguments
            );
        }
        return $price;
    }

    public function getAddToCartPostParams(Product $product)
    {
        $url = $this->getAddToCartUrl($product, ['_escape' => false]);
        return [
            'action' => $url,
            'data' => [
                'product' => (int) $product->getEntityId(),
                ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ]
        ];
    }

    public function getIdentities()
    {
        return [\Magento\Catalog\Model\Product::CACHE_TAG];
    }
}
