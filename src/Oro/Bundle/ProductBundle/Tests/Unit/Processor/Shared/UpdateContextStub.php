<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Processor\Shared;

use Oro\Bundle\ApiBundle\Processor\FormContextTrait;
use Oro\Bundle\ApiBundle\Processor\SingleItemContext;

class UpdateContextStub extends SingleItemContext
{
    use FormContextTrait;
}