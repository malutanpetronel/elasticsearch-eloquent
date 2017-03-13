<?php

namespace Isswp101\Persimmon\Repository;

use Isswp101\Persimmon\Contracts\Storable;
use Isswp101\Persimmon\QueryBuilder\IQueryBuilder;

interface IRepository
{
    public function instantiate(string $class): Storable;

    public function find($id, string $class, array $columns = []): Storable;

    public function all(IQueryBuilder $query, string $class, callable $callback = null);

    public function insert(Storable $model);

    public function update(Storable $model);

    public function delete(Storable $model);
}