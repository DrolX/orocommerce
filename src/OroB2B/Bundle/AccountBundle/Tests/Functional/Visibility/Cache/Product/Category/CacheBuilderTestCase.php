<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\Visibility\Cache\Product\Category;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use Oro\Component\Testing\WebTestCase;

use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseProductVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Visibility\Cache\Product\Category\CacheBuilder;

abstract class CacheBuilderTestCase extends WebTestCase
{
    /**
     * @var CacheBuilder
     */
    protected $cacheBuilder;

    public function setUp()
    {
        $this->initClient();

        $this->flushCategoryVisibilityCache();

        $this->loadFixtures(
            [
                'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadProductVisibilityFallbackCategoryData',
            ]
        );

        $this->cacheBuilder = $this->getContainer()->get($this->getCacheBuilderContainerId());
    }

    protected function flushCategoryVisibilityCache()
    {
        $this->getContainer()->get('orob2b_account.storage.category_visibility_storage')->flush();
    }

    /**
     * @return string
     */
    abstract protected function getCacheBuilderContainerId();

    /**
     * @param array $expectedData
     */
    protected function assertProductVisibilityResolvedCorrect(array $expectedData)
    {
        ksort($expectedData['hiddenProductsByAccountGroups']);
        ksort($expectedData['hiddenProductsByAccounts']);
        $this->assertEquals(
            $expectedData,
            [
                'hiddenProducts' => $this->getHiddenProducts(),
                'hiddenProductsByAccountGroups' => $this->getHiddenProductsByAccountGroups(),
                'hiddenProductsByAccounts' => $this->getHiddenProductsByAccounts(),
            ]
        );
    }

    /**
     * @return array
     */
    protected function getHiddenProducts()
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved')
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved');
        $queryBuilder = $repository->createQueryBuilder('pvr');
        $this->selectHiddenProductSku($queryBuilder, 'pvr');
        $results = $queryBuilder->getQuery()
            ->getScalarResult();

        return array_map(
            function ($row) {
                return $row['sku'];
            },
            $results
        );
    }

    /**
     * @return array
     */
    protected function getHiddenProductsByAccountGroups()
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\AccountGroupProductVisibilityResolved')
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\AccountGroupProductVisibilityResolved');
        $queryBuilder = $repository->createQueryBuilder('agpvr')
            ->select('accountGroup.name as account_group_name')
            ->join('agpvr.accountGroup', 'accountGroup');
        $this->selectHiddenProductSku($queryBuilder, 'agpvr');
        $results = $queryBuilder->getQuery()
            ->getScalarResult();

        $res = array_reduce(
            $results,
            function ($results, $row) {
                if (!isset($results[$row['account_group_name']])) {
                    $results[$row['account_group_name']] = [];
                }
                $results[$row['account_group_name']][] = $row['sku'];

                return $results;
            },
            []
        );
        ksort($res);

        return $res;
    }

    /**
     * @return array
     */
    protected function getHiddenProductsByAccounts()
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('doctrine')
            ->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\AccountProductVisibilityResolved')
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\AccountProductVisibilityResolved');
        $queryBuilder = $repository->createQueryBuilder('apvr')
            ->select('account.name as account_name')
            ->join('apvr.account', 'account');
        $this->selectHiddenProductSku($queryBuilder, 'apvr');
        $results = $queryBuilder->getQuery()
            ->getScalarResult();

        $res = array_reduce(
            $results,
            function ($results, $row) {
                if (!isset($results[$row['account_name']])) {
                    $results[$row['account_name']] = [];
                }
                $results[$row['account_name']][] = $row['sku'];

                return $results;
            },
            []
        );
        ksort($res);

        return $res;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     */
    protected function selectHiddenProductSku(QueryBuilder $queryBuilder, $alias)
    {
        $queryBuilder->addSelect('product.sku')
            ->join($alias . '.product', 'product')
            ->andWhere(
                $queryBuilder->expr()->eq(
                    $alias . '.visibility',
                    BaseProductVisibilityResolved::VISIBILITY_HIDDEN
                )
            )
            ->orderBy($alias . '.category');
    }
}
