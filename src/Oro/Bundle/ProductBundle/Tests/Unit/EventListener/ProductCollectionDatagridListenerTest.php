<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\NameStrategyInterface;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\EventListener\ProductCollectionDatagridListener;
use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductCollectionDatagridListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RequestStack|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $requestStack;

    /**
     * @var SegmentManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $segmentManager;

    /**
     * @var ManagerRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var NameStrategyInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $nameStrategy;

    /**
     * @var ProductCollectionDatagridListener
     */
    protected $listener;

    protected function setUp()
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->segmentManager = $this->getMockBuilder(SegmentManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->nameStrategy = $this->createMock(NameStrategyInterface::class);
        $this->listener = new ProductCollectionDatagridListener(
            $this->requestStack,
            $this->segmentManager,
            $this->registry,
            $this->nameStrategy
        );
    }

    public function testOnBuildAfterWithoutRequest()
    {
        /** @var BuildAfter|\PHPUnit_Framework_MockObject_MockObject $event */
        $event = $this->createMock(BuildAfter::class);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->segmentManager->expects($this->never())
            ->method($this->anything());

        $this->listener->onBuildAfter($event);
    }

    public function testOnBuildAfterNotOrmDatasource()
    {
        $dataSource = $this->createMock(DatasourceInterface::class);

        /** @var Datagrid|\PHPUnit_Framework_MockObject_MockObject $dataGrid */
        $dataGrid = $this->createMock(Datagrid::class);
        $dataGrid->expects($this->once())
            ->method('getDatasource')
            ->willReturn($dataSource);

        $event = new BuildAfter($dataGrid);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request());

        $this->segmentManager->expects($this->never())
            ->method($this->anything());

        $this->listener->onBuildAfter($event);
    }

    public function testOnBuildAfterWithoutDefinition()
    {
        $dataSource = $this->createMock(OrmDatasource::class);

        /** @var Datagrid|\PHPUnit_Framework_MockObject_MockObject $dataGrid */
        $dataGrid = $this->createMock(Datagrid::class);
        $dataGrid->expects($this->once())
            ->method('getDatasource')
            ->willReturn($dataSource);
        $this->assertGetGridFullNameCalls($dataGrid, 'grid_name', '1');

        $event = new BuildAfter($dataGrid);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request());

        $this->segmentManager->expects($this->never())
            ->method($this->anything());

        $this->listener->onBuildAfter($event);
    }

    /**
     * @dataProvider gridNameDataProvider
     * @param string $gridName
     * @param string $scope
     * @param string $resolvedName
     */
    public function testOnBuildAfterWhenDefinitionFromRequest($gridName, $scope, $resolvedName)
    {
        $segmentType = new SegmentType(SegmentType::TYPE_DYNAMIC);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->once())
            ->method('getReference')
            ->with(SegmentType::class, SegmentType::TYPE_DYNAMIC)
            ->willReturn($segmentType);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(SegmentType::class)
            ->willReturn($em);

        $segmentDefinition = 'definition';
        $qb = $this->createMock(QueryBuilder::class);

        $dataSource = $this->createMock(OrmDatasource::class);
        $dataSource->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($qb);

        /** @var Datagrid|\PHPUnit_Framework_MockObject_MockObject $dataGrid */
        $dataGrid = $this->createMock(Datagrid::class);
        $dataGrid->expects($this->once())
            ->method('getDatasource')
            ->willReturn($dataSource);

        $this->assertGetGridFullNameCalls($dataGrid, $gridName, $scope);
        $event = new BuildAfter($dataGrid);

        $requestParameterKey = ProductCollectionDatagridListener::SEGMENT_DEFINITION_PARAMETER_KEY . $resolvedName;
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([
                $requestParameterKey => $segmentDefinition
            ]));

        $createdSegment = new Segment();
        $createdSegment->setDefinition($segmentDefinition)
            ->setEntity(Product::class)
            ->setType($segmentType);

        $this->segmentManager->expects($this->once())
            ->method('filterBySegment')
            ->with($qb, $createdSegment);

        $this->listener->onBuildAfter($event);
    }

    /**
     * @return array
     */
    public function gridNameDataProvider(): array
    {
        return [
            'without scope' => ['grid_name', null, 'grid_name:0'],
            'with 0 scope' => ['grid_name', '0', 'grid_name:0'],
            'with scope' => ['grid_name', '1', 'grid_name:1']
        ];
    }

    /**
     * @param Datagrid|\PHPUnit_Framework_MockObject_MockObject $dataGrid
     * @param string $gridName
     * @param string $gridScope
     */
    private function assertGetGridFullNameCalls(Datagrid $dataGrid, $gridName, $gridScope)
    {
        $gridFullName = $gridName;
        if ($gridScope) {
            $gridFullName .= ':' . $gridScope;
        }
        $dataGrid->expects($this->once())
            ->method('getName')
            ->willReturn($gridName);
        $dataGrid->expects($this->once())
            ->method('getScope')
            ->willReturn($gridScope);
        $this->nameStrategy->expects($this->once())
            ->method('buildGridFullName')
            ->with($gridName, $gridScope)
            ->willReturn($gridFullName);
    }
}