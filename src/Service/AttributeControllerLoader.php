<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekApiKeyCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekApiLogCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekBalanceCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekBalanceHistoryCrudController;
use Tourze\DeepSeekApiBundle\Controller\DeepSeekModelCrudController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    private RouteCollection $collection;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();

        $this->collection = new RouteCollection();
        $this->collection->addCollection($this->controllerLoader->load(DeepSeekApiKeyCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(DeepSeekApiLogCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(DeepSeekBalanceCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(DeepSeekBalanceHistoryCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(DeepSeekModelCrudController::class));
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        return $this->collection;
    }
}
