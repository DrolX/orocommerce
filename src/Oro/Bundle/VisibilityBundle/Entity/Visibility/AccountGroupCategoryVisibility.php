<?php

namespace Oro\Bundle\VisibilityBundle\Entity\Visibility;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\ScopeBundle\Entity\Scope;

/**
 * @ORM\Entity(
 *   repositoryClass="Oro\Bundle\VisibilityBundle\Entity\Visibility\Repository\AccountGroupCategoryVisibilityRepository"
 * )
 * @ORM\Table(
 *      name="oro_acc_grp_ctgr_visibility",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(
 *              name="oro_acc_grp_ctgr_vis_uidx",
 *              columns={"category_id"}
 *          )
 *      }
 * )
 * @Config
 */
class AccountGroupCategoryVisibility implements VisibilityInterface
{
    const PARENT_CATEGORY = 'parent_category';
    const CATEGORY = 'category';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Category
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CatalogBundle\Entity\Category")
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $category;

    /**
     * @var string
     *
     * @ORM\Column(name="visibility", type="string", length=255, nullable=true)
     */
    protected $visibility;

    /**
     * @var Scope
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\ScopeBundle\Entity\Scope")
     * @ORM\JoinColumn(name="scope_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $scope;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param Category $category
     *
     * @return $this
     */
    public function setCategory(Category $category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @param Category $category
     * @return string
     */
    public static function getDefault($category)
    {
        return self::CATEGORY;
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($visibility)
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param Category $category
     * @return array
     */
    public static function getVisibilityList($category)
    {
        $visibilityList = [
            self::CATEGORY,
            self::PARENT_CATEGORY,
            self::HIDDEN,
            self::VISIBLE
        ];
        if ($category instanceof Category && !$category->getParentCategory()) {
            unset($visibilityList[array_search(self::PARENT_CATEGORY, $visibilityList)]);
        }
        return $visibilityList;
    }

    /**
     * {@inheritdoc}
     */
    public function getTargetEntity()
    {
        return $this->getCategory();
    }

    /**
     * @param Category $category
     * @return $this
     */
    public function setTargetEntity($category)
    {
        return $this->setCategory($category);
    }

    /**
     * @param Scope $scope
     * @return $this
     */
    public function setScope(Scope $scope = null)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * @return Scope
     */
    public function getScope()
    {
        return $this->scope;
    }
}
