<?php

namespace Oro\Bundle\ActionBundle\Helper;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use Doctrine\Common\Util\ClassUtils;

use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

class ContextHelper
{
    const ROUTE_PARAM = 'route';
    const ENTITY_ID_PARAM = 'entityId';
    const ENTITY_CLASS_PARAM = 'entityClass';
    const DATAGRID_PARAM = 'datagrid';

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var RequestStack */
    protected $requestStack;

    /** @var array */
    protected $actionDatas = [];

    /** @var  PropertyAccessor */
    protected $propertyAccessor;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param RequestStack $requestStack
     */
    public function __construct(DoctrineHelper $doctrineHelper, RequestStack $requestStack = null)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->requestStack = $requestStack;
    }

    /**
     * @param array|null $context
     * @return array
     */
    public function getContext(array $context = null)
    {
        if (null === $context) {
            $route = $this->getRequestParameter(self::ROUTE_PARAM) ?: $this->getRequestParameter('_route');
            $context = [
                self::ROUTE_PARAM => $route,
                self::ENTITY_ID_PARAM => $this->getRequestParameter(self::ENTITY_ID_PARAM),
                self::ENTITY_CLASS_PARAM => $this->getRequestParameter(self::ENTITY_CLASS_PARAM),
                self::DATAGRID_PARAM => $this->getRequestParameter(self::DATAGRID_PARAM),
            ];
        }

        return $this->normalizeContext($context);
    }

    /**
     * @param array $context
     * @return array
     */
    public function getActionParameters(array $context)
    {
        $request = $this->requestStack->getMasterRequest();

        $params = [
            self::ROUTE_PARAM => $request->get('_route'),
            'fromUrl' => $request->getRequestUri()
        ];

        if (array_key_exists('entity', $context) && is_object($context['entity']) &&
            !$this->doctrineHelper->isNewEntity($context['entity'])
        ) {
            $params['entityId'] = $this->doctrineHelper->getEntityIdentifier($context['entity']);
            $params['entityClass'] = ClassUtils::getClass($context['entity']);
        } elseif (isset($context['entity_class'])) {
            $params['entityClass'] = $context['entity_class'];
        }

        return $params;
    }

    /**
     * @param array|null $context
     * @return ActionData
     */
    public function getActionData(array $context = null)
    {
        $context = $this->getContext($context);

        $hash = $this->generateHash($context, [self::ENTITY_CLASS_PARAM, self::ENTITY_ID_PARAM]);

        if (!array_key_exists($hash, $this->actionDatas)) {
            $entity = null;

            if ($context['entityClass']) {
                $entity = $this->getEntityReference(
                    $context[self::ENTITY_CLASS_PARAM],
                    $context[self::ENTITY_ID_PARAM]
                );
            }

            $this->actionDatas[$hash] = new ActionData(['data' => $entity]);
        }

        return $this->actionDatas[$hash];
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getRequestParameter($name, $default = null)
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? $request->get($name, $default) : $default;
    }

    /**
     * @param array $context
     * @return array
     */
    protected function normalizeContext(array $context)
    {
        return array_merge(
            [
                self::ROUTE_PARAM => null,
                self::ENTITY_ID_PARAM => null,
                self::ENTITY_CLASS_PARAM => null,
                self::DATAGRID_PARAM => null,
            ],
            $context
        );
    }

    /**
     * @param string $entityClass
     * @param mixed $entityId
     * @return Object
     */
    protected function getEntityReference($entityClass, $entityId)
    {
        $entity = null;

        if ($this->doctrineHelper->isManageableEntity($entityClass)) {
            if ($entityId) {
                $entity = $this->doctrineHelper->getEntityReference($entityClass, $entityId);
            } else {
                $entity = $this->doctrineHelper->createEntityInstance($entityClass);
            }
        }

        return $entity;
    }

    /**
     * @param array $context
     * @param array $properties
     * @return string
     */
    protected function generateHash(array $context, array $properties)
    {
        $array = [];
        foreach ($properties as $property) {
            $array[$property] = $this->getPropertyAccessor()->getValue($context, sprintf('[%s]', $property));
        }
        array_multisort($array);

        return md5(json_encode($array, JSON_NUMERIC_CHECK));
    }

    /**
     * @return PropertyAccessor
     */
    protected function getPropertyAccessor()
    {
        if (!$this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }
}
