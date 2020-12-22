<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Select\Where;

use Espo\{
    Core\Exceptions\Error,
    ORM\EntityManager,
    ORM\Entity,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\QueryComposer\BaseQueryComposer as QueryComposer,
};

class Scanner
{
    protected $entityManager;

    private $seedHash = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function applyLeftJoins(SelectBuilder $selectBuilder, Item $item)
    {
        $entityType = $selectBuilder->build()->getFrom();

        if (!$entityType) {
            throw new Error("No entity type.");
        }

        $this->applyLeftJoinsFromItem($selectBuilder, $item, $entityType);
    }

    protected function applyLeftJoinsFromItem(SelectBuilder $selectBuilder, Item $item, string $entityType)
    {
        $type = $item->getType();
        $value = $item->getValue();
        $attribute = $item->getAttribute();

        if (in_array($type, ['subQueryNotIn', 'subQueryIn', 'not'])) {
            return;
        }

        if (in_array($type, ['or', 'and', 'having'])) {
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $subItem) {
                $this->applyLeftJoinsFromItem($selectBuilder, Item::fromRaw($subItem), $entityType);
            }

            return;
        }

        if (!$attribute) {
            return;
        }

        $this->applyLeftJoinsFromAttribute($selectBuilder, $attribute, $entityType);
    }

    protected function applyLeftJoinsFromAttribute(SelectBuilder $selectBuilder, string $attribute, string $entityType)
    {
        if (strpos($attribute, ':') !== false) {
            $argumentList = QueryComposer::getAllAttributesFromComplexExpression($attribute);

            foreach ($argumentList as $argument) {
                $this->applyLeftJoinsFromAttribute($selectBuilder, $argument, $entityType);
            }

            return;
        }

        $seed = $this->getSeed($entityType);

        if (strpos($attribute, '.') !== false) {
            list($link, $attribute) = explode('.', $attribute);

            if ($seed->hasRelation($link)) {
                $queryBuilder->leftJoin($link);

                if ($seed->getRelationType($link) === Entity::HAS_MANY) {
                    $queryBuilder->distinct();
                }
            }

            return;
        }

        $attributeType = $seed->getAttributeType($attribute);

        if ($attributeType === Entity::FOREIGN) {
            $relation = $seed->getAttributeParam($attribute, 'relation');

            if ($relation) {
                $queryBuilder->leftJoin($relation);
            }
        }
    }

    protected function getSeed(string $entityType) : Entity
    {
        if (!isset($this->seedHash[$entityType])) {
            $this->seedHash[$entityType] = $this->entityManager->getEntity($entityType);
        }

        return $this->seedHash[$entityType];
    }
}
