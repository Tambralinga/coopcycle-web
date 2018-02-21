<?php

namespace AppBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\RestaurantRepository;
use Doctrine\ORM\QueryBuilder;

final class RestaurantFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $properties = $this->extractProperties($request, Restaurant::class);
        $distance = isset($properties['distance']) ? $properties['distance'] : 3000;

        if ($property === 'coordinate') {
            list($latitude, $longitude) = explode(',', $value);
            $this->logger->info(sprintf('RestaurantFilter :: %s, %s, %s', $latitude, $longitude, $distance));
            RestaurantRepository::addNearbyQueryClause($queryBuilder, $latitude, $longitude, $distance);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        // if (!$this->properties) {
        //     return [];
        // }

        // $description = [];
        // foreach ($this->properties as $property => $strategy) {
        //     $description["regexp_$property"] = [
        //         'property' => $property,
        //         'type' => 'string',
        //         'required' => false,
        //         'swagger' => [
        //             'description' => 'Filter using a regex. This will appear in the Swagger documentation!',
        //             'name' => 'Custom name to use in the Swagger documentation',
        //             'type' => 'Will appear below the name in the Swagger documentation',
        //         ],
        //     ];
        // }

        // return $description;

        return [];
    }
}
