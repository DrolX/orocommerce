<?php

namespace OroB2B\Bundle\ProductBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use OroB2B\Bundle\ProductBundle\Entity\ProductImage;
use OroB2B\Bundle\ProductBundle\Entity\ProductImageType;
use Symfony\Component\Yaml\Yaml;

use Oro\Bundle\AttachmentBundle\Entity\File;

use OroB2B\Bundle\ProductBundle\Entity\Product;

class LoadProductImageData extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * @var array
     */
    protected static $products = [
        'product.1',
        'product.2',
    ];

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return ['OroB2B\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData'];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        foreach (self::$products as $productReference) {
            /** @var Product $product */
            $product = $this->getReference($productReference);
            $image = new File();
            $image->setFilename($product->getSku());
            $this->addReference('img.' . $product->getSku(), $image);

            $productImage = new ProductImage();
            $productImage->setImage($image);
            $productImage->addType(ProductImageType::TYPE_LISTING);

            $product->addImage($productImage);

            $manager->persist($image);
            $manager->persist($product);
        }

        $manager->flush();
    }
}